<?php

namespace Melasistema\MelaCms\Console;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Melasistema\MelaCms\Concerns\HasCmsAccess;
use Melasistema\MelaCms\Install\AgentGuide;
use Melasistema\MelaCms\Install\ComposerScripts;
use Melasistema\MelaCms\Install\Project;
use Melasistema\MelaCms\Install\Roles;
use Melasistema\MelaCms\Install\StubPublisher;
use Melasistema\MelaCms\Install\StubStatus;
use Melasistema\MelaCms\Install\UserModelPatcher;
use ReflectionClass;
use Spatie\Permission\PermissionServiceProvider;

class InstallCommand extends Command
{
    protected $signature = 'mela:install
        {--name= : Name of the super-admin}
        {--email= : Email of the super-admin}
        {--password= : Password of the super-admin}
        {--locales= : Comma-separated site locales, e.g. en,it}
        {--force : Replace project files that differ from the core templates}';

    protected $description = 'Set up MelaCMS in this project: database, roles, super-admin, admin panel and workflows';

    public function __construct(
        private readonly Project $project,
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    /**
     * Safe to run again: every step skips what is already in place.
     */
    public function handle(
        UserModelPatcher $patcher,
        Roles $roles,
        ComposerScripts $composerScripts,
        StubPublisher $stubs,
        AgentGuide $agentGuide,
    ): int {
        if (! $this->writeConfig()) {
            return self::FAILURE;
        }

        $this->publishPermissionMigration();
        $this->patchUserModel($patcher);

        $this->call('migrate');
        $roles->sync();

        if (! $this->createSuperAdmin($roles)) {
            return self::FAILURE;
        }

        $this->callSilently('filament:assets');

        if (! $composerScripts->ensureFilamentUpgrade()) {
            $this->components->warn('Could not update composer.json. Add "'.ComposerScripts::SCRIPT.'" to its post-autoload-dump scripts.');
        }

        $this->publishStubs($stubs);

        foreach ($agentGuide->sync() as $file => $result) {
            $this->components->twoColumnDetail($file, "MelaCMS section {$result}");
        }

        $this->newLine();
        $this->components->info('MelaCMS is installed. Log in at /'.config('mela-cms.admin_path').'.');
        $this->components->bulletList([
            'The workflows expect the branches "development" (work) and "master" (live).',
            'To release from GitHub, enable "Allow GitHub Actions to create and approve pull requests" in the repo settings.',
            'To deploy, set the variables and secrets listed at the top of .github/workflows/deploy.yml.',
        ]);

        return self::SUCCESS;
    }

    private function writeConfig(): bool
    {
        $path = $this->project->path('config/mela-cms.php');

        if (! $this->files->exists($path)) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->copy(__DIR__.'/../../config/mela-cms.php', $path);
        }

        $locales = $this->option('locales');

        if ($locales === null && $this->input->isInteractive()) {
            $locales = $this->ask('Site locales (comma separated)', implode(',', config('mela-cms.locales')));
        }

        if ($locales === null) {
            return true;
        }

        $locales = array_values(array_unique(array_filter(array_map(trim(...), explode(',', $locales)))));

        foreach ($locales as $locale) {
            if (! preg_match('/^[a-z]{2,3}([_-][A-Za-z]{2,4})?$/', $locale)) {
                $this->components->error("\"{$locale}\" is not a valid locale. Use codes such as en, it or pt_BR.");

                return false;
            }
        }

        if ($locales === []) {
            $this->components->error('At least one locale is required.');

            return false;
        }

        $export = "'locales' => ['".implode("', '", $locales)."']";
        $config = preg_replace("/'locales'\s*=>\s*\[[^\]]*\]/", $export, $this->files->get($path), 1, $count);

        if ($count === 1) {
            $this->files->put($path, $config);
            config(['mela-cms.locales' => $locales]);
        } else {
            $this->components->warn("Could not find the locales entry in config/mela-cms.php. Set it by hand: {$export}");
        }

        return true;
    }

    /**
     * Spatie ships its tables as a migration to publish into the project.
     */
    private function publishPermissionMigration(): void
    {
        $directory = $this->project->path('database/migrations');

        if ($this->files->glob($directory.'/*_create_permission_tables.php') !== []) {
            return;
        }

        $package = dirname((new ReflectionClass(PermissionServiceProvider::class))->getFileName(), 2);

        $this->files->ensureDirectoryExists($directory);
        $this->files->copy(
            $package.'/database/migrations/create_permission_tables.php.stub',
            $directory.'/'.date('Y_m_d_His').'_create_permission_tables.php',
        );
    }

    private function patchUserModel(UserModelPatcher $patcher): void
    {
        $model = $this->userModel();

        if (is_subclass_of($model, FilamentUser::class) && in_array(HasCmsAccess::class, class_uses_recursive($model))) {
            return;
        }

        $manual = "Add `implements FilamentUser` and `use HasCmsAccess;` to {$model} by hand, or nobody can log in to the admin.";
        $path = (new ReflectionClass($model))->getFileName();

        // Never edit a model that lives outside the project, e.g. in vendor/.
        if ($path === false || ! str_starts_with($path, $this->project->path('app'))) {
            $this->components->warn($manual);

            return;
        }

        $patched = $patcher->patch($this->files->get($path));

        if ($patched === null) {
            $this->components->warn($manual);

            return;
        }

        $this->files->put($path, $patched);

        // Best effort: the model may have traits or imports the patch sorted differently than Pint does.
        $pint = $this->project->path('vendor/bin/pint');

        if ($this->files->exists($pint)) {
            Process::path($this->project->path())->run([PHP_BINARY, $pint, $path]);
        }
    }

    private function createSuperAdmin(Roles $roles): bool
    {
        $email = $this->option('email');
        $password = $this->option('password');

        if ($email === null && $roles->hasSuperAdmin()) {
            return true;
        }

        if ($this->input->isInteractive()) {
            $name = $this->option('name') ?? $this->ask('Super-admin name', 'Super Admin');

            while (! $this->validEmail($email)) {
                $email = $this->ask('Super-admin email');
            }

            while (! $this->validPassword($password)) {
                $password = $this->secret('Super-admin password (at least 8 characters)');
            }
        } else {
            $name = $this->option('name') ?? 'Super Admin';
        }

        if (! $this->validEmail($email) || ! $this->validPassword($password)) {
            $this->components->error('Pass a valid --email and a --password of at least 8 characters when running without interaction.');

            return false;
        }

        $model = $this->userModel();
        $user = $model::query()->where('email', $email)->first();

        // An existing account keeps its password and only gains the role.
        if ($user === null) {
            $user = new $model;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ])->save();
        }

        $roles->makeSuperAdmin($user);

        return true;
    }

    private function publishStubs(StubPublisher $stubs): void
    {
        foreach ($stubs->statuses() as $destination => $status) {
            if ($status === StubStatus::Current) {
                $this->components->twoColumnDetail($destination, 'up to date');

                continue;
            }

            if ($status === StubStatus::Differs && ! $this->shouldReplace($destination)) {
                $this->components->twoColumnDetail($destination, 'kept (differs from the core template)');

                continue;
            }

            $stubs->publish($destination);
            $this->components->twoColumnDetail($destination, $status === StubStatus::Missing ? 'created' : 'replaced');
        }
    }

    private function shouldReplace(string $destination): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->input->isInteractive()
            && $this->confirm("{$destination} differs from the core template. Replace it?", false);
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
