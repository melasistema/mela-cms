<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Install\Project;
use Spatie\Permission\Models\Role as RoleModel;

it('reports when everything matches the core', function () {
    $this->install();

    $this->artisan('mela:update')
        ->expectsOutputToContain('All project templates match the core')
        ->assertSuccessful();
});

it('reports project files that differ, without touching them', function () {
    $this->install();
    file_put_contents($this->projectFile('.github/workflows/ci.yml'), 'mine');
    unlink($this->projectFile('.github/dependabot.yml'));

    $this->artisan('mela:update')
        ->expectsOutputToContain('.github/workflows/ci.yml')
        ->expectsOutputToContain('.github/dependabot.yml')
        ->assertSuccessful();

    expect(file_get_contents($this->projectFile('.github/workflows/ci.yml')))->toBe('mine')
        ->and($this->projectFile('.github/dependabot.yml'))->not->toBeFile();
});

it('restores a core role that is missing', function () {
    $this->install();
    RoleModel::findByName(Role::Editor->value)->delete();

    $this->artisan('mela:update')->assertSuccessful();

    expect(RoleModel::where('name', Role::Editor->value)->exists())->toBeTrue();
});

it('refreshes only the MelaCMS section of the agent guides', function () {
    $this->install();
    file_put_contents(
        $this->projectFile('AGENTS.md'),
        "# Mine\n\n<mela-cms-guidelines>\nold\n</mela-cms-guidelines>\n\nAlso mine\n",
    );

    $this->artisan('mela:update')->assertSuccessful();

    expect(file_get_contents($this->projectFile('AGENTS.md')))
        ->toStartWith("# Mine\n\n<mela-cms-guidelines>\n## MelaCMS")
        ->toEndWith("</mela-cms-guidelines>\n\nAlso mine\n")
        ->not->toContain("\nold\n");
});

it('passes the check when the agent guides match the core', function () {
    $this->install();

    $this->artisan('mela:update', ['--check' => true])
        ->expectsOutputToContain('The agent guides match the installed MelaCMS core')
        ->assertSuccessful();
});

it('fails the check when an agent guide is behind the core, and changes nothing', function () {
    $this->install();
    $guide = "# Mine\n\n<mela-cms-guidelines>\nold\n</mela-cms-guidelines>\n";
    file_put_contents($this->projectFile('AGENTS.md'), $guide);
    file_put_contents($this->projectFile('.github/workflows/ci.yml'), 'mine');
    RoleModel::findByName(Role::Editor->value)->delete();

    $this->artisan('mela:update', ['--check' => true])
        ->expectsOutputToContain('AGENTS.md.')
        ->expectsOutputToContain('and commit the result')
        ->assertFailed();

    // A template the site changed is not a reason to fail, and nothing is repaired.
    expect(file_get_contents($this->projectFile('AGENTS.md')))->toBe($guide)
        ->and(RoleModel::where('name', Role::Editor->value)->exists())->toBeFalse();
});

it('fails the check when an agent guide is missing', function () {
    $this->install();
    unlink($this->projectFile('CLAUDE.md'));

    $this->artisan('mela:update', ['--check' => true])
        ->expectsOutputToContain('CLAUDE.md.')
        ->assertFailed();

    expect($this->projectFile('CLAUDE.md'))->not->toBeFile();
});

it('ships a CI that checks the agent guides', function () {
    $this->install();

    expect(file_get_contents($this->projectFile('.github/workflows/ci.yml')))
        ->toContain('run: php artisan mela:update --check');
});

function installedCore(string $version): void
{
    $path = test()->projectFile('vendor/composer/installed.json');

    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, json_encode(['packages' => [
        ['name' => 'filament/filament', 'version' => 'v5.0.0'],
        ['name' => 'melasistema/mela-cms', 'version' => $version],
    ]]));
}

function fakeComposer(string $latest, bool $requireSucceeds = true): void
{
    Process::fake([
        '*show*' => Process::result(json_encode(['name' => 'melasistema/mela-cms', 'latest' => $latest])),
        '*require*' => function () use ($latest, $requireSucceeds) {
            if (! $requireSucceeds) {
                return Process::result(errorOutput: 'Your requirements could not be resolved.', exitCode: 2);
            }

            installedCore($latest);

            return Process::result();
        },
        '*mela:update*' => Process::result(),
    ]);
}

