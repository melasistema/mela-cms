<?php

use Melasistema\MelaCms\Enums\Role;
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
