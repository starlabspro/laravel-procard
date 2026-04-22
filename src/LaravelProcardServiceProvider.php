<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Starlabs\LaravelProcard\Http\Middleware\VerifyProcardSignature;

class LaravelProcardServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-procard')
            ->hasConfigFile()
            ->hasMigration('create_procard_table');
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(ProcardService::class, function () {
            return new ProcardService;
        });
    }

    public function bootingPackage(): void
    {
        $this->app['router']->aliasMiddleware('procard', VerifyProcardSignature::class);

        if ($this->app->runningInConsole()) {
            return;
        }

        if (! config('procard.routes.enabled', true)) {
            return;
        }

        $prefix = config('procard.routes.prefix', '');
        $middleware = config('procard.routes.middleware', 'web');

        if (config('procard.api.enabled')) {
            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(__DIR__.'/Routes/procard_api.php');
        } else {
            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(__DIR__.'/Routes/procard.php');
        }
    }
}
