<?php

use Filament\Panel;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Filament\Pages\ManageLanguages;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Settings;
use Melasistema\MelaCms\Tests\Fixtures\SiteLanguagesPage;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    $this->install(['--locales' => 'en,it']);
});

function languagesUser(Role $role): User
{
    $user = User::create([
        'name' => 'Test',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->assignRole($role->value);

    return $user;
}

it('starts from the languages in the config', function () {
    $languages = app(Languages::class);

    expect($languages->all())->toBe(['en', 'it'])
        ->and($languages->default())->toBe('en')
        ->and($languages->translations())->toBe(['it'])
        ->and($languages->has('it'))->toBeTrue()
        ->and($languages->has('fr'))->toBeFalse()
        ->and($languages->admin())->toBe('en');
});

it('takes the admin language from the config before the app locale', function () {
    config(['mela-cms.admin_locale' => 'it']);

    expect(app(Languages::class)->admin())->toBe('it');
});

it('prefers what was saved over the config', function () {
    app(Languages::class)->update(['en', 'it', 'fr'], 'it', 'fr');

    $languages = app(Languages::class);

    expect($languages->all())->toBe(['it', 'en', 'fr'])
        ->and($languages->default())->toBe('it')
        ->and($languages->translations())->toBe(['en', 'fr'])
        ->and($languages->admin())->toBe('fr');
});

it('falls back to the config while the settings table does not exist', function () {
    Schema::drop('mela_settings');

    expect(app(Settings::class)->all())->toBe([])
        ->and(app(Languages::class)->all())->toBe(['en', 'it']);
});

it('names a language in its own language or in another', function () {
    expect(Languages::name('it'))->toBe('Italiano')
        ->and(Languages::name('it', 'en'))->toBe('Italian');
});

it('offers the languages the panel is translated into', function () {
    expect(app(Languages::class)->availableForAdmin())
        ->toHaveKeys(['en', 'it', 'pt_BR'])
        ->not->toHaveKey('xx');
});

it('shows the languages page to admins and super-admins only', function (Role $role, int $status) {
    $this->actingAs(languagesUser($role))->get('/admin/languages')->assertStatus($status);
})->with([
    [Role::SuperAdmin, 200],
    [Role::Admin, 200],
    [Role::Editor, 403],
]);

it('fills the page with the current languages', function () {
    $this->actingAs(languagesUser(Role::Admin));

    Livewire::test(ManageLanguages::class)
        ->assertFormSet(['locales' => ['en', 'it'], 'default' => 'en', 'admin' => 'en']);
});

it('saves the languages and makes the chosen default the first', function () {
    $this->actingAs(languagesUser(Role::Admin));

    Livewire::test(ManageLanguages::class)
        ->fillForm(['locales' => ['en', 'it', 'de'], 'default' => 'it', 'admin' => 'it'])
        ->call('save')
        ->assertHasNoFormErrors();

    $languages = app(Languages::class);

    expect($languages->all())->toBe(['it', 'en', 'de'])
        ->and($languages->admin())->toBe('it');
});

it('rejects a default language that is not a site language', function () {
    $this->actingAs(languagesUser(Role::Admin));

    Livewire::test(ManageLanguages::class)
        ->fillForm(['locales' => ['en', 'it'], 'default' => 'de'])
        ->call('save')
        ->assertHasFormErrors(['default']);

    expect(app(Languages::class)->all())->toBe(['en', 'it']);
});

it('requires at least one site language', function () {
    $this->actingAs(languagesUser(Role::Admin));

    Livewire::test(ManageLanguages::class)
        ->fillForm(['locales' => []])
        ->call('save')
        ->assertHasFormErrors(['locales']);
});

it('shows the panel in the admin language', function () {
    app(Languages::class)->update(['en'], 'en', 'it');

    $this->actingAs(languagesUser(Role::Admin))->get('/admin/languages')->assertOk();

    expect(app()->getLocale())->toBe('it');
});

it('leaves the language of the site alone', function () {
    app(Languages::class)->update(['en'], 'en', 'it');

    Route::get('/hello', fn () => app()->getLocale());

    $this->get('/hello')->assertSee('en');
});

it('lets a site remove the languages page', function () {
    config(['mela-cms.pages.languages' => null]);

    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel->getPages())->not->toContain(ManageLanguages::class);
});

it('lets a site replace the languages page', function () {
    config(['mela-cms.pages.languages' => SiteLanguagesPage::class]);

    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel->getPages())->toContain(SiteLanguagesPage::class)
        ->not->toContain(ManageLanguages::class);
});
