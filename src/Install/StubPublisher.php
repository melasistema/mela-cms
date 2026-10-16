<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Copies the templates in stubs/ into the client project. Once copied, a file
 * belongs to the project: it is only replaced when the caller asks for it.
 *
 * The project's `mela-cms.json` records, per file, a hash of the template
 * version the site last took or kept. A file that differs from a template the
 * site already kept is its own change and is not raised again; only a new
 * version of the template is.
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

    public const string RECORD = 'mela-cms.json';

    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * @return array<string, StubStatus> keyed by destination
     */
    public function statuses(): array
    {
        $record = $this->record();
        $statuses = [];

        foreach (self::FILES as $stub => $destination) {
            $target = $this->project->path($destination);
            $template = $this->hash($this->files->get($this->stubPath($stub)));
            $file = $this->files->exists($target) ? $this->hash($this->files->get($target)) : null;

            $statuses[$destination] = match (true) {
                $file === $template => StubStatus::Current,
                ($record[$destination] ?? null) === $template => StubStatus::Kept,
                $file === null => StubStatus::Missing,
                default => StubStatus::Changed,
            };
        }

        return $statuses;
    }

    /**
     * Whether the file is the template version the site last took, unchanged:
     * then taking the new version loses nothing of the site's own.
     */
    public function untouched(string $destination): bool
    {
        $target = $this->project->path($destination);
        $recorded = $this->record()[$destination] ?? null;

        return $recorded !== null
            && $this->files->exists($target)
            && $this->hash($this->files->get($target)) === $recorded;
    }

    public function publish(string $destination): void
    {
        $target = $this->project->path($destination);

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy($this->templatePath($destination), $target);
        $this->remember([$destination]);
    }

    /**
     * Keep the project's file (or its absence) against this version of the template.
     */
    public function keep(string $destination): void
    {
        $this->remember([$destination]);
    }

    /**
     * Record the files that are the current template, so a later version of
     * the template can tell whether the site changed them.
     */
    public function track(): void
    {
        $this->remember(array_keys(array_filter(
            $this->statuses(),
            fn (StubStatus $status) => $status === StubStatus::Current,
        )));
    }

    public function path(string $destination): string
    {
        return $this->project->path($destination);
    }

    public function templatePath(string $destination): string
    {
        return $this->stubPath(array_search($destination, self::FILES, true));
    }

    /**
     * @param  list<string>  $destinations
     */
    private function remember(array $destinations): void
    {
        $record = $this->record();
        $updated = $record;

        foreach ($destinations as $destination) {
            $updated[$destination] = $this->hash($this->files->get($this->templatePath($destination)));
        }

        if ($updated === $record) {
            return;
        }

        ksort($updated);
        $path = $this->project->path(self::RECORD);
        $contents = $this->files->exists($path) ? (json_decode($this->files->get($path), true) ?: []) : [];
        $contents['templates'] = $updated;

        $this->files->put($path, json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * @return array<string, string> destination => template hash
     */
    private function record(): array
    {
        $path = $this->project->path(self::RECORD);

        if (! $this->files->exists($path)) {
            return [];
        }

        $templates = json_decode($this->files->get($path), true)['templates'] ?? [];

        return is_array($templates) ? $templates : [];
    }

    /**
     * Line endings are left out, so a checkout with CRLF is the same file.
     */
    private function hash(string $contents): string
    {
        return hash('sha256', str_replace("\r\n", "\n", $contents));
    }

    private function stubPath(string $stub): string
    {
        return __DIR__.'/../../stubs/'.$stub;
    }
}
