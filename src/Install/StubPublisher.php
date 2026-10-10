<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Copies the templates in stubs/ into the client project. Once copied, a file
 * belongs to the project: it is only replaced when the caller asks for it.
 */
final readonly class StubPublisher
{
    /**
     * Template (relative to stubs/) => destination (relative to the project).
     */
    public const array FILES = [
        'workflows/ci.yml' => '.github/workflows/ci.yml',
        'workflows/release.yml' => '.github/workflows/release.yml',
        'workflows/deploy.yml' => '.github/workflows/deploy.yml',
        'dependabot.yml' => '.github/dependabot.yml',
    ];

    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * @return array<string, StubStatus> keyed by destination
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach (self::FILES as $stub => $destination) {
            $statuses[$destination] = $this->status($stub, $destination);
        }

        return $statuses;
    }

    public function publish(string $destination): void
    {
        $stub = array_search($destination, self::FILES, true);
        $target = $this->project->path($destination);

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy($this->stubPath($stub), $target);
    }

    private function status(string $stub, string $destination): StubStatus
    {
        $target = $this->project->path($destination);

        if (! $this->files->exists($target)) {
            return StubStatus::Missing;
        }

        return $this->files->get($target) === $this->files->get($this->stubPath($stub))
            ? StubStatus::Current
            : StubStatus::Differs;
    }

    private function stubPath(string $stub): string
    {
        return __DIR__.'/../../stubs/'.$stub;
    }
}
