<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;

/**
 * Stands in for a site's own Settings page, with a field of its own.
 */
class SiteSettingsPage extends ManageSettings
{
    public static function formComponents(): array
    {
        return [
            ...parent::formComponents(),
            TextInput::make('booking_url')->url(),
        ];
    }
}
