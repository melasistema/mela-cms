<?php

namespace Melasistema\MelaCms\Filament\Forms;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/**
 * The form section where an editor writes what search engines and social
 * networks show for a record. Everything is optional and saved in the `seo`
 * JSON column of a model with the HasSeo trait.
 *
 *     SeoFields::make()
 */
class SeoFields
{
    public static function make(string $column = 'seo'): Section
    {
        return Section::make('Search engines')
            ->description('Optional. Without these the title, the cover image and the description of the site are used.')
            ->collapsible()
            ->collapsed()
            ->schema([
                Translations::make([
                    TextInput::make("{$column}.title")
                        ->label('Title')
                        ->helperText('Shown in search results and in the browser tab, in place of the title.')
                        ->maxLength(255),
                    Textarea::make("{$column}.description")
                        ->label('Description')
                        ->helperText('One or two sentences shown under the title in search results.')
                        ->rows(3)
                        ->maxLength(300),
                ]),
                MediaPicker::make("{$column}.image")
                    ->label('Share image')
                    ->helperText('Shown when the address is shared on a social network, in place of the cover image.')
                    ->images(),
                Toggle::make("{$column}.noindex")
                    ->label('Keep out of search engines')
                    ->helperText('The page stays reachable, but search engines are asked not to list it and it is left out of the sitemap.'),
            ]);
    }
}
