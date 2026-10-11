<?php

namespace Melasistema\MelaCms\Filament\Forms;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Melasistema\MelaCms\Languages;

/**
 * Form fields for translatable attributes: one tab per site language, the
 * default language first. A site in one language gets the fields without tabs.
 *
 *     Translations::make([
 *         TextInput::make('title')->required(),
 *         RichEditor::make('body'),
 *     ])
 *
 * Write each field once. A required field is required in the default
 * language only.
 */
class Translations
{
    /**
     * @param  array<Field>  $fields
     */
    public static function make(array $fields): Component
    {
        $languages = app(Languages::class);

        if ($languages->translations() === []) {
            return Group::make(static::fieldsFor($languages->default(), $fields, isDefault: true));
        }

        return Tabs::make('Translations')->tabs(array_map(
            fn (string $locale) => Tab::make(Languages::name($locale))
                ->schema(static::fieldsFor($locale, $fields, $locale === $languages->default())),
            $languages->all(),
        ));
    }

    /**
     * @param  array<Field>  $fields
     * @return array<Field>
     */
    protected static function fieldsFor(string $locale, array $fields, bool $isDefault): array
    {
        return array_map(function (Field $field) use ($locale, $isDefault) {
            // The attribute holds [locale => value], so "title" becomes "title.it".
            $field = $field->getClone()->statePath($field->getName().'.'.$locale);

            return $isDefault ? $field : $field->required(false);
        }, $fields);
    }
}
