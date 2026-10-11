<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;

/**
 * A site that adds fields for its "home" template to the page form.
 */
class SitePageResource extends PageResource
{
    public static function formComponents(): array
    {
        return [
            ...parent::formComponents(),
            TextInput::make('fields.hero.link')
                ->visible(fn (Get $get): bool => $get('template') === 'home'),
            Translations::make([
                TextInput::make('fields.hero.button'),
            ]),
        ];
    }
}
