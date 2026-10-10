<?php

use Melasistema\MelaCms\MelaCmsServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProviders(MelaCmsServiceProvider::class))->not->toBeEmpty();
});

it('merges the default config', function () {
    expect(config('mela-cms.locales'))->toBe(['en'])
        ->and(config('mela-cms.admin_path'))->toBe('admin');
});
