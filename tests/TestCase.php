<?php

namespace Flyerless\Tests\Service;

use BristolSU\Support\Testing\AssertsEloquentModels;
use Flyerless\Service\FlyerlessServiceProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class TestCase extends \BristolSU\Support\Testing\TestCase
{

    use AssertsEloquentModels, ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__  . '/../database/factories');
    }

    public function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            FlyerlessServiceProvider::class
        ]);
    }

}
