<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Melasistema\MelaCms\Concerns\HasSlug;
use Melasistema\MelaCms\Concerns\Publishable;
use Spatie\Translatable\HasTranslations;

/**
 * Stands in for an entity of a client site with its own address: a blog post.
 */
class Post extends Model
{
    use HasSlug;
    use HasTranslations;
    use Publishable;

    protected $guarded = [];

    public array $translatable = ['title', 'slug'];
}
