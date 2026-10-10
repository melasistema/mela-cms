<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Makes the project republish Filament's assets after every Composer install or
 * update, so the admin never runs against assets from an older Filament.
 */
final readonly class ComposerScripts
{
    public const string SCRIPT = '@php artisan filament:upgrade';

    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * Returns false when composer.json is missing or cannot be read.
     */
    public function ensureFilamentUpgrade(): bool
    {
        $path = $this->project->path('composer.json');

        if (! $this->files->exists($path)) {
            return false;
        }

        // Decoded as objects so empty objects are not written back as [].
        $composer = json_decode($this->files->get($path));

        if (! $composer instanceof \stdClass) {
            return false;
        }

        $composer->scripts ??= new \stdClass;
        $scripts = (array) ($composer->scripts->{'post-autoload-dump'} ?? []);

        if (in_array(self::SCRIPT, $scripts, true)) {
            return true;
        }

        $composer->scripts->{'post-autoload-dump'} = [...$scripts, self::SCRIPT];

        $this->files->put(
            $path,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        return true;
    }
}
