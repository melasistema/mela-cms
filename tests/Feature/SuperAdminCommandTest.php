<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Tests\Fixtures\User;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * A server after its first deploy: the migrations have run, and there are no
 * roles and no accounts.
 */
function deployed(): void
{
    test()->install();

    User::query()->delete();
    RoleModel::query()->delete();
}

it('creates the roles and a super-admin on a site that was only migrated', function () {
    deployed();

    $this->artisan('mela:super-admin', [
        '--name' => 'Luca',
        '--email' => 'luca@example.com',
        '--password' => 'correct-horse',
        '--no-interaction' => true,
    ])->expectsOutputToContain('Log in at /admin')->assertSuccessful();

    $user = User::sole();

    expect(RoleModel::pluck('name')->all())->toEqualCanonicalizing(Role::values())
        ->and($user->name)->toBe('Luca')
        ->and(Hash::check('correct-horse', $user->password))->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('asks for the account when run interactively', function () {
    deployed();

    $this->artisan('mela:super-admin')
        ->expectsQuestion('Super-admin email', 'not-an-email')
        ->expectsQuestion('Super-admin email', 'luca@example.com')
        ->expectsQuestion('Super-admin name', 'Luca')
        ->expectsQuestion('Super-admin password (at least 8 characters)', 'short')
        ->expectsQuestion('Super-admin password (at least 8 characters)', 'correct-horse')
        ->assertSuccessful();

    expect(User::sole()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('gives the role to an existing account without asking for a password', function () {
    deployed();

    $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => Hash::make('editor-password')]);

    $this->artisan('mela:super-admin')
        ->expectsQuestion('Super-admin email', 'editor@example.com')
        ->assertSuccessful();

    expect($editor->refresh()->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and($editor->name)->toBe('Editor')
        ->and(Hash::check('editor-password', $editor->password))->toBeTrue();
});

it('adds a second super-admin to a site that has one', function () {
    $this->install();

    $this->artisan('mela:super-admin', [
        '--email' => 'second@example.com',
        '--password' => 'correct-horse',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::role(Role::SuperAdmin->value)->count())->toBe(2);
});

it('fails without an email or a password when there is no interaction', function (array $options) {
    deployed();

    $this->artisan('mela:super-admin', [...$options, '--no-interaction' => true])->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'nothing' => [[]],
    'no password' => [['--email' => 'luca@example.com']],
    'short password' => [['--email' => 'luca@example.com', '--password' => 'short']],
]);

it('fails with a message when the migrations have not run', function () {
    expect(Schema::hasTable('roles'))->toBeFalse();

    $this->artisan('mela:super-admin', ['--email' => 'luca@example.com', '--password' => 'correct-horse'])
        ->expectsOutputToContain('php artisan migrate')
        ->assertFailed();
});
