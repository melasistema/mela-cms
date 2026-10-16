<?php

namespace Melasistema\MelaCms\Filament\Forms;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\SiteRoutes;

/**
 * A form field for the slug of a record: lowercase letters, numbers and
 * hyphens, and no other record of the same model with the same slug. Inside
 * Translations each language has its own slug.
 *
 *     Translations::make([
 *         TextInput::make('title')->required()->live(onBlur: true)->afterStateUpdated(SlugInput::fill()),
 *         SlugInput::make('slug')->required(),
 *     ])
 *
 * SlugInput::fill() on the title writes the slug from it while the slug is
 * still empty.
 */
class SlugInput extends TextInput
{
    protected bool $isPath = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->maxLength(255)
            ->rule(fn (SlugInput $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component) {
                if (! is_string($value) || $value === '') {
                    return;
                }

                $segment = '[a-z0-9]+(?:-[a-z0-9]+)*';

                if (! preg_match($component->isPath ? "~^{$segment}(?:/{$segment})*$~" : "~^{$segment}$~", $value)) {
                    $fail($component->isPath
                        ? 'Use lowercase letters, numbers and hyphens, with a slash between two levels.'
                        : 'Use lowercase letters, numbers and hyphens only.');

                    return;
                }

                if ($component->isPath && (in_array(Str::before($value, '/'), $component->reservedSegments(), true) || $component->hasRoute($value))) {
                    $fail('This address is already used by the site.');

                    return;
                }

                if ($component->isTaken($value)) {
                    $fail(__('validation.unique', ['attribute' => $component->getValidationAttribute()]));
                }
            });
    }

    /**
     * The slug is a whole address under the root of the site: it may have
     * levels (`services/web-design`), and may not start like a language or
     * like the admin panel, nor be an address a route of the site answers.
     */
    public function path(bool $condition = true): static
    {
        $this->isPath = $condition;

        return $this;
    }

    /**
     * For afterStateUpdated() of the field the slug is made from, which
     * must be live().
     */
    public static function fill(string $slug = 'slug'): Closure
    {
        return function (Field $component, Get $get, Set $set, ?string $state) use ($slug): void {
            // Inside Translations the title is "title.it", so the slug is "slug.it".
            $locale = Str::after($component->getStatePath(false), $component->getName());
            $target = $slug.$locale;

            if (blank($get($target)) && filled($state)) {
                $set($target, Str::slug($state, language: ltrim($locale, '.') ?: app(Languages::class)->default()));
            }
        };
    }

    /**
     * The language of the field inside Translations, or null when the slug
     * is not translated.
     */
    protected function locale(): ?string
    {
        $locale = ltrim(Str::after($this->getStatePath(false), $this->getName()), '.');

        return $locale === '' ? null : $locale;
    }

    protected function isTaken(string $slug): bool
    {
        $model = $this->getModel();

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return false;
        }

        $record = $this->getRecord();
        $column = $this->getName().($this->locale() === null ? '' : '->'.$this->locale());

        return $model::query()
            ->where($column, $slug)
            ->when($record instanceof Model && $record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();
    }

    /**
     * Whether a route of the site answers the address. Pages are served by
     * the fallback route, so the page would never be shown.
     */
    protected function hasRoute(string $path): bool
    {
        return SiteRoutes::answer($path);
    }

    /**
     * @return list<string>
     */
    protected function reservedSegments(): array
    {
        return [
            ...array_map(Languages::slug(...), app(Languages::class)->all()),
            Str::before(trim((string) config('mela-cms.admin_path'), '/'), '/'),
            'livewire',
            'storage',
        ];
    }
}
