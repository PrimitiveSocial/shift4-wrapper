<?php

namespace PrimitiveSocial\Shift4Wrapper;

use Illuminate\Support\ServiceProvider;

class Shift4WrapperServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'primitivesocial');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'primitivesocial');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shift4wrapper.php', 'shift4wrapper');

        // Register the service the package provides.
        $this->app->singleton('shift4wrapper', function ($app) {
            return new Shift4Wrapper;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['shift4wrapper'];
    }
    
    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/shift4wrapper.php' => config_path('shift4wrapper.php'),
        ], 'shift4wrapper.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/primitivesocial'),
        ], 'shift4wrapper.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/primitivesocial'),
        ], 'shift4wrapper.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/primitivesocial'),
        ], 'shift4wrapper.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
