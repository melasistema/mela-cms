<?php

use Melasistema\MelaCms\Enums\Role;
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
