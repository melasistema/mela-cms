<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The MelaCMS version a site has, the newest one released, and the Composer
 * call that moves the site from one to the other.
 */
final readonly class CoreVersion
{
    public const string PACKAGE = 'melasistema/mela-cms';

    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * The release in vendor/, as Composer installed it. Null for a copy that
     * is not a release (a path repository or a branch) or no Composer install.
     */
    public function installed(): ?string
    {
        $path = $this->project->path('vendor/composer/installed.json');

        if (! $this->files->exists($path)) {
            return null;
        }

        foreach (json_decode($this->files->get($path), true)['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === self::PACKAGE) {
                return $this->release($package['version'] ?? '');
            }
        }

        return null;
    }

    /**
     * The newest release, whatever the constraint in composer.json allows.
     *
     * @throws RuntimeException when Composer cannot be run or cannot read the repository
     */
    public function latest(): ?string
    {
        $result = $this->composer(['show', self::PACKAGE, '--latest', '--format=json']);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'Composer failed without a message.');
        }

        return $this->release(json_decode($result->output(), true)['latest'] ?? '');
    }

    /**
     * Require the release with a constraint that allows it. Below 1.0 a new
     * minor version is outside `^0.5`, so the constraint is raised rather than
     * the package only updated.
     */
    public function update(string $version): ProcessResult
    {
        [$major, $minor] = explode('.', $version);

        return $this->composer(['require', self::PACKAGE.":^{$major}.{$minor}", '--with-all-dependencies']);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function composer(array $arguments): ProcessResult
    {
        $composer = (new Composer($this->files, $this->project->path()))->findComposer();

        return Process::path($this->project->path())
            ->forever()
            ->run([...$composer, ...$arguments, '--no-interaction', '--no-ansi']);
    }

    private function release(string $version): ?string
    {
        return preg_match('/^v?(\d+\.\d+\.\d+)$/', $version, $matches) === 1 ? $matches[1] : null;
    }
}
