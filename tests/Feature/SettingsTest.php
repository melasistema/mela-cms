<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Settings;
use Melasistema\MelaCms\Tests\Fixtures\SiteSettingsPage;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    $this->install(['--locales' => 'en,it']);
});

function settingsUser(Role $role): User
{
    $user = User::create([
        'name' => 'Test',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->assignRole($role->value);

    return $user;
}

it('reads inside a saved value with a dot', function () {
    $settings = app(Settings::class);

    $settings->set('site', ['contact' => ['email' => 'info@example.com', 'phone' => null]]);

    expect($settings->get('site.contact.email'))->toBe('info@example.com')
        ->and($settings->get('site.contact'))->toBe(['email' => 'info@example.com', 'phone' => null])
        ->and($settings->get('site.contact.phone', 'none'))->toBe('none')
        ->and($settings->get('site.missing.key', 'none'))->toBe('none');
});

it('reads a translated value in the language of the request', function () {
    app(Settings::class)->set('site', ['name' => ['en' => 'My site', 'it' => 'Il mio sito']]);

    Route::get('/name', fn () => app(Settings::class)->translated('site.name'));

    $this->get('/name')->assertSee('My site');
    $this->get('/it/name')->assertSee('Il mio sito');
});

it('falls back to the default language, then to the given default', function () {
    $settings = app(Settings::class);
    $settings->set('site', ['name' => ['en' => 'My site', 'it' => '']]);

    app(Languages::class)->use('it');

    expect($settings->translated('site.name'))->toBe('My site')
        ->and($settings->translated('site.description'))->toBeNull()
        ->and($settings->translated('site.description', 'Nothing yet'))->toBe('Nothing yet');
});

it('does not throw while the settings table does not exist', function () {
    Schema::drop('mela_settings');

    expect(app(Settings::class)->get('site.name'))->toBeNull()
        ->and(app(Settings::class)->translated('site.name', 'Laravel'))->toBe('Laravel');
});

it('shows the settings page to admins and super-admins only', function (Role $role, int $status) {
    $this->actingAs(settingsUser($role))->get('/admin/settings')->assertStatus($status);
})->with([
    [Role::SuperAdmin, 200],
    [Role::Admin, 200],
    [Role::Editor, 403],
]);

it('saves the settings of the site', function () {
    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->fillForm([
            'name' => ['en' => 'My site', 'it' => 'Il mio sito'],
            'description' => ['en' => 'About my site'],
            'contact' => ['email' => 'info@example.com', 'phone' => '+39 06 1234'],
            'social' => ['instagram' => 'https://instagram.com/mysite'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(Settings::class);

    expect($settings->translated('site.name'))->toBe('My site')
        ->and($settings->get('site.name.it'))->toBe('Il mio sito')
        ->and($settings->get('site.contact.email'))->toBe('info@example.com')
        ->and($settings->get('site.contact.phone'))->toBe('+39 06 1234')
        ->and($settings->get('site.social.instagram'))->toBe('https://instagram.com/mysite')
        ->and($settings->get('site.social.facebook'))->toBeNull();
});

it('fills the page with what was saved', function () {
    app(Settings::class)->set('site', [
        'name' => ['en' => 'My site', 'it' => 'Il mio sito'],
        'contact' => ['email' => 'info@example.com'],
    ]);

    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'name' => ['en' => 'My site', 'it' => 'Il mio sito'],
            'contact.email' => 'info@example.com',
        ]);
});

it('rejects an email or a profile address that is not one', function () {
    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->fillForm(['contact' => ['email' => 'nope'], 'social' => ['facebook' => 'nope']])
        ->call('save')
        ->assertHasFormErrors(['contact.email', 'social.facebook']);

    expect(app(Settings::class)->get('site'))->toBeNull();
});

it('leaves the languages alone', function () {
    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->fillForm(['name' => ['en' => 'My site']])
        ->call('save');

    expect(app(Settings::class)->get('locales'))->toBeNull()
        ->and(app(Languages::class)->all())->toBe(['en', 'it']);
});

it('keeps the translation of a language that was removed', function () {
    app(Settings::class)->set('site', ['name' => ['en' => 'My site', 'it' => 'Il mio sito']]);
    app(Languages::class)->update(['en'], 'en', 'en');

    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->fillForm(['name' => ['en' => 'Our site']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->get('site.name'))->toBe(['en' => 'Our site', 'it' => 'Il mio sito']);
});

it('clears a field that was emptied', function () {
    app(Settings::class)->set('site', ['contact' => ['email' => 'info@example.com']]);

    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->fillForm(['contact' => ['email' => null]])
        ->call('save');

    expect(app(Settings::class)->get('site.contact.email'))->toBeNull();
});

it('names the panel after the site', function () {
    expect(Filament::getPanel('admin')->getBrandName())->toBe(config('app.name'));

    app(Settings::class)->set('site', ['name' => ['en' => 'My site']]);

    expect(Filament::getPanel('admin')->getBrandName())->toBe('My site');
});

it('saves the fields a site adds to the page', function () {
    $this->actingAs(settingsUser(Role::Admin));

    Livewire::test(SiteSettingsPage::class)
        ->fillForm(['name' => ['en' => 'My site'], 'booking_url' => 'https://example.com/book'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->get('site.booking_url'))->toBe('https://example.com/book')
        ->and(app(Settings::class)->get('site.name.en'))->toBe('My site');
});

it('lets a site remove or replace the settings page', function () {
    config(['mela-cms.pages.settings' => null]);

    expect((new AdminPanelProvider(app()))->panel(Panel::make())->getPages())
        ->not->toContain(ManageSettings::class);

    config(['mela-cms.pages.settings' => SiteSettingsPage::class]);

    expect((new AdminPanelProvider(app()))->panel(Panel::make())->getPages())
        ->toContain(SiteSettingsPage::class)
        ->not->toContain(ManageSettings::class);
});
