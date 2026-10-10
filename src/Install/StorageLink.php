<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Links public/storage to storage/app/public, as `artisan storage:link` does.
 * Without the link the files of the media library have no address.
 */
final readonly class StorageLink
{
    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * Returns false when the link could not be made.
     */
    public function ensure(): bool
    {
        $link = $this->project->path('public/storage');

        if (is_link($link) || $this->files->exists($link)) {
            return true;
        }

        $target = $this->project->path('storage/app/public');

        $this->files->ensureDirectoryExists($target);
        $this->files->ensureDirectoryExists(dirname($link));

        return rescue(fn () => $this->files->link($target, $link) !== false, false, report: false);
    }
}
