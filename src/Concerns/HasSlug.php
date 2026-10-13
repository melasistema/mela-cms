<?php

namespace Melasistema\MelaCms\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Melasistema\MelaCms\Languages;

/**
 * For a model with a `slug` column, found on the site by its address.
 *
 * When `slug` is one of the translatable attributes of the model, each
 * language has its own slug. A language without one uses the slug of the
 * default language, or of the next site language that has one, so a record
 * keeps an address when the default language changes.
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
        $languages = app(Languages::class)->fallbacks($locale);

        // Each language counts only when the ones before it have no slug.
        $query->where(function (Builder $query) use ($column, $slug, $languages) {
            foreach ($languages as $index => $language) {
                $query->orWhere(function (Builder $query) use ($column, $slug, $languages, $index, $language) {
                    foreach (array_slice($languages, 0, $index) as $empty) {
                        $query->whereNull("{$column}->{$empty}");
                    }

                    $query->where("{$column}->{$language}", $slug);
                });
            }
        });
    }

    /**
     * The slug the record answers to in the language of the request, or in
     * the given language: its own, or the one whereSlug() falls back to.
     */
    public function slugIn(?string $locale = null): ?string
    {
        if (! $this->hasTranslatedSlug()) {
            return $this->slug;
        }

        foreach (app(Languages::class)->fallbacks($locale) as $language) {
            $slug = $this->getTranslation('slug', $language, false);

            if (filled($slug)) {
                return $slug;
            }
        }

        return null;
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
            $slugs[$locale] = $this->slugIn($locale);
        }

        return $slugs;
    }

    protected function hasTranslatedSlug(): bool
    {
        return method_exists($this, 'isTranslatableAttribute') && $this->isTranslatableAttribute('slug');
    }
}
