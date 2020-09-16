<?php

namespace Flyerless\Service;

use BristolSU\Support\Connection\Contracts\ConnectorStore;
use Flyerless\Service\Connectors\OAuth;
use Illuminate\Support\ServiceProvider;

class FlyerlessServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function boot()
    {
        $connectorStore = $this->app->make(ConnectorStore::class);
        $connectorStore->register(
          'Flyerless Api (Required to use Flyerless Club Description Update)',
          'Connect to Flyerless',
          'flyerless-club-api',
          'flyerless',
          OAuth::class
        );
    }

}
