<?php

use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\PanelRegistry;
use Filament\Support\Colors\Color;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;
use Melasistema\MelaCms\MelaCmsServiceProvider;
use Melasistema\MelaCms\Tests\Fixtures\SitePanelProvider;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    $this->install();
});

function userWithRole(?Role $role): User
{
    $user = User::create([
        'name' => 'Test',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);

    if ($role) {
        $user->assignRole($role->value);
    }

    return $user;
}

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('lets every core role into the panel', function (Role $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
})->with(Role::cases());

it('keeps users without a role out of the panel', function () {
    $this->actingAs(userWithRole(null))->get('/admin')->assertForbidden();
});

it('lets a super-admin pass any authorization check', function () {
    expect(userWithRole(Role::SuperAdmin)->can('anything-at-all'))->toBeTrue()
        ->and(userWithRole(Role::Admin)->can('anything-at-all'))->toBeFalse();
});

it('builds the panel with the provider the site names in the config', function () {
    config(['mela-cms.panel_provider' => SitePanelProvider::class]);

    // The panels are built when the registry is first resolved: drop it so the
    // providers registered again build them again, as a site boot would.
    app()->forgetInstance(PanelRegistry::class);
    (new MelaCmsServiceProvider(app()))->register();

    $panel = Filament::getPanel('admin');

    expect(app()->getProviders(SitePanelProvider::class))->not->toBeEmpty()
        ->and($panel->getColors()['primary'])->toBe(Color::Emerald)
        ->and($panel->getBrandLogo())->toBe('https://example.com/logo.svg')
        // What the core sets is kept.
        ->and($panel->getPath())->toBe('admin')
        ->and($panel->getResources())->toContain(PageResource::class);

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('https://example.com/logo.svg')
        ->assertDontSee('mela-login-mark')
        ->assertDontSee('Built with');
});

it('shows the MelaCMS mark on the login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('id="mela-login-mark-ring"', escape: false);
});

it('uses the MelaCMS palette', function () {
    $colors = Filament::getPanel('admin')->getColors();

    expect($colors['primary'])->toBe(Color::hex('#1d6fa5'))
        ->and($colors['gray'])->toBe(Color::Slate);
});

it('lets every user pick light, dark or the system theme', function () {
    expect(Filament::getPanel('admin')->getDefaultThemeMode())->toBe(ThemeMode::System);

    $this->actingAs(userWithRole(Role::Editor))
        ->get('/admin')
        ->assertOk()
        ->assertSee('fi-theme-switcher')
        ->assertSee("theme === 'light'", escape: false)
        ->assertSee("theme === 'dark'", escape: false)
        ->assertSee("theme === 'system'", escape: false);
});

it('shows the MelaCMS favicon', function () {
    $svg = file_get_contents(__DIR__.'/../../resources/images/favicon.svg');

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('<link rel="icon" href="data:image/svg+xml;base64,'.base64_encode($svg).'"', escape: false);
});

it('signs every admin page with MelaCMS and its version', function () {
    $path = $this->projectFile('vendor/composer/installed.json');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, json_encode(['packages' => [['name' => 'melasistema/mela-cms', 'version' => 'v0.8.1']]]));

    $this->actingAs(userWithRole(Role::Editor))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Built with')
        ->assertSee('Version 0.8.1');
});

it('keeps the version off the login page', function () {
    $path = $this->projectFile('vendor/composer/installed.json');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, json_encode(['packages' => [['name' => 'melasistema/mela-cms', 'version' => 'v0.8.1']]]));

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Built with')
        ->assertDontSee('0.8.1');
});
