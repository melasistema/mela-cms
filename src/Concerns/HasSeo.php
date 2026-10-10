<?php

namespace Melasistema\MelaCms\Concerns;

use Illuminate\Support\Arr;
use Melasistema\MelaCms\Languages;

/**
 * For a model with a nullable JSON `seo` column, filled by the SeoFields of
 * its form: a title and a description for search engines in each language,
 * an image for social networks, and whether the record is kept out of search
 * engines.
 *
 * Seo::for($model) reads these, with the title and the cover of the record
 * as fallbacks.
 */
trait HasSeo
{
    public function initializeHasSeo(): void
    {
        $this->mergeCasts(['seo' => 'array']);
    }

    /**
     * The title for search engines: the one written for them, or the title
     * of the record.
     */
    public function seoTitle(): ?string
    {
        return app(Languages::class)->translation(Arr::get($this->seo ?? [], 'title'))
            ?? $this->getAttribute('title')
            ?? $this->getAttribute('name');
    }

    public function seoDescription(): ?string
    {
        return app(Languages::class)->translation(Arr::get($this->seo ?? [], 'description'));
    }

    /**
     * The id of the image shown when the record is shared: the one chosen
     * for that, or the cover of the record.
     */
    public function seoImageId(): int|string|null
    {
        return Arr::get($this->seo ?? [], 'image') ?: $this->getAttribute('cover_id');
    }

    public function seoNoindex(): bool
    {
        return (bool) Arr::get($this->seo ?? [], 'noindex', false);
    }
}
