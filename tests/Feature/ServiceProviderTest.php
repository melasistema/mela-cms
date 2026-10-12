<?php

use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;
use Melasistema\MelaCms\MelaCmsServiceProvider;
use Melasistema\MelaCms\Models\Page;

it('registers the service provider', function () {
    expect(app()->getProviders(MelaCmsServiceProvider::class))->not->toBeEmpty();
});

it('merges the default config', function () {
    expect(config('mela-cms.locales'))->toBe(['en'])
        ->and(config('mela-cms.admin_path'))->toBe('admin');
});

it('gives the keys missing from a stale config cache their defaults', function () {
    // A site that updated the core while bootstrap/cache/config.php still
    // holds the config of a version without pages, media library and sitemap.
    // Laravel merges no package config while a cache is in place.
    app()->instance('config_loaded_from_cache', true);

    config(['mela-cms' => [
        'locales' => ['en', 'it'],
        'admin_locale' => null,
        'admin_path' => 'cms',
        'resources' => ['users' => UserResource::class],
    ]]);

    expect(app()->configurationIsCached())->toBeTrue();

    (new MelaCmsServiceProvider(app()))->register();

    expect(config('mela-cms.resources.pages'))->toBe(PageResource::class)
        ->and(config('mela-cms.pages.settings'))->toBe(ManageSettings::class)
        ->and(config('mela-cms.media.disk'))->toBe('public')
        ->and(config('mela-cms.sitemap'))->toBe([Page::class])
        ->and(config('mela-cms.public_pages'))->toBeTrue()
        ->and(config('mela-cms.locale_urls'))->toBe('path');

    // What the site set is kept.
    expect(config('mela-cms.locales'))->toBe(['en', 'it'])
        ->and(config('mela-cms.admin_path'))->toBe('cms')
        ->and(config('mela-cms.resources.users'))->toBe(UserResource::class);
});
