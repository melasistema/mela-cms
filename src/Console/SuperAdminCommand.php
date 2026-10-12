<?php

namespace Melasistema\MelaCms\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Melasistema\MelaCms\Install\Roles;

class SuperAdminCommand extends Command
{
    protected $signature = 'mela:super-admin
        {--name= : Name of a new account}
        {--email= : Email of the account}
        {--password= : Password of a new account}';

    protected $description = 'Create a super-admin account, or give the role to an existing account';

    /**
     * Changes the database only, so it is the command to run on a server:
     * after the first deploy nobody can log in until an account has the role.
     */
    public function handle(Roles $roles): int
    {
        if (! Schema::hasTable(config('permission.table_names.roles'))) {
            $this->components->error('The roles table is missing. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $roles->sync();

        $email = $this->option('email');

        while ($this->input->isInteractive() && ! $this->validEmail($email)) {
            $email = $this->ask('Super-admin email');
        }

        if (! $this->validEmail($email)) {
            $this->components->error('Pass a valid --email when running without interaction.');

            return self::FAILURE;
        }

        $model = $this->userModel();

        // An existing account keeps its name and password and only gains the role.
        $user = $model::query()->where('email', $email)->first() ?? $this->createUser($model, $email);

        if ($user === null) {
            return self::FAILURE;
        }

        $roles->makeSuperAdmin($user);

        $this->components->info("{$email} is a super-admin. Log in at /".config('mela-cms.admin_path').'.');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function createUser(string $model, string $email): ?Model
    {
        $name = $this->option('name');
        $password = $this->option('password');

        if ($this->input->isInteractive()) {
            $name ??= $this->ask('Super-admin name', 'Super Admin');

            while (! $this->validPassword($password)) {
                $password = $this->secret('Super-admin password (at least 8 characters)');
            }
        }

        if (! $this->validPassword($password)) {
            $this->components->error('Pass a --password of at least 8 characters when running without interaction.');

            return null;
        }

        $user = new $model;
        $user->forceFill([
            'name' => $name ?? 'Super Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function validEmail(?string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validPassword(?string $password): bool
    {
        return $password !== null && mb_strlen($password) >= 8;
    }

    /**
     * @return class-string<Model>
     */
    private function userModel(): string
    {
        return config('auth.providers.users.model');
    }
}
