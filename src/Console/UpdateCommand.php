<?php

namespace Melasistema\MelaCms\Console;

use Illuminate\Console\Command;
use Melasistema\MelaCms\Install\AgentGuide;
use Melasistema\MelaCms\Install\ComposerScripts;
use Melasistema\MelaCms\Install\Roles;
use Melasistema\MelaCms\Install\StorageLink;
use Melasistema\MelaCms\Install\StubPublisher;
use Melasistema\MelaCms\Install\StubStatus;

class UpdateCommand extends Command
{
    protected $signature = 'mela:update
        {--check : Change nothing, and fail when the agent guides are behind the installed core}';

    protected $description = 'Apply a new MelaCMS core version after `composer update`: migrations, roles, agent guide and a template report';

    /**
     * Never changes a file the project owns: it rewrites only the MelaCMS
     * section of the agent guides and reports the templates that no longer
     * match the installed core.
     */
    public function handle(Roles $roles, ComposerScripts $composerScripts, StubPublisher $stubs, AgentGuide $agentGuide, StorageLink $storageLink): int
    {
        if ($this->option('check')) {
            return $this->check($agentGuide);
        }

        $this->call('migrate');
        $roles->sync();
        $composerScripts->ensureFilamentUpgrade();
        $agentGuide->sync();
        $storageLink->ensure();

        $outdated = array_filter($stubs->statuses(), fn (StubStatus $status) => $status !== StubStatus::Current);

        if ($outdated === []) {
            $this->components->info('MelaCMS is up to date. All project templates match the core.');

            return self::SUCCESS;
        }

        $this->components->info('MelaCMS is up to date. These project files do not match the core templates:');

        foreach ($outdated as $destination => $status) {
            $this->components->twoColumnDetail($destination, $status === StubStatus::Missing ? 'missing' : 'differs');
        }

        $this->components->bulletList([
            'A file that differs is either your own change or a template updated in the core.',
            'Run `php artisan mela:install` to review them one by one, or compare with vendor/melasistema/mela-cms/stubs.',
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
}
