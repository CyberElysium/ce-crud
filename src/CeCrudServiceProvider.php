<?php

namespace Cyberelysium\CeCrud;

use Illuminate\Support\ServiceProvider;

class CeCrudServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCECrudCommand::class,
                MakeCECrudCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'ce-crud');

        // Register the main class to use with the facade
        $this->app->singleton('ce-crud', function () {
            return new CeCrud;
        });
    }
}
