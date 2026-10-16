<?php

namespace Melasistema\MelaCms\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Melasistema\MelaCms\Console\Concerns\ReviewsTemplates;
use Melasistema\MelaCms\Install\AgentGuide;
use Melasistema\MelaCms\Install\Changelog;
use Melasistema\MelaCms\Install\ComposerScripts;
use Melasistema\MelaCms\Install\CoreVersion;
use Melasistema\MelaCms\Install\Project;
use Melasistema\MelaCms\Install\Roles;
use Melasistema\MelaCms\Install\StorageLink;
use Melasistema\MelaCms\Install\StubPublisher;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

use function Illuminate\Support\php_binary;

class UpdateCommand extends Command
{
    use ReviewsTemplates;

    protected $signature = 'mela:update
        {--installed : Apply the core already in vendor/, without looking for a newer version}
        {--check : Change nothing, and fail when the agent guides are behind the installed core}';

    protected $description = 'Update the MelaCMS core to its newest version and apply it: Composer, migrations, roles, agent guides and the templates the core changed';

    /**
     * Never changes a file the project owns without asking: besides
     * composer.json and composer.lock it rewrites only the MelaCMS section of
     * the agent guides and the template record, and asks about a project file
     * only when the core changed its template.
     */
    public function handle(Project $project, CoreVersion $core, Changelog $changelog): int
    {
        if ($this->option('check')) {
            return $this->laravel->call($this->check(...));
        }

        // On a server the core arrives with a deploy, built from composer.lock.
        if ($this->option('installed') || $this->laravel->isProduction()) {
            return $this->laravel->call($this->apply(...));
        }

        return $this->updateCore($project, $core, $changelog) ?? $this->laravel->call($this->apply(...));
    }

    /**
     * Move the site to the newest release. Returns null when there is nothing
     * to move, so the installed core is applied in this process.
     */
    private function updateCore(Project $project, CoreVersion $core, Changelog $changelog): ?int
    {
        $from = $core->installed();

        if ($from === null) {
            $this->components->info('The installed MelaCMS core is not a release, so Composer is left alone.');

            return null;
        }

        try {
            $latest = $core->latest();
        } catch (RuntimeException $exception) {
            $this->components->warn("Could not look for a new MelaCMS version, so the installed {$from} is applied. Composer said: {$exception->getMessage()}");

            return null;
        }

        if ($latest === null || version_compare($latest, $from, '<=')) {
            $this->components->info("MelaCMS {$from} is the newest version.");

            return null;
        }

        $this->components->info("Updating MelaCMS {$from} → {$latest}");

        $result = null;
        $this->components->task('Composer', function () use ($core, $latest, &$result) {
            $result = $core->update($latest);

            return $result->successful();
        });

        $to = $core->installed();

        if (! $result->successful() || $to === null || version_compare($to, $from, '<=')) {
            $this->failed($result, 'Composer did not update MelaCMS. Nothing else was changed.');

            return self::FAILURE;
        }

        // This process still runs the classes of the previous core: the new
        // one is applied by its own code, in a fresh process. It gets the
        // terminal, to ask about the templates the core changed.
        $applied = Process::path($project->path())->forever()
            ->tty($this->input->isInteractive() && SymfonyProcess::isTtySupported())
            ->run(
                array_filter([php_binary(), 'artisan', 'mela:update', '--installed', $this->output->isDecorated() ? '--ansi' : null]),
                fn (string $type, string $output) => $this->output->write($output),
            );

        if ($applied->failed()) {
            $this->components->error("MelaCMS {$to} is installed, but applying it failed. Fix the error above and run `php artisan mela:update` again.");

            return self::FAILURE;
        }

        if ($entries = $changelog->between($from, $to)) {
            $this->components->info("What's new in MelaCMS {$to}");
            $this->components->bulletList($entries);
        }

        $this->components->info('Commit the changes and release the site as usual:');
        $this->line("  fix: update MelaCMS to {$to}");
        $this->newLine();

        return self::SUCCESS;
    }

    private function apply(Roles $roles, ComposerScripts $composerScripts, StubPublisher $stubs, AgentGuide $agentGuide, StorageLink $storageLink): int
    {
        $this->call('migrate');
        $roles->sync();
        $composerScripts->ensureFilamentUpgrade();
        $agentGuide->sync();
        $storageLink->ensure();

        $waiting = $this->reviewTemplates($stubs);

        if ($waiting === []) {
            $this->components->info('MelaCMS is up to date.');

            return self::SUCCESS;
        }

        $this->components->info('MelaCMS is up to date. The files waiting for your choice are left as they are.');
        $this->components->bulletList([
            'Run `php artisan mela:update --installed` in a terminal to take the new template or keep your file, one by one.',
            'Your choice is recorded in '.StubPublisher::RECORD.', so a file is raised again only when the core changes its template.',
        ]);

        return self::SUCCESS;
    }

    /**
     * For the CI of a site: a core update that arrives without `mela:update`
     * (a Dependabot pull request) leaves the agent guides of the old version.
     * Touches neither the database nor a file. The templates are not checked,
     * because a file that differs can be the site's own change.
     */
    private function check(AgentGuide $agentGuide): int
    {
        $outdated = $agentGuide->outdated();

        if ($outdated === []) {
            $this->components->info('The agent guides match the installed MelaCMS core.');

            return self::SUCCESS;
        }

        $this->components->error('The MelaCMS section of these files does not match the installed core: '.implode(', ', $outdated).'.');
        $this->components->bulletList(['Run `php artisan mela:update` and commit the result.']);

        return self::FAILURE;
    }

    private function failed(?ProcessResult $result, string $message): void
    {
        if ($result !== null && ($output = trim($result->errorOutput() ?: $result->output())) !== '') {
            $this->newLine();
            $this->line($output);
        }

        $this->components->error($message);
    }
}
