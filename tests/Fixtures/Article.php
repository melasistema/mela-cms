<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Stands in for a content model of a client site.
 */
class Article extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title', 'body'];
}
