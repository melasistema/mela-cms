<?php

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

    $this->get('/admin/login')->assertOk()->assertSee('https://example.com/logo.svg');
});
