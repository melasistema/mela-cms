<?php

use Filament\Panel;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Filament\Pages\EditProfile;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    $this->install();
});

function profileUser(Role $role): User
{
    $user = User::create([
        'name' => 'Test',
        'email' => uniqid().'@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $user->assignRole($role->value);

    return $user;
}

it('gives every role a profile page', function (Role $role) {
    $this->actingAs(profileUser($role))->get('/admin/profile')->assertOk();
})->with(Role::cases());

it('lets a user change their own name', function () {
    $this->actingAs($user = profileUser(Role::Editor));

    Livewire::test(EditProfile::class)
        ->assertFormSet(['name' => 'Test', 'email' => $user->email])
        ->fillForm(['name' => 'Edith'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Edith')
        ->and(Hash::check('old-password', $user->password))->toBeTrue();
});

it('never sends the stored password hash to the form', function () {
    $this->actingAs(profileUser(Role::Editor)->makeVisible('password'));

    Livewire::test(EditProfile::class)->assertFormSet(['password' => null]);
});

it('asks for the current password before changing the password', function () {
    $this->actingAs($user = profileUser(Role::Editor));

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
            'currentPassword' => 'wrong-password',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
            'currentPassword' => 'old-password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

it('asks for the current password before changing the email', function () {
    $this->actingAs($user = profileUser(Role::Editor));

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'new@example.com'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'new@example.com', 'currentPassword' => 'old-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->email)->toBe('new@example.com');
});

it('removes the page when the site switches it off', function () {
    $panel = fn (): Panel => (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel()->hasProfile())->toBeTrue();

    config(['mela-cms.profile_page' => null]);

    expect($panel()->hasProfile())->toBeFalse();
});
