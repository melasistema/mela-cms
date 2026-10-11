<?php

namespace Melasistema\MelaCms\Filament\Resources\Media\Pages;

use Melasistema\MelaCms\Filament\Resources\Media\MediaResource;

/**
 * Points a page at the media resource named in the config, so a site that
 * replaces the resource with its own subclass keeps using these pages.
 */
trait ResolvesMediaResource
{
    public static function getResource(): string
    {
        return config('mela-cms.resources.media') ?? MediaResource::class;
    }
}
