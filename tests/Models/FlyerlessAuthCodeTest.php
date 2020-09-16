<?php

namespace Flyerless\Tests\Service\Models;

use Flyerless\Service\Models\FlyerlessAuthCode;
use Flyerless\Tests\Service\TestCase;
use Carbon\Carbon;

class FlyerlessAuthCodeTest extends TestCase
{

    /** @test */
    public function a_model_can_be_created()
    {
        $expiresAt = Carbon::now()->addDay();

        $authCode = factory(FlyerlessAuthCode::class)->create([
          'api_key' => 'abc123',
          'access_token' => '123abc',
          'expires_at' => $expiresAt
        ]);

        $this->assertDatabaseHas('flyerless_auth_codes', [
          'api_key' => 'abc123',
          'access_token' => '123abc',
          'expires_at' => $expiresAt->format('Y-m-d H:i:s')
        ]);
    }

    /** @test */
    public function the_auth_code_refresh_token_and_expires_at_are_all_hidden()
    {
        $authCode = factory(FlyerlessAuthCode::class)->create();

        $attributes = $authCode->toArray();

        $this->assertArrayNotHasKey('access_token', $attributes);
        $this->assertArrayNotHasKey('api_key', $attributes);
        $this->assertArrayNotHasKey('expires_at', $attributes);
    }

    /** @test */
    public function isTokenValid_returns_true_if_expires_at_is_in_the_future()
    {
        $authCode = factory(FlyerlessAuthCode::class)->create([
          'expires_at' => Carbon::now()->addDay()
        ]);

        $this->assertTrue($authCode->isTokenValid());
    }

    /** @test */
    public function isTokenValid_returns_true_if_expires_at_is_in_the_past()
    {
        $authCode = factory(FlyerlessAuthCode::class)->create([
          'expires_at' => Carbon::now()->subDay()
        ]);

        $this->assertFalse($authCode->isTokenValid());
    }

    /** @test */
    public function it_creates_a_model_with_just_an_api_key(){
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $authCode = FlyerlessAuthCode::create([
          'api_key' => 'apikey'
        ]);

        $this->assertEquals('apikey', $authCode->api_key);
        $this->assertEquals('', $authCode->access_token);
        $this->assertTrue($now->addMinutes(20)->diffInSeconds($authCode->expires_at) < 1 && $now->addMinutes(20)->diffInSeconds($authCode->expires_at) > -1
        );
    }

}
