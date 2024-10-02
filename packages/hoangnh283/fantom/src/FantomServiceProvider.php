<?php
namespace Hoangnh283\Fantom;

use Illuminate\Support\ServiceProvider;
use Hoangnh283\Fantom\Services\FantomService;
use Hoangnh283\Fantom\Console\Commands;

class FantomServiceProvider extends ServiceProvider {
    public function boot()
    {
        
        // Load routes
        // $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // // Publish config
        // $this->publishes([
        //     __DIR__.'/config/telegram.php' => config_path('telegram.php'),
        // ]);
    }
    public function register()
    {
        $this->app->bind(FantomService::class, function ($app) {
            return new FantomService();
        });
    }
}