<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;
use Melasistema\MelaCms\MelaCmsServiceProvider;
use Melasistema\MelaCms\Models\Page;
use Spatie\Permission\Models\Role as RoleModel;

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

it('creates the core roles after a migration run, as a deploy does', function () {
    $this->install();

    $this->artisan('migrate:fresh')->assertSuccessful();

    expect(RoleModel::pluck('name')->all())->toEqualCanonicalizing(Role::values());
});

it('creates a missing core role when there is nothing to migrate', function () {
    $this->install();

    RoleModel::findByName(Role::Editor->value)->delete();

    // A core update that adds a role and no migration.
    $this->artisan('migrate')->assertSuccessful();

    expect(RoleModel::pluck('name')->all())->toEqualCanonicalizing(Role::values());
});

it('creates no roles when a migration run is only shown or rolled back', function () {
    $this->install();

    RoleModel::query()->delete();

    // One migration to run again, so there is something to show.
    DB::table('migrations')->where('migration', 'like', '%_create_mela_settings_table')->delete();

    $this->artisan('migrate', ['--pretend' => true])->assertSuccessful();
    $this->artisan('migrate:rollback', ['--pretend' => true])->assertSuccessful();

    expect(RoleModel::count())->toBe(0);
});

it('migrates a site that does not have the roles table yet', function () {
    // #[WithMigration] has run the migrations without the Spatie one.
    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable(config('permission.table_names.roles')))->toBeFalse();
});
