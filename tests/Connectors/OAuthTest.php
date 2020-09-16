<?php

namespace Flyerless\Tests\Service\Connectors;

use Flyerless\Service\Connectors\OAuth;
use Flyerless\Service\Models\FlyerlessAuthCode;
use Flyerless\Tests\Service\TestCase;
use BristolSU\Support\Connection\Contracts\Client\Client;
use Carbon\Carbon;
use FormSchema\Schema\Form;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Config\Repository;
use Prophecy\Argument;
use function GuzzleHttp\Psr7\stream_for;

class OAuthTest extends TestCase
{

    /** @test */
    public function settingsSchema_returns_a_form_schema()
    {
        $this->assertInstanceOf(Form::class, OAuth::settingsSchema());
    }

    /** @test */
    public function request_adds_the_correct_authentication_options_if_body_already_given()
    {
        $client = $this->prophesize(Client::class);
        $options = ['form_params' => ['test' => 'abc']];

        $client->request('GET', '/', [
          'form_params' => ['test' => 'abc', 'API_token' => 'access-token123'], 'base_uri' => 'https://example.com'
        ])->shouldBeCalled();

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => 'access-token123',
          'expires_at' => Carbon::now()->addDay()
        ]);

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_url' => 'https://example.com'
        ]);
        $oAuth->request('GET', '/', $options);
    }

    /** @test */
    public function request_adds_the_correct_authentication_options_if_headers_not_already_given()
    {
        $client = $this->prophesize(Client::class);

        $client->request('GET', '/', [
          'form_params' => ['API_token' => 'access-token123'], 'base_uri' => 'https://example.com'
        ])->shouldBeCalled();

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => 'access-token123',
          'expires_at' => Carbon::now()->addDay()
        ]);

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_url' => 'https://example.com'
        ]);
        $oAuth->request('GET', '/', []);
    }

    /** @test */
    public function request_refreshes_the_auth_code_if_expired()
    {
        $client = $this->prophesize(Client::class);

        $client->request('GET', '/', [
          'form_params' => ['API_token' => 'access-token456'], 'base_uri' => 'https://example.com'
        ])->shouldBeCalled();
        $client->request('POST', '', [
          'form_params' => ['API_KEY' => 'abc123'], 'base_uri' => 'https://example.com'
        ])->shouldBeCalled()->willReturn(new Response(200, [], stream_for(json_encode([
          'Token' => 'access-token456'
        ]))));

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => 'access-token123',
          'expires_at' => Carbon::now()->subDay()
        ]);

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_url' => 'https://example.com'
        ]);

        $oAuth->request('GET', '/', []);

        $newAuthCode = $authCode->refresh();
        $this->assertEquals('access-token456', $newAuthCode->access_token);
    }

    /** @test */
    public function request_refreshes_the_auth_code_if_never_retrieved()
    {
        $client = $this->prophesize(Client::class);

        $client->request('GET', '/', [
          'form_params' => ['API_token' => 'access-token456'], 'base_uri' => 'https://example.com'
        ])->shouldBeCalled();
        $client->request('POST', '', [
          'form_params' => ['API_KEY' => 'abc123'], 'base_uri' => 'https://example.com'
        ])->shouldBeCalled()->willReturn(new Response(200, [], stream_for(json_encode([
          'Token' => 'access-token456'
        ]))));

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_url' => 'https://example.com'
        ]);

        $oAuth->request('GET', '/', []);

        $newAuthCode = FlyerlessAuthCode::where(['api_key' => 'abc123'])->first();
        $this->assertInstanceOf(FlyerlessAuthCode::class, $newAuthCode);
        $this->assertTrue($newAuthCode->exists);

        $this->assertEquals('access-token456', $newAuthCode->access_token);
    }

    /** @test */
    public function request_throws_an_error_if_token_cannot_be_refreshed()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Flyerless API Token could not be refreshed');

        $client = $this->prophesize(Client::class);
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => '',
          'expires_at' => Carbon::now()->subDay()
        ]);

        $client->request('POST', '', [
          'base_uri' => 'https://test.com/bristol',
          'form_params' => [
            'API_KEY' => 'abc123'
          ]

        ])->shouldBeCalled()->willThrow($this->prophesize(ClientException::class)->reveal());


        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_url' => 'https://test.com/bristol'
        ]);
        $oAuth->request('GET', 'test', []);
    }

    /** @test */
    public function test_makes_a_get_request_to_the_service_and_returns_true_if_authenticated()
    {
        $client = $this->prophesize(Client::class);
        $client->request('POST', '', Argument::type('array'))->shouldBeCalled()->willReturn(
          new Response(403, [], stream_for(json_encode(['Authorised' => 'True']))));

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => '123abc',
          'expires_at' => Carbon::now()->addDay()
        ]);

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_uri' => 'test.com'
        ]);
        $this->assertTrue(
          $oAuth->test()
        );
    }

    /** @test */
    public function test_makes_a_get_request_to_the_service_and_returns_false_if_not_authenticated()
    {
        $client = $this->prophesize(Client::class);
        $client->request('POST', '', Argument::type('array'))->shouldBeCalled()->willReturn(
          new Response(403, [], stream_for(json_encode(['Authorised' => 'False']))));

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => '123abc',
          'expires_at' => Carbon::now()->addDay()
        ]);

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_uri' => 'test.com'
        ]);
        $this->assertFalse(
          $oAuth->test()
        );
    }

    /** @test */
    public function test_makes_a_get_request_to_the_service_and_returns_false_if_error_thrown()
    {
        $client = $this->prophesize(Client::class);
        $client->request('POST', '', Argument::type('array'))->shouldBeCalled()->willThrow(
            $this->prophesize(ClientException::class)->reveal()
        );

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => '123abc',
          'expires_at' => Carbon::now()->addDay()
        ]);

        $oAuth = new OAuth($client->reveal());
        $oAuth->setSettings([
          'api_key' => 'abc123',
          'base_uri' => 'test.com'
        ]);
        $this->assertFalse(
          $oAuth->test()
        );
    }

}
