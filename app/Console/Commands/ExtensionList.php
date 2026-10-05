<?php

namespace App\Console\Commands;

use App\Support\Extensions\ExtensionManager;
use Illuminate\Console\Command;

/**
 * Every folder in extensions/ and what becomes of it. Runs without loading any extension
 * (ExtensionServiceProvider skips extension:* commands), so it works while one is broken: "ready"
 * means it passes every check and loads on a normal request.
 */
class ExtensionList extends Command
{
    protected $signature = 'extension:list {--json : Machine-readable output}';

    protected $description = 'List the website\'s extensions: version, compatibility, on/off, problems';

    public function handle(ExtensionManager $extensions): int
    {
        $rows = array_map(fn (array $record) => [
            'id' => $record['id'],
            'name' => $record['manifest']?->name,
            'version' => $record['manifest']?->version,
            'requires_core' => $record['manifest']?->requiresCore,
            'status' => $record['status'],
            'note' => $record['error'],
        ], array_values($extensions->all()));

        if ($this->option('json')) {
            $this->line(json_encode(['enabled' => $extensions->enabled(), 'extensions' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $extensions->enabled()) {
            $this->components->warn('EXTENSIONS_ENABLED=false: no extension is loaded on this website.');
        }
        if ($rows === []) {
            $this->components->info('No extension in '.$extensions->root().'.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Version', 'Requires core', 'Status', 'Note'], array_map(fn (array $row) => [
            $row['id'], $row['name'] ?? '-', $row['version'] ?? '-', $row['requires_core'] ?? '-', $row['status'],
            $row['note'] === null ? '' : mb_strimwidth($row['note'], 0, 90, '…'),
        ], $rows));

        return self::SUCCESS;
    }
}
