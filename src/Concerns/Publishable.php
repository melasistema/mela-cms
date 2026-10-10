<?php

namespace Melasistema\MelaCms\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Melasistema\MelaCms\Enums\PublicationStatus;

/**
 * For a model with a nullable `published_at` column. Without a date the
 * record is a draft; with a date to come it goes public on that date.
 *
 *     Post::published()->latest('published_at')->get()
 */
trait Publishable
{
    public function initializePublishable(): void
    {
        $this->mergeCasts(['published_at' => 'datetime']);
    }

    /**
     * Only what visitors of the site may see.
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status() === PublicationStatus::Published;
    }

    public function status(): PublicationStatus
    {
        return match (true) {
            $this->published_at === null => PublicationStatus::Draft,
            $this->published_at->isFuture() => PublicationStatus::Scheduled,
            default => PublicationStatus::Published,
        };
    }
}
