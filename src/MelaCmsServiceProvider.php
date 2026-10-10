<?php

namespace Melasistema\MelaCms;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Melasistema\MelaCms\Console\InstallCommand;
use Melasistema\MelaCms\Console\UpdateCommand;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Install\Project;

class MelaCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mela-cms.php', 'mela-cms');

        $this->app->singleton(Project::class, fn () => new Project($this->app->basePath()));

        $this->app->register(AdminPanelProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // A super-admin passes every authorization check.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole(Role::SuperAdmin->value) ? true : null);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UpdateCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/mela-cms.php' => config_path('mela-cms.php'),
            ], 'mela-cms-config');
        }
    }
}
