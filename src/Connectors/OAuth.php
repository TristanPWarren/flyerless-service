<?php

namespace Flyerless\Service\Connectors;

use Flyerless\Service\Models\FlyerlessAuthCode as AuthModel;
use BristolSU\Support\Connection\Contracts\Connector;
use Carbon\Carbon;
use FormSchema\Generator\Field;
use FormSchema\Schema\Form;
use GuzzleHttp\Exception\GuzzleException;

class OAuth extends Connector
{

    /**
     * @inheritDoc
     */
    public function request($method, $uri, array $apiOptions = [])
    {
        $apiOptions['form_params'] = array_merge(
          (isset($apiOptions['form_params']) ? $apiOptions['form_params'] : []),
          ['API_token' => $this->getAccessToken()]
        );
        $apiOptions['base_uri'] = $this->getSetting('base_url');
        return $this->client->request($method, $uri, $apiOptions);
    }

    /**
     * @inheritDoc
     */
    public function test(): bool
    {
        try {
            $options = ['Request_Type' => 0];
            $response = $this->request('POST', '', $options);
            $response = json_decode((string) $response->getBody()->getContents(), true);
            if (isset($response['Authorised']) && $response['Authorised'] === "True") {
                return true;
            } else {
                return false;
            }
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    static public function settingsSchema(): Form
    {
        return \FormSchema\Generator\Form::make()->withField(
            \FormSchema\Generator\Field::input('api_key')->inputType('text')->required(true)
                ->label('Api Key')->hint('Your Flyerless API Key')->help('You should contact Flyerless to get an API key')
        )->withField(
          \FormSchema\Generator\Field::input('base_url')->inputType('text')->required(true)
            ->label('Base URL')->hint('The URL of the flyerless API')->help('This should look something like https://bristol.flyerless.co.uk/API/')
        )->getSchema();
    }

    private function getAccessToken(): string
    {
        //Get authModel if it exists
        try {
            $api_key = $this->getSetting('api_key');
            $authModel = AuthModel::where('api_key', '=', $api_key)->firstOrFail();

            if ($authModel->isTokenValid()) {
                return $authModel->access_token;
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            //AuthModel not found
            $authModel = null;
        }

        try {
            return $this->refreshAccessToken($authModel);
        } catch (\Exception $e) {
            throw new \Exception('Flyerless API Token could not be refreshed', 401, $e);
        }

    }


    private function refreshAccessToken(?AuthModel $authModel)
    {
        //Create new AuthModel if one doesn't exist
        if ($authModel === null) {
            $authModel = AuthModel::create([
                'api_key' => $this->getSetting('api_key')
            ]);
        }

        //Get token from flyerless
        $options = [];

        $options['base_uri'] = $this->getSetting('base_url');
        $options['form_params'] = [];
        $options['form_params']['API_KEY'] = $this->getSetting('api_key');
        $tokenResponse = $this->client->request('POST', '', $options);

        //Add token to authModel
        $authModel->access_token = json_decode($tokenResponse->getBody()->getContents())->Token;

        //Add Date to authModel
        $authModel->expires_at = Carbon::now()->addMinutes(25);

    	$authModel->save();

    	// Return the new access token
    	return $authModel->access_token;
    }

}
