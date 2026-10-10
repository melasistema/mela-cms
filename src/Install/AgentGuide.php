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
        $section = self::OPEN."\n".trim($this->files->get(__DIR__.'/../../stubs/agent-guidelines.md'))."\n".self::CLOSE;
        $results = [];

        foreach (self::FILES as $file) {
            $path = $this->project->path($file);

            if (! $this->files->exists($path)) {
                $this->files->put($path, $section."\n");
                $results[$file] = 'created';

                continue;
            }

            $current = $this->files->get($path);
            $pattern = '/'.preg_quote(self::OPEN, '/').'.*?'.preg_quote(self::CLOSE, '/').'/s';

            $updated = preg_match($pattern, $current)
                ? preg_replace_callback($pattern, fn () => $section, $current, 1)
                : rtrim($current)."\n\n".$section."\n";

            if ($updated !== $current) {
                $this->files->put($path, $updated);
            }

            $results[$file] = $updated === $current ? 'up to date' : 'updated';
        }

        return $results;
    }
}
