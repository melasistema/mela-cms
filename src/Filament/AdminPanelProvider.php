<?php

namespace Melasistema\MelaCms\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Melasistema\MelaCms\Http\Middleware\SetAdminLocale;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $namespace = $this->app->getNamespace();

        return $panel
            ->default()
            ->id('admin')
            ->path(config('mela-cms.admin_path'))
            ->login()
            // Every user edits their own name, email and password here, whatever their role.
            ->profile(config('mela-cms.profile_page'), isSimple: false)
            // The core's own screens. A site removes or replaces one in config/mela-cms.php.
            ->resources(array_values(array_filter(config('mela-cms.resources'))))
            // The site's own resources, pages and widgets live in the client app.
            ->discoverResources(in: app_path('Filament/Resources'), for: $namespace.'Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: $namespace.'Filament\\Pages')
            ->pages([
                Dashboard::class,
                // The core's own pages, removed or replaced the same way.
                ...array_values(array_filter(config('mela-cms.pages'))),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: $namespace.'Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent, so Livewire requests are in the admin language too.
            ->middleware([SetAdminLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
