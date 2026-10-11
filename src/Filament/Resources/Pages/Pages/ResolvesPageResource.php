<?php

namespace Melasistema\MelaCms\Filament\Resources\Pages\Pages;

use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;

/**
 * Points a page at the pages resource named in the config, so a site that
 * replaces the resource with its own subclass keeps using these pages.
 */
trait ResolvesPageResource
{
    public static function getResource(): string
    {
        return config('mela-cms.resources.pages') ?? PageResource::class;
    }
}
