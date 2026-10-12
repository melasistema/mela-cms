<?php

namespace Melasistema\MelaCms;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Melasistema\MelaCms\Console\InstallCommand;
use Melasistema\MelaCms\Console\RegenerateMediaCommand;
use Melasistema\MelaCms\Console\UpdateCommand;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Http\Controllers\PageController;
use Melasistema\MelaCms\Http\Controllers\SitemapController;
use Melasistema\MelaCms\Http\Middleware\SetLocale;
use Melasistema\MelaCms\Install\Project;
use Melasistema\MelaCms\Models\Media;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\Policies\ContentPolicy;
use Melasistema\MelaCms\Policies\MediaPolicy;
use Melasistema\MelaCms\Policies\UserPolicy;
use Spatie\Translatable\Translatable;

class MelaCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfig();

        $this->app->singleton(Project::class, fn () => new Project($this->app->basePath()));
        $this->app->scoped(Settings::class);
        $this->app->scoped(Languages::class);
        $this->app->scoped(Seo::class);

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mela-cms');

        // Last of the global middleware: after the proxies are trusted, before
        // the request is routed.
        $this->app->make(Kernel::class)->pushMiddleware(SetLocale::class);

        Blade::directive('hreflang', fn () => '<?php echo app(\\'.Languages::class.'::class)->hreflang(); ?>');
        Blade::directive('seo', fn () => '<?php echo app(\\'.Seo::class.'::class)->render()."\n"; ?>');
        Blade::directive('richtext', fn (string $expression) => '<?php echo \\'.RichContent::class."::html({$expression}); ?>");

        // The pages of the site, at every address that no other route takes.
        // A fallback route of the site replaces this one.
        Route::fallback(PageController::class)->middleware('web');

        // A route of the site at the same address replaces this one.
        Route::get('sitemap.xml', SitemapController::class)->name('mela-cms.sitemap');

        // A super-admin passes every authorization check.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole(Role::SuperAdmin->value) ? true : null);

        // Registered before the site's providers boot, so a site can replace
        // it with its own Gate::policy() call.
        Gate::policy(config('auth.providers.users.model'), UserPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Page::class, ContentPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UpdateCommand::class,
                RegenerateMediaCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/mela-cms.php' => config_path('mela-cms.php'),
            ], 'mela-cms-config');
        }
    }

    /**
     * Not mergeConfigFrom(), for two reasons. It does nothing while the config
     * is cached, so a cache made before a core update would lack every key the
     * new version adds. And it merges one level deep, so a site whose published
     * config predates a core resource, page or media option would never get it.
     */
    private function mergeConfig(): void
    {
        $defaults = require __DIR__.'/../config/mela-cms.php';
        $config = [...$defaults, ...config('mela-cms', [])];

        foreach (['resources', 'pages', 'media'] as $key) {
            $config[$key] = [...$defaults[$key], ...($config[$key] ?? [])];
        }

        config(['mela-cms' => $config]);
    }
}
