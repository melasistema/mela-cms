<?php

namespace Melasistema\MelaCms;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Melasistema\MelaCms\Console\InstallCommand;
use Melasistema\MelaCms\Console\UpdateCommand;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Http\Middleware\SetLocale;
use Melasistema\MelaCms\Install\Project;
use Melasistema\MelaCms\Policies\UserPolicy;
use Spatie\Translatable\Translatable;

class MelaCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mela-cms.php', 'mela-cms');
        $this->mergeCoreScreens('resources');
        $this->mergeCoreScreens('pages');

        $this->app->singleton(Project::class, fn () => new Project($this->app->basePath()));
        $this->app->scoped(Settings::class);
        $this->app->scoped(Languages::class);

        // A translation that is missing falls back to the default language of
        // the site, not to the app's fallback locale.
        $this->app->extend(Translatable::class, function (Translatable $translatable, $app) {
            $translatable->fallbackLocale = $app->make(Languages::class)->default();

            return $translatable;
        });

        $this->app->register(AdminPanelProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Last of the global middleware: after the proxies are trusted, before
        // the request is routed.
        $this->app->make(Kernel::class)->pushMiddleware(SetLocale::class);

        Blade::directive('hreflang', fn () => '<?php echo app(\\'.Languages::class.'::class)->hreflang(); ?>');

        // A super-admin passes every authorization check.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole(Role::SuperAdmin->value) ? true : null);

        // Registered before the site's providers boot, so a site can replace
        // it with its own Gate::policy() call.
        Gate::policy(config('auth.providers.users.model'), UserPolicy::class);

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

    /**
     * mergeConfigFrom() merges one level deep. A site whose published config
     * predates a core resource or page would otherwise never get it.
     */
    private function mergeCoreScreens(string $key): void
    {
        $defaults = (require __DIR__.'/../config/mela-cms.php')[$key];

        config(["mela-cms.{$key}" => [...$defaults, ...config("mela-cms.{$key}")]]);
    }
}
