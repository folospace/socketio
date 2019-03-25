<?php

namespace Folospace\Socketio;

use Illuminate\Support\ServiceProvider;

class SocketioServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (!class_exists('swoole_websocket_server')) {
            die('php swoole extension not prepared !');
        }
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'folospace');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'folospace');
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
        $this->mergeConfigFrom(__DIR__.'/../config/socketio.php', 'socketio');

        // Register the service the package provides.
        $this->app->singleton('socketio', function ($app) {
            return new Socketio;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['socketio'];
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
            __DIR__.'/../config/socketio.php' => config_path('socketio.php'),
        ], 'socketio.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/folospace'),
        ], 'socketio.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/folospace'),
        ], 'socketio.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/folospace'),
        ], 'socketio.views');*/

        // Registering package commands.
         $this->commands([
             \Folospace\Socketio\Commands\Socketio::class
         ]);
    }
}
