<?php

namespace Melasistema\MelaCms\Filament\Resources\Users\Pages;

use Melasistema\MelaCms\Filament\Resources\Users\UserResource;

/**
 * Points a page at the users resource named in the config, so a site that
 * replaces the resource with its own subclass keeps using these pages.
 */
trait ResolvesUserResource
{
    public static function getResource(): string
    {
        return config('mela-cms.resources.users') ?? UserResource::class;
    }
}