it('updates the core to the newest release and applies it with the new code', function () {
    $this->install();
    installedCore('v0.5.1');
    fakeComposer('v0.5.3');
    $guide = "<mela-cms-guidelines>\nold\n</mela-cms-guidelines>\n";
    file_put_contents($this->projectFile('AGENTS.md'), $guide);

    $this->artisan('mela:update')
        ->expectsOutputToContain('Updating MelaCMS 0.5.1 → 0.5.3')
        ->expectsOutputToContain('qualify the publication date column in the published scope')
        ->expectsOutputToContain('fix: update MelaCMS to 0.5.3')
        ->doesntExpectOutputToContain('skip the sizes of an image')
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => in_array('melasistema/mela-cms:^0.5', $process->command, true)
        && in_array('--with-all-dependencies', $process->command, true));
    Process::assertRan(fn (PendingProcess $process) => in_array('mela:update', $process->command, true)
        && in_array('--installed', $process->command, true)
        && $process->path === app(Project::class)->path());

    // The steps are left to the new core: this process has the old classes.
    expect(file_get_contents($this->projectFile('AGENTS.md')))->toBe($guide);
});

it('raises the constraint for a new minor version below 1.0', function () {
    $this->install();
    installedCore('v0.5.3');
    fakeComposer('v0.6.0');

    $this->artisan('mela:update')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => in_array('melasistema/mela-cms:^0.6', $process->command, true));
});

it('applies the installed core when it is the newest release', function () {
    $this->install();
    installedCore('v0.5.3');
    fakeComposer('v0.5.3');
    file_put_contents($this->projectFile('AGENTS.md'), "<mela-cms-guidelines>\nold\n</mela-cms-guidelines>\n");

    $this->artisan('mela:update')
        ->expectsOutputToContain('MelaCMS 0.5.3 is the newest version')
        ->expectsOutputToContain('All project templates match the core')
        ->assertSuccessful();

    Process::assertDidntRun(fn (PendingProcess $process) => in_array('require', $process->command, true));
    expect(file_get_contents($this->projectFile('AGENTS.md')))->not->toContain("\nold\n");
});

it('applies the installed core when Composer cannot look for a new release', function () {
    $this->install();
    installedCore('v0.5.3');
    Process::fake(['*' => Process::result(errorOutput: 'Could not authenticate against github.com', exitCode: 1)]);

    $this->artisan('mela:update')
        ->expectsOutputToContain('Could not authenticate against github.com')
        ->expectsOutputToContain('All project templates match the core')
        ->assertSuccessful();
});

it('stops when Composer cannot update the core', function () {
    $this->install();
    installedCore('v0.5.3');
    fakeComposer('v0.6.0', requireSucceeds: false);

    $this->artisan('mela:update')
        ->expectsOutputToContain('Your requirements could not be resolved.')
        ->expectsOutputToContain('Composer did not update MelaCMS')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process) => in_array('mela:update', $process->command, true));
});

it('reports a failure of the new core to apply', function () {
    $this->install();
    installedCore('v0.5.3');
    Process::fake([
        '*show*' => Process::result(json_encode(['latest' => 'v0.5.4'])),
        '*require*' => function () {
            installedCore('v0.5.4');

            return Process::result();
        },
        '*mela:update*' => Process::result(exitCode: 1),
    ]);

    $this->artisan('mela:update')
        ->expectsOutputToContain('MelaCMS 0.5.4 is installed, but applying it failed')
        ->assertFailed();
});

it('leaves Composer alone', function (Closure $setUp, array $options) {
    $this->install();
    Process::fake();
    $setUp();

    $this->artisan('mela:update', $options)
        ->when(app()->isProduction(), fn ($command) => $command->expectsConfirmation('Are you sure you want to run this command?', 'yes'))
        ->expectsOutputToContain('All project templates match the core')
        ->assertSuccessful();

    Process::assertNothingRan();
})->with([
    'with --installed' => [fn () => installedCore('v0.5.1'), ['--installed' => true]],
    'for a core that is not a release' => [fn () => installedCore('dev-master'), []],
    'without a Composer install' => [fn () => null, []],
    'on a production server' => [function () {
        installedCore('v0.5.1');
        app()->detectEnvironment(fn () => 'production');
    }, []],
]);
