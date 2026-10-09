<?php

namespace App\Console\Commands;

use App\Services\CloudinaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads used to keep the client's file extension, so a file the content
 * check accepted could sit in storage as .html and be served as a page on the
 * site's own origin. Lists what does not carry an allowed extension; it never
 * deletes, because an operator should look at each file first.
 */
class AuditMediaExtensions extends Command
{
    protected $signature = 'media:audit-extensions';

    protected $description = 'List files on the public disk whose extension uploads may no longer produce';

    public function handle(): int
    {
        $suspicious = collect(Storage::disk('public')->allFiles())
            ->reject(fn (string $path): bool => basename($path) === '.gitignore')
            ->reject(fn (string $path): bool => in_array(
                strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                CloudinaryService::STORABLE_EXTENSIONS,
                true,
            ))
            ->values();

        if ($suspicious->isEmpty()) {
            $this->components->info('Every file on the public disk has an allowed extension.');

            return self::SUCCESS;
        }

        $this->components->warn("{$suspicious->count()} file(s) carry an extension uploads no longer produce. Review and remove them by hand:");
        $suspicious->each(fn (string $path) => $this->line('  storage/app/public/'.$path));

        return self::FAILURE;
    }
}
