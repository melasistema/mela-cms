<?php

namespace Melasistema\MelaCms\Console\Concerns;

use Illuminate\Support\Facades\Process;
use Melasistema\MelaCms\Install\StubPublisher;
use Melasistema\MelaCms\Install\StubStatus;

/**
 * Goes through the project files made from the core templates. A file the
 * site already decided about for this version of its template is left alone;
 * for a new version the developer chooses, and the choice is recorded.
 */
trait ReviewsTemplates
{
    private const string TAKE = 'Take the core template';

    private const string KEEP = 'Keep mine';

    private const string DIFF = 'Show the differences';

    private const string ADD = 'Add it';

    private const string LEAVE_OUT = 'Leave it out';

    /**
     * @return list<string> the files still waiting for a choice, because the command ran without interaction
     */
    private function reviewTemplates(StubPublisher $stubs, bool $createMissing = false): array
    {
        $force = $this->hasOption('force') && $this->option('force');
        $waiting = [];

        $stubs->track();

        foreach ($stubs->statuses() as $destination => $status) {
            if ($status === StubStatus::Current) {
                $this->components->twoColumnDetail($destination, 'up to date');

                continue;
            }

            if ($status === StubStatus::Kept && ! $force) {
                $this->components->twoColumnDetail($destination, 'your version, kept');

                continue;
            }

            $exists = $status !== StubStatus::Missing && $this->fileExists($stubs, $destination);

            if ($force || ($status === StubStatus::Missing && $createMissing)) {
                $stubs->publish($destination);
                $this->components->twoColumnDetail($destination, $exists ? 'replaced' : 'created');

                continue;
            }

            if (! $this->input->isInteractive()) {
                $waiting[] = $destination;
                $this->components->twoColumnDetail($destination, $status === StubStatus::Missing ? 'missing, waiting for your choice' : 'differs, waiting for your choice');

                continue;
            }

            if ($this->chooseTemplate($stubs, $destination, $status)) {
                $stubs->publish($destination);
                $this->components->twoColumnDetail($destination, $status === StubStatus::Missing ? 'created' : 'replaced');
            } else {
                $stubs->keep($destination);
                $this->components->twoColumnDetail($destination, 'your version, kept');
            }
        }

        return $waiting;
    }

    /**
     * Whether to take the core template. A file the site never changed
     * suggests taking it; a file with changes of its own suggests keeping it.
     */
    private function chooseTemplate(StubPublisher $stubs, string $destination, StubStatus $status): bool
    {
        if ($status === StubStatus::Missing) {
            return $this->choice("{$destination} is not in the project. Add the core template?", [self::ADD, self::LEAVE_OUT], self::LEAVE_OUT) === self::ADD;
        }

        $untouched = $stubs->untouched($destination);
        $question = $untouched
            ? "The core template of {$destination} changed. You have not changed the file."
            : "The core template of {$destination} changed since you took or kept it, or this is the first check of the file.";

        while (($answer = $this->choice($question, [self::TAKE, self::KEEP, self::DIFF], $untouched ? self::TAKE : self::KEEP)) === self::DIFF) {
            $this->showDifferences($stubs, $destination);
        }

        return $answer === self::TAKE;
    }

    private function showDifferences(StubPublisher $stubs, string $destination): void
    {
        $template = realpath($stubs->templatePath($destination));
        $result = Process::run(['git', 'diff', '--no-index', '--no-color', '--', $stubs->path($destination), $template]);

        // `git diff` exits with 1 when the files differ.
        if ($result->exitCode() !== 1) {
            $this->components->warn("Could not compare the files. The core template is {$template}.");

            return;
        }

        $this->newLine();
        $this->line(rtrim($result->output()));
        $this->newLine();
        $this->components->info('Lines with + are what the core template would bring, lines with - what taking it would remove.');
    }

    private function fileExists(StubPublisher $stubs, string $destination): bool
    {
        return is_file($stubs->path($destination));
    }
}
