<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Melasistema\MelaCms\Filament\AdminPanelProvider;

/**
 * Stands in for a site's own panel provider, with its own colour and logo.
 */
class SitePanelProvider extends AdminPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->colors(['primary' => Color::Emerald])
            ->brandLogo('https://example.com/logo.svg');
    }
}
