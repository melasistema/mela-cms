<?php

namespace Melasistema\MelaCms\Console;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Melasistema\MelaCms\Concerns\HasCmsAccess;
use Melasistema\MelaCms\Console\Concerns\ReviewsTemplates;
use Melasistema\MelaCms\Install\AgentGuide;
use Melasistema\MelaCms\Install\ComposerScripts;
use Melasistema\MelaCms\Install\Project;
use Melasistema\MelaCms\Install\Roles;
use Melasistema\MelaCms\Install\StorageLink;
use Melasistema\MelaCms\Install\StubPublisher;
use Melasistema\MelaCms\Install\UserModelPatcher;
use Melasistema\MelaCms\Install\WelcomeRoute;
use ReflectionClass;
use Spatie\Permission\PermissionServiceProvider;

class InstallCommand extends Command
{
    use ReviewsTemplates;

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
        StorageLink $storageLink,
        WelcomeRoute $welcomeRoute,
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

        if (! $storageLink->ensure()) {
            $this->components->warn('Could not link public/storage. Run `php artisan storage:link`, or uploaded files will not show.');
        }

        match ($welcomeRoute->remove()) {
            WelcomeRoute::REMOVED => $this->components->twoColumnDetail('routes/web.php', 'Welcome route removed, / shows the home page'),
            WelcomeRoute::KEPT => $this->components->twoColumnDetail('routes/web.php', 'Own route for / kept, it is shown instead of the home page'),
            default => null,
        };

        $this->reviewTemplates($stubs, createMissing: true);

        foreach ($agentGuide->sync() as $file => $result) {
            $this->components->twoColumnDetail($file, "MelaCMS section {$result}");
        }

        $this->newLine();
        $this->components->info('MelaCMS is installed. Log in at /'.config('mela-cms.admin_path').'.');
        $this->components->bulletList([
            'The workflows expect the branches "development" (work) and "master" (live).',
            'To release from GitHub, enable "Allow GitHub Actions to create and approve pull requests" in the repo settings.',
            'To deploy, set the variables and secrets listed at the top of .github/workflows/deploy.yml.',
            'After the first deploy, run `php artisan mela:super-admin` on the server to create the account that logs in there.',
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

    /**
     * Skipped when the site has a super-admin and no other account is named.
     */
    private function createSuperAdmin(Roles $roles): bool
    {
        if ($this->option('email') === null && $roles->hasSuperAdmin()) {
            return true;
        }

        return $this->call('mela:super-admin', array_filter([
            '--name' => $this->option('name'),
            '--email' => $this->option('email'),
            '--password' => $this->option('password'),
        ], fn (?string $value) => $value !== null)) === self::SUCCESS;
    }

    /**
     * @return class-string<Model>
     */
    private function userModel(): string
    {
        return config('auth.providers.users.model');
    }
}
