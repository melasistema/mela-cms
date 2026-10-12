<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Keeps a MelaCMS section in the project's agent guides. A fresh Laravel app
 * already ships AGENTS.md and CLAUDE.md, so the section is added between tags
 * and only what is inside the tags is ever rewritten.
 */
final readonly class AgentGuide
{
    public const array FILES = ['AGENTS.md', 'CLAUDE.md'];

    private const string OPEN = '<mela-cms-guidelines>';

    private const string CLOSE = '</mela-cms-guidelines>';

    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * @return array<string, string> file => created, updated or up to date
     */
    public function sync(): array
    {
        $results = [];

        foreach (self::FILES as $file) {
            $path = $this->project->path($file);
            $current = $this->files->exists($path) ? $this->files->get($path) : null;
            $updated = $this->withSection($current);

            if ($updated !== $current) {
                $this->files->put($path, $updated);
            }

            $results[$file] = match (true) {
                $current === null => 'created',
                $updated === $current => 'up to date',
                default => 'updated',
            };
        }

        return $results;
    }

    /**
     * The guides `sync()` would write to, without writing.
     *
     * @return list<string>
     */
    public function outdated(): array
    {
        return array_values(array_filter(self::FILES, function (string $file): bool {
            $path = $this->project->path($file);
            $current = $this->files->exists($path) ? $this->files->get($path) : null;

            return $this->withSection($current) !== $current;
        }));
    }

    private function withSection(?string $current): string
    {
        $section = self::OPEN."\n".trim($this->files->get(__DIR__.'/../../stubs/agent-guidelines.md'))."\n".self::CLOSE;

        if ($current === null) {
            return $section."\n";
        }

        $pattern = '/'.preg_quote(self::OPEN, '/').'.*?'.preg_quote(self::CLOSE, '/').'/s';

        return preg_match($pattern, $current)
            ? preg_replace_callback($pattern, fn () => $section, $current, 1)
            : rtrim($current)."\n\n".$section."\n";
    }
}
