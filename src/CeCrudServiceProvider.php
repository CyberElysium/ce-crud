<?php

namespace Cyberelysium\CeCrud;

use Illuminate\Support\ServiceProvider;
use Cyberelysium\CeCrud\Console\MakeCECrudCommand;
use Cyberelysium\CeCrud\Console\InstallCECrudCommand;

class CeCrudServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([__DIR__.'/../config/ce-crud.php' => config_path('ce-crud.php')]);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/ce-crud.php', 'ce-crud');

        $this->commands([
            InstallCECrudCommand::class,
            MakeCECrudCommand::class,
        ]);
    }
}
