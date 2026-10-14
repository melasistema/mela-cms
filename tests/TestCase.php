<?php

namespace Melasistema\MelaCms\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\LivewireServiceProvider;
use Melasistema\MelaCms\Install\Project;
use Melasistema\MelaCms\MelaCmsServiceProvider;
use Melasistema\MelaCms\Tests\Fixtures\User;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Translatable\TranslatableServiceProvider;

#[WithMigration]
abstract class TestCase extends Orchestra
{
    /**
     * A throwaway directory standing in for the client project, so the
     * commands never write into the Testbench skeleton in vendor/.
     */
    protected string $projectPath;

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->projectPath);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        // In a real app these are auto-discovered; Testbench needs them listed.
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            PowerJoinsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            PermissionServiceProvider::class,
            TranslatableServiceProvider::class,
            MelaCmsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->projectPath = sys_get_temp_dir().'/mela-cms-'.bin2hex(random_bytes(6));

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->projectPath.'/database/migrations');
        $files->ensureDirectoryExists($this->projectPath.'/public');
        $files->put($this->projectPath.'/composer.json', json_encode([
            'require' => new \stdClass,
            'scripts' => ['post-autoload-dump' => ['@php artisan package:discover --ansi']],
        ]));

        $app->instance(Project::class, new Project($this->projectPath));
        $app->useDatabasePath($this->projectPath.'/database');
        $app->usePublicPath($this->projectPath.'/public');

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * Each test starts from an empty database. An in-memory SQLite one is
     * new for every test; a MySQL one, as in CI, keeps the tables of the
     * test before.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::dropAllTables();
    }

    protected function projectFile(string $path): string
    {
        return $this->projectPath.'/'.$path;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function install(array $options = []): void
    {
        $this->artisan('mela:install', [
            '--email' => 'luca@example.com',
            '--password' => 'correct-horse',
            '--no-interaction' => true,
            ...$options,
        ])->assertSuccessful();
    }
}
