<?php

namespace Starlabs\LaravelProcard\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Starlabs\LaravelProcard\LaravelProcardServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Starlabs\\LaravelProcard\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelProcardServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('procard.base_url', 'https://example.procard-ltd.com/api/');
        config()->set('procard.merchant_id', 'MERCH-TEST');
        config()->set('procard.secret_key', 'secret_key_value');
        config()->set('procard.currency', 'EUR');
        config()->set('procard.language', 'en');
        config()->set('procard.urls.approve_url', 'https://example.com/approve');
        config()->set('procard.urls.decline_url', 'https://example.com/decline');
        config()->set('procard.urls.cancel_url', 'https://example.com/cancel');
        config()->set('procard.urls.callback_url', 'https://example.com/callback');
        config()->set('procard.routes.enabled', false);
        config()->set('procard.api.enabled', false);

        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
