<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\View\View;
use Melasistema\MelaCms\Filament\AdminPanelProvider;

/**
 * Stands in for a site's own panel provider, with its own colour and logo
 * and without the MelaCMS login logo and footer.
 */
class SitePanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->colors(['primary' => Color::Emerald])
            ->brandLogo('https://example.com/logo.svg');
    }

    protected function loginLogo(): ?View
    {
        return null;
    }

    protected function footer(): ?View
    {
        return null;
    }
}
