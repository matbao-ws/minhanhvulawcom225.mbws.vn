<?php

namespace App\Support\CoreUpdate;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs artisan in a fresh PHP process. The updater needs that: once the files are replaced, the
 * process doing the update still holds the old classes in memory, so migrations, package
 * discovery and the health check must boot the new code on their own.
 *
 * Meant for the CLI (cron runs the update): under PHP-FPM, PHP_BINARY is not a CLI binary —
 * set CORE_UPDATE_PHP_BINARY when the host needs a specific one.
 */
class ArtisanRunner
{
    public function __construct(private readonly string $base) {}

    /** @param list<string> $arguments e.g. ['migrate', '--force'] */
    public function run(array $arguments, int $timeout = 600): string
    {
        $process = new Process([$this->php(), 'artisan', ...$arguments, '--no-interaction', '--no-ansi'], $this->base, null, null, $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            // The tail of the output holds the reason; keep state.json readable.
            $output = preg_replace(['/\e\[[0-9;]*m/', '/\s+/'], ['', ' '], $process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(sprintf('artisan %s failed: %s', implode(' ', $arguments), mb_strimwidth(trim($output), 0, 600, '…')));
        }

        return $process->getOutput();
    }

    /**
     * Start one process before any file is replaced, so every class the runner needs is loaded
     * from the old vendor/ — not half from the old, half from the new.
     */
    public function warm(): void
    {
        $process = new Process([$this->php(), '-v'], $this->base, null, null, 30);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Cannot start PHP processes: '.trim($process->getErrorOutput()));
        }
    }

    private function php(): string
    {
        return (string) (config('core_update.php_binary') ?: PHP_BINARY);
    }
}
