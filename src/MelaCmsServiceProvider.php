<?php

namespace Melasistema\MelaCms;

use Illuminate\Support\ServiceProvider;

class MelaCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mela-cms.php', 'mela-cms');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mela-cms.php' => config_path('mela-cms.php'),
            ], 'mela-cms-config');
        }
    }
}
