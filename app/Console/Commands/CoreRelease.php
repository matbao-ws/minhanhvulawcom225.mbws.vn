<?php

namespace App\Console\Commands;

use App\Support\CoreUpdate\ReleaseBuilder;
use App\Support\CoreUpdate\ReleaseSigner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Builds and signs a core release (run by .github/workflows/release.yml on a version tag). The
 * signing key is read from an environment variable and never printed.
 */
class CoreRelease extends Command
{
    protected $signature = 'core:release
        {--output=dist : Directory to write the release to}
        {--channel=stable : dev, beta or stable}
        {--commit= : Git commit the release is built from}
        {--previous= : release.json of the previous release, to tell which migrations are new}
        {--min-from= : Oldest version that may update straight to this one (default: config core_update.min_from)}
        {--backup-required : Ask websites for a database backup before applying it}
        {--no-dependencies : Leave out the vendor/ artifact}
        {--key-env=CORE_RELEASE_SIGNING_KEY : Environment variable holding the private key (PEM)}';

    protected $description = 'Build and sign a core release package';

    public function handle(): int
    {
        $pem = (string) getenv((string) $this->option('key-env'));
        if ($pem === '') {
            $this->components->error('The signing key is not set (env '.$this->option('key-env').').');

            return self::FAILURE;
        }

        $trusted = array_filter((array) config('core_update.public_keys'));
        try {
            $public = ReleaseSigner::publicOf($pem);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
        if (! in_array($public, $trusted, true)) {
            // Websites would refuse it; fail here instead of publishing a release nobody can install.
            $this->components->error('The signing key is not in config/core_update.php public_keys.');

            return self::FAILURE;
        }

        $previous = null;
        if (filled($path = $this->option('previous')) && is_file($path)) {
            $previous = json_decode((string) file_get_contents($path), true);
        }

        try {
            $release = (new ReleaseBuilder(base_path()))->build((string) $this->option('output'), [
                'channel' => (string) $this->option('channel'),
                'private_pem' => $pem,
                'commit' => $this->option('commit'),
                'min_from' => $this->option('min-from'),
                'previous' => is_array($previous) ? $previous : null,
                'backup_required' => (bool) $this->option('backup-required'),
                'dependencies' => ! $this->option('no-dependencies'),
            ]);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Version', $release['version'].' ('.$release['channel'].')');
        $this->components->twoColumnDetail('Core files', (string) count($release['files']));
        foreach ($release['artifacts'] as $name => $artifact) {
            $this->components->twoColumnDetail($artifact['file'], number_format($artifact['size'] / 1048576, 1).' MB');
        }
        $db = $release['database'];
        $this->components->twoColumnDetail('New migrations', (string) count($db['new_migrations']));
        $this->components->twoColumnDetail('Rollback-safe', $db['rollback_safe'] ? 'yes' : 'NO (contract migration)');
        $this->components->twoColumnDetail('Backup required', $db['backup_required'] ? 'yes' : 'no');

        return self::SUCCESS;
    }
}
