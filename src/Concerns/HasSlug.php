<?php

namespace Melasistema\MelaCms\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Melasistema\MelaCms\Languages;

/**
 * For a model with a `slug` column, found on the site by its address.
 *
 * When `slug` is one of the translatable attributes of the model, each
 * language has its own slug, and a language without one uses the slug of the
 * default language.
 *
 *     Post::published()->whereSlug($slug)->firstOrFail()
 */
trait HasSlug
{
    /**
     * The record at this slug in the language of the request, or in the
     * given language.
     */
    public function scopeWhereSlug(Builder $query, string $slug, ?string $locale = null): void
    {
        if (! $this->hasTranslatedSlug()) {
            $query->where($this->qualifyColumn('slug'), $slug);

            return;
        }

        $column = $this->qualifyColumn('slug');
        $locale ??= app(Languages::class)->current();
        $default = app(Languages::class)->default();

        $query->where(function (Builder $query) use ($column, $slug, $locale, $default) {
            $query->where("{$column}->{$locale}", $slug);

            if ($locale !== $default) {
                $query->orWhere(fn (Builder $query) => $query
                    ->whereNull("{$column}->{$locale}")
                    ->where("{$column}->{$default}", $slug));
            }
        });
    }

    /**
     * The slug in every site language: [locale => slug].
     *
     * @return array<string, string|null>
     */
    public function slugs(): array
    {
        $slugs = [];

        foreach (app(Languages::class)->all() as $locale) {
            $slugs[$locale] = $this->hasTranslatedSlug() ? $this->getTranslation('slug', $locale) : $this->slug;
        }

        return $slugs;
    }

    protected function hasTranslatedSlug(): bool
    {
        return method_exists($this, 'isTranslatableAttribute') && $this->isTranslatableAttribute('slug');
    }
}
