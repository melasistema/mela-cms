<?php

namespace Melasistema\MelaCms\Install;

/**
 * Reads the changelog release-please writes into the package, to tell a site
 * what a core update brings.
 */
final readonly class Changelog
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? __DIR__.'/../../CHANGELOG.md';
    }

    /**
     * The entries of the versions after `$from` up to `$to`, newest first,
     * without the links to commits and issues.
     *
     * @return list<string>
     */
    public function between(string $from, string $to): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $entries = [];
        $version = null;
        $breaking = false;

        foreach (file($this->path, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^##? \[?v?(\d+\.\d+\.\d+)/', $line, $matches) === 1) {
                $version = $matches[1];
                $breaking = false;
            } elseif (str_starts_with($line, '### ')) {
                $breaking = str_contains(strtoupper($line), 'BREAKING');
            } elseif (str_starts_with($line, '* ')
                && $version !== null
                && version_compare($version, $from, '>')
                && version_compare($version, $to, '<=')) {
                $entry = trim(preg_replace('/ \(\[[^\]]*\]\([^)]*\)\)/', '', substr($line, 2)));
                $entries[] = $breaking ? 'Breaking: '.$entry : $entry;
            }
        }

        return $entries;
    }
}
