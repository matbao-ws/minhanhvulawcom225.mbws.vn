<?php

namespace App\Console\Commands;

use App\Support\CoreUpdate\ReleaseInvalid;
use App\Support\CoreUpdate\ReleaseVerifier;
use Illuminate\Console\Command;

class CoreVerifyRelease extends Command
{
    protected $signature = 'core:verify-release {dir : Directory holding release.json, its signature and the artifacts}';

    protected $description = 'Check a core release: trusted signature, intact artifacts, only core files';

    public function handle(ReleaseVerifier $verifier): int
    {
        try {
            $release = $verifier->verify(rtrim((string) $this->argument('dir'), '/\\'));
        } catch (ReleaseInvalid $e) {
            $this->components->error('Release rejected: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Release %s (%s) is valid: %d core files.', $release['version'], $release['channel'], count($release['files'])));

        return self::SUCCESS;
    }
}
