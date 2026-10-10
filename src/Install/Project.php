<?php

namespace Melasistema\MelaCms\Install;

/**
 * The client project the install and update commands write into.
 */
final readonly class Project
{
    public function __construct(private string $basePath) {}

    public function path(string $path = ''): string
    {
        return rtrim($this->basePath.DIRECTORY_SEPARATOR.ltrim($path, '/\\'), '/\\');
    }
}
