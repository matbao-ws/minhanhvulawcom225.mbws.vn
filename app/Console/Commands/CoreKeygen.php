<?php

namespace App\Console\Commands;

use App\Support\CoreUpdate\ReleaseSigner;
use Illuminate\Console\Command;

/**
 * Creates the release signing key pair. The private key goes to a file (never to the screen) and
 * from there into the GitHub secret CORE_RELEASE_SIGNING_KEY, then the file is deleted; the public
 * key goes into config/core_update.php.
 */
class CoreKeygen extends Command
{
    protected $signature = 'core:keygen {--private= : File to write the private key to (outside the repository)}';

    protected $description = 'Generate a key pair for signing core releases';

    public function handle(): int
    {
        $target = (string) $this->option('private');
        if ($target === '') {
            $this->components->error('Pass --private=PATH, a file outside the repository.');

            return self::FAILURE;
        }

        $real = str_replace('\\', '/', realpath(dirname($target)) ?: dirname($target));
        if (str_starts_with(strtolower($real.'/'), strtolower(str_replace('\\', '/', base_path()).'/'))) {
            $this->components->error('The private key must not be written inside the repository.');

            return self::FAILURE;
        }
        if (file_exists($target)) {
            $this->components->error("{$target} already exists; not overwriting a key.");

            return self::FAILURE;
        }

        $pair = ReleaseSigner::generate();
        file_put_contents($target, $pair['private_pem']);
        @chmod($target, 0600);

        $this->components->info("Private key written to {$target}.");
        $this->line('1. GitHub → repository Settings → Secrets → Actions: add CORE_RELEASE_SIGNING_KEY with the file content.');
        $this->line('2. Delete the file (or keep it offline). Never commit or paste it.');
        $this->line('3. Add the public key to config/core_update.php → public_keys:');
        $this->newLine();
        $this->line("        '{$pair['public']}', // key id {$pair['key_id']}");

        return self::SUCCESS;
    }
}
