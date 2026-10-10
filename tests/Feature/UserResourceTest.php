<?php

use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Filament\Resources\Users\Pages\CreateUser;
use Melasistema\MelaCms\Filament\Resources\Users\Pages\EditUser;
use Melasistema\MelaCms\Filament\Resources\Users\Pages\ListUsers;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;
use Melasistema\MelaCms\Tests\Fixtures\SiteUserResource;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    $this->install();
});

function makeUser(?Role $role, string $name = 'Test'): User
{
    $user = User::create([
        'name' => $name,
        'email' => uniqid().'@example.com',
        'password' => Hash::make('password'),
    ]);

    if ($role) {
        $user->assignRole($role->value);
    }

    return $user;
}

it('shows the users screen to super-admins and admins only', function (Role $role, bool $allowed) {
    $response = $this->actingAs(makeUser($role))->get('/admin/users');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    [Role::SuperAdmin, true],
    [Role::Admin, true],
    [Role::Editor, false],
]);

it('lists the users with their role', function () {
    $this->actingAs(makeUser(Role::Admin));
    $editor = makeUser(Role::Editor, 'Edith');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$editor])
        ->assertSee('Edith')
        ->assertSee('Editor');
});

it('creates a user with a hashed password and one role', function () {
    $this->actingAs(makeUser(Role::Admin));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Edith',
            'email' => 'edith@example.com',
            'password' => 'correct-horse',
            'role' => Role::Editor->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'edith@example.com')->sole();

    expect(Hash::check('correct-horse', $user->password))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([Role::Editor->value]);
});

it('requires a role, a password and a unique email on create', function () {
    $this->actingAs($admin = makeUser(Role::Admin));

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Edith', 'email' => $admin->email])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique', 'password' => 'required', 'role' => 'required']);
});

it('changes the role and keeps the password when it is left empty', function () {
    $this->actingAs(makeUser(Role::Admin));
    $user = makeUser(Role::Editor);
    $password = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertFormSet(['role' => Role::Editor->value, 'password' => null])
        ->fillForm(['name' => 'Renamed', 'role' => Role::Admin->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh()->unsetRelation('roles');

    expect($user->name)->toBe('Renamed')
        ->and($user->password)->toBe($password)
        ->and($user->getRoleNames()->all())->toBe([Role::Admin->value]);
});

it('lets only a super-admin give the super-admin role', function () {
    $this->actingAs(makeUser(Role::Admin));

    expect(UserResource::assignableRoles())->not->toHaveKey(Role::SuperAdmin->value);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'correct-horse',
            'role' => Role::SuperAdmin->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::where('email', 'mallory@example.com')->exists())->toBeFalse();

    $this->actingAs(makeUser(Role::SuperAdmin));

    expect(UserResource::assignableRoles())->toHaveKey(Role::SuperAdmin->value);
});

it('keeps admins away from super-admin accounts', function () {
    $admin = makeUser(Role::Admin);
    $superAdmin = makeUser(Role::SuperAdmin);
    $editor = makeUser(Role::Editor);

    expect($admin->can('update', $superAdmin))->toBeFalse()
        ->and($admin->can('delete', $superAdmin))->toBeFalse()
        ->and($admin->can('update', $editor))->toBeTrue()
        ->and($admin->can('delete', $editor))->toBeTrue()
        ->and($editor->can('update', $editor))->toBeFalse();

    $this->actingAs($admin)
        ->get("/admin/users/{$superAdmin->getKey()}/edit")
        ->assertForbidden();
});

it('lets nobody delete or change the role of their own account', function (Role $role) {
    $this->actingAs($user = makeUser($role));

    expect(UserResource::canDelete($user))->toBeFalse()
        ->and(UserResource::canDelete(makeUser(Role::Editor)))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertFormFieldDisabled('role')
        ->fillForm(['name' => 'Still me'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Still me')
        ->and($user->hasRole($role->value))->toBeTrue();
})->with([Role::SuperAdmin, Role::Admin]);

it('deletes a user', function () {
    $this->actingAs(makeUser(Role::Admin));
    $user = makeUser(Role::Editor);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->callAction('delete');

    expect(User::find($user->getKey()))->toBeNull();
});

it('removes the screen when the site switches it off', function () {
    $panel = fn (): Panel => (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel()->getResources())->toContain(UserResource::class);

    config(['mela-cms.resources.users' => null]);

    expect($panel()->getResources())->not->toContain(UserResource::class);
});

it('lets the site replace the resource with its own subclass', function () {
    config(['mela-cms.resources.users' => SiteUserResource::class]);

    $this->actingAs(makeUser(Role::Admin));

    expect(CreateUser::getResource())->toBe(SiteUserResource::class);

    Livewire::test(CreateUser::class)
        ->assertFormFieldExists('phone')
        ->assertFormFieldExists('role');
});

it('lets the site replace the policy', function () {
    Gate::policy(User::class, AllowEditorsPolicy::class);

    $this->actingAs(makeUser(Role::Editor))->get('/admin/users')->assertOk();
});

class AllowEditorsPolicy
{
    public function viewAny(): bool
    {
        return true;
    }
}
