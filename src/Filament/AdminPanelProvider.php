<?php

namespace Melasistema\MelaCms\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Melasistema\MelaCms\Http\Middleware\SetAdminLocale;
use Melasistema\MelaCms\Install\CoreVersion;
use Melasistema\MelaCms\Settings;

/**
 * The admin panel.
 *
 * A site that changes the panel (colours, logo, theme, plugins) extends this
 * class, overrides panel() starting from parent::panel($panel), and sets its
 * class as `panel_provider` in config/mela-cms.php. Its colours and favicon
 * replace the MelaCMS ones, and loginLogo() or footer() returning null removes the MelaCMS mark from
 * the login page or the "Built with MelaCMS" line.
 */
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
            // The name an admin gave the site, in the language of the panel.
            ->brandName(fn () => app(Settings::class)->translated('site.name', config('app.name')))
            // The MelaCMS palette: Mela Blue for actions, the cool grays of Ink and Slate.
            ->colors([
                'primary' => Color::hex('#1d6fa5'),
                'gray' => Color::Slate,
            ])
            // Inline, so the package needs no file in the site's public/ directory.
            ->favicon(fn () => 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(__DIR__.'/../../resources/images/favicon.svg')))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, fn () => $this->loginLogo())
            ->renderHook(PanelsRenderHook::FOOTER, fn () => $this->footer())
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

    /**
     * The MelaCMS mark at the top of the login page and the other sign-in pages.
     */
    protected function loginLogo(): ?View
    {
        return view('mela-cms::filament.login-logo');
    }

    /**
     * The line at the bottom of every admin page, with the core version for
     * signed-in users.
     */
    protected function footer(): ?View
    {
        return view('mela-cms::filament.footer', [
            'version' => filament()->auth()->check() ? app(CoreVersion::class)->installed() : null,
        ]);
    }
}
