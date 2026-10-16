<?php

use Illuminate\Support\Facades\Hash;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Install\ComposerScripts;
use Melasistema\MelaCms\Install\StubPublisher;
use Melasistema\MelaCms\Tests\Fixtures\User;
use Spatie\Permission\Models\Role as RoleModel;

it('creates the core roles and a super-admin', function () {
    $this->install(['--name' => 'Luca']);

    expect(RoleModel::pluck('name')->all())->toEqualCanonicalizing(Role::values());

    $user = User::sole();

    expect($user->name)->toBe('Luca')
        ->and($user->email)->toBe('luca@example.com')
        ->and(Hash::check('correct-horse', $user->password))->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('asks for the super-admin when run interactively', function () {
    $this->artisan('mela:install')
        ->expectsQuestion('Site locales (comma separated)', 'en,it')
        ->expectsQuestion('Super-admin email', 'not-an-email')
        ->expectsQuestion('Super-admin email', 'luca@example.com')
        ->expectsQuestion('Super-admin name', 'Luca')
        ->expectsQuestion('Super-admin password (at least 8 characters)', 'short')
        ->expectsQuestion('Super-admin password (at least 8 characters)', 'correct-horse')
        ->assertSuccessful();

    expect(User::sole()->email)->toBe('luca@example.com')
        ->and(config('mela-cms.locales'))->toBe(['en', 'it']);
});

it('fails without credentials when there is no interaction', function () {
    $this->artisan('mela:install', ['--no-interaction' => true])->assertFailed();

    expect(User::count())->toBe(0);
});

it('writes the locales into the project config', function () {
    $this->install(['--locales' => 'en, it']);

    $config = require $this->projectFile('config/mela-cms.php');

    expect($config['locales'])->toBe(['en', 'it'])
        ->and($config['admin_path'])->toBe('admin');
});

it('rejects an invalid locale', function () {
    $this->artisan('mela:install', ['--locales' => 'english!', '--no-interaction' => true])->assertFailed();
});

it('copies the templates into the project', function () {
    $this->install();

    foreach (StubPublisher::FILES as $stub => $destination) {
        expect($this->projectFile($destination))->toBeFile()
            ->and(file_get_contents($this->projectFile($destination)))
            ->toBe(file_get_contents(__DIR__.'/../../stubs/'.$stub));
    }
});

it('records the templates it copies', function () {
    $this->install();

    $record = json_decode(file_get_contents($this->projectFile(StubPublisher::RECORD)), true)['templates'];

    expect(array_keys($record))->toEqualCanonicalizing(array_values(StubPublisher::FILES));

    foreach (StubPublisher::FILES as $stub => $destination) {
        expect($record[$destination])->toBe(hash_file('sha256', __DIR__.'/../../stubs/'.$stub));
    }
});

it('ships a deploy that removes the caches of the previous release before it runs artisan', function () {
    $this->install();

    $deploy = file_get_contents($this->projectFile('.github/workflows/deploy.yml'));
    $removal = strpos($deploy, 'rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php');

    // A cached config or route list of the previous release can make artisan
    // itself fail with the new code.
    expect($removal)->not->toBeFalse()
        ->and($removal)->toBeLessThan(strpos($deploy, '" artisan '));
});

it('ships a deploy that names PHP_BIN when the PHP of the server is too old', function () {
    $this->install();

    $deploy = file_get_contents($this->projectFile('.github/workflows/deploy.yml'));
    $check = strpos($deploy, "exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then");

    // Otherwise the first artisan command stops in Composer's platform check,
    // whose message does not say which setting to change.
    expect($check)->not->toBeFalse()
        ->and($check)->toBeLessThan(strpos($deploy, '" artisan '))
        ->and(substr($deploy, $check, 400))->toContain('Set the PHP_BIN repository variable')->toContain('exit 1');
});

it('runs the PHP version check of the deploy', function (int $minimum, int $exitCode) {
    $deploy = file_get_contents(__DIR__.'/../../stubs/workflows/deploy.yml');

    preg_match('/^ +if ! "\$PHP" -r .*?^ +fi$/ms', $deploy, $block);

    $script = 'PHP='.escapeshellarg(PHP_BINARY)."\nPHP_BIN=php\n".str_replace('80400', (string) $minimum, $block[0])."\necho passed\n";

    exec('bash -c '.escapeshellarg($script).' 2>&1', $output, $result);

    expect($result)->toBe($exitCode)
        ->and(implode("\n", $output))->toContain($exitCode === 0 ? 'passed' : "'php' on the server is PHP ".PHP_VERSION);
})->with([
    'a PHP that is new enough' => [80400, 0],
    'a PHP that is too old' => [999999, 1],
]);

it('adds the Filament upgrade script to composer.json once', function () {
    $this->install();
    $this->install();

    $composer = json_decode(file_get_contents($this->projectFile('composer.json')));

    expect($composer->scripts->{'post-autoload-dump'})->toBe([
        '@php artisan package:discover --ansi',
        ComposerScripts::SCRIPT,
    ])->and($composer->require)->toBeObject();
});

it('can be run again without duplicating anything', function () {
    $this->install();
    $this->install();

    expect(User::count())->toBe(1)
        ->and(RoleModel::count())->toBe(count(Role::cases()))
        ->and(glob($this->projectFile('database/migrations/*_create_permission_tables.php')))->toHaveCount(1);
});

it('keeps a project file that was changed, unless forced', function () {
    $this->install();
    file_put_contents($this->projectFile('.github/dependabot.yml'), 'mine');

    $this->install();
    expect(file_get_contents($this->projectFile('.github/dependabot.yml')))->toBe('mine');

    $this->install(['--force' => true]);
    expect(file_get_contents($this->projectFile('.github/dependabot.yml')))->not->toBe('mine');
});

it('installs the core from Packagist, without a token', function () {
    $this->install();

    expect(file_get_contents($this->projectFile('.github/dependabot.yml')))
        ->toContain('dependency-name: melasistema/mela-cms')
        ->not->toContain('registries:')
        ->not->toContain('COMPOSER_TOKEN');

    foreach (['ci', 'release', 'deploy'] as $workflow) {
        expect(file_get_contents($this->projectFile(".github/workflows/{$workflow}.yml")))
            ->not->toContain('COMPOSER_AUTH');
    }
});

it('gives an existing account the role without changing its password', function () {
    $this->install();

    $this->install(['--password' => 'another-password']);

    expect(Hash::check('correct-horse', User::sole()->password))->toBeTrue();
});

it('adds a MelaCMS section to the agent guides without touching the rest', function () {
    file_put_contents($this->projectFile('AGENTS.md'), "# Project notes\n");

    $this->install();
    $this->install();

    $agents = file_get_contents($this->projectFile('AGENTS.md'));

    expect($agents)->toStartWith("# Project notes\n\n<mela-cms-guidelines>\n")
        ->and(substr_count($agents, '<mela-cms-guidelines>'))->toBe(1)
        ->and(file_get_contents($this->projectFile('CLAUDE.md')))->toStartWith('<mela-cms-guidelines>');
});
