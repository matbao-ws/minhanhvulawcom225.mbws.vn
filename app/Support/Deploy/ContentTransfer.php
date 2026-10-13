<?php

namespace App\Support\Deploy;

use App\Services\Packages\PackageLimits;
use App\Support\CoreManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carries the content a developer entered locally to a website on its first deploy
 * (`content:export` here, `content:import` there; deploy.py runs both).
 *
 * The file holds rows of ContentTables only, with their ids, so the relations between them come
 * across intact. Importing is refused unless the website is still new — it replaces those tables
 * wholesale, which on a live shop would overwrite what the owner built.
 */
class ContentTransfer
{
    public const FORMAT = 1;

    private const CHUNK = 200;

    public function __construct(
        private readonly CoreManifest $manifest,
        private readonly PackageLimits $limits,
    ) {}

    /**
     * @return array<string, int> rows written per table
     *
     * @throws ContentTransferRefused when this database has migrations not run yet
     */
    public function export(string $file): array
    {
        if (($pending = $this->pendingMigrations()) !== []) {
            throw new ContentTransferRefused('pending_migrations: run `php artisan migrate` here first ('.implode(', ', array_slice($pending, 0, 3)).')');
        }

        $tables = [];
        foreach (ContentTables::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table)->orderBy('id');
            if ($table === 'project_settings') {
                $query->whereIn('setting_key', ContentTables::SETTING_KEYS);
            }
            $tables[$table] = $query->get()->map(fn (object $row): array => (array) $row)->all();
        }

        $payload = [
            'format' => self::FORMAT,
            'core_version' => $this->manifest->version(),
            'migrations' => $this->ranMigrations(),
            'exported_at' => now()->toIso8601String(),
            'tables' => $tables,
        ];

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, gzencode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 9));

        return array_map('count', $tables);
    }

    /**
     * @return array<string, int> rows written per table
     *
     * @throws ContentTransferRefused when the file is unusable or the website is not new
     */
    public function import(string $file): array
    {
        $payload = $this->read($file);
        $this->assertNew();
        $this->assertSameMigrations($payload['migrations'] ?? []);
        $this->assertWithinPackage($payload['tables']);

        $tables = array_intersect_key($payload['tables'], array_flip(ContentTables::TABLES));
        $counts = [];

        Schema::withoutForeignKeyConstraints(function () use ($tables, &$counts): void {
            DB::transaction(function () use ($tables, &$counts): void {
                // What the seed created (default category, menu, languages, settings) gives way.
                foreach (array_reverse(ContentTables::TABLES) as $table) {
                    if (! array_key_exists($table, $tables) || ! Schema::hasTable($table)) {
                        continue;
                    }
                    $table === 'project_settings'
                        ? DB::table($table)->whereIn('setting_key', ContentTables::SETTING_KEYS)->delete()
                        : DB::table($table)->delete();
                }

                foreach (ContentTables::TABLES as $table) {
                    if (! array_key_exists($table, $tables) || ! Schema::hasTable($table)) {
                        continue;
                    }
                    $columns = array_flip(Schema::getColumnListing($table));
                    // Accounts do not travel: a reference to the developer's user would point at
                    // nobody, or worse at a different person on the website.
                    $userColumns = $this->userForeignKeys($table);

                    $rows = [];
                    foreach ($tables[$table] as $row) {
                        if ($table === 'project_settings') {
                            if (! in_array($row['setting_key'] ?? null, ContentTables::SETTING_KEYS, true)) {
                                continue;
                            }
                            // Only some of its rows are replaced; the others (package state, keys)
                            // keep their ids, which a local id could collide with.
                            unset($row['id']);
                        }
                        $row = array_intersect_key($row, $columns);
                        foreach ($userColumns as $column) {
                            if (array_key_exists($column, $row)) {
                                $row[$column] = null;
                            }
                        }
                        $rows[] = $row;
                    }

                    foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                    $counts[$table] = count($rows);
                }

                foreach (ContentTables::RESET_COUNTERS as $table => $column) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                        DB::table($table)->update([$column => 0]);
                    }
                }
            });
        });

        return $counts;
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        $raw = is_file($file) ? @file_get_contents($file) : false;
        $json = $raw === false ? false : @gzdecode($raw);
        $payload = $json === false ? null : json_decode($json, true);

        if (! is_array($payload) || ($payload['format'] ?? null) !== self::FORMAT || ! is_array($payload['tables'] ?? null)) {
            throw new ContentTransferRefused('invalid_file: not a content export of this format');
        }

        return $payload;
    }

    private function assertNew(): void
    {
        foreach (ContentTables::LIVE_DATA_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new ContentTransferRefused("not_new: the website already has {$table}; content is only carried on a first deploy");
            }
        }

        // The seed creates one superadmin before the import runs; anything beyond is a live shop.
        $customers = DB::table('users')->whereNull('role_id')->count();
        $staff = DB::table('users')->whereNotNull('role_id')->count();
        if ($customers > 0 || $staff > 1) {
            throw new ContentTransferRefused('not_new: the website already has accounts; content is only carried on a first deploy');
        }
    }

    /** @param  list<string>  $migrations */
    private function assertSameMigrations(array $migrations): void
    {
        $here = $this->ranMigrations();
        $missing = array_values(array_diff($migrations, $here));
        $extra = array_values(array_diff($here, $migrations));

        if ($missing !== [] || $extra !== []) {
            throw new ContentTransferRefused(sprintf(
                'schema_mismatch: the export and this website run different migrations (only in the export: %s; only here: %s)',
                implode(', ', array_slice($missing, 0, 3)) ?: '-',
                implode(', ', array_slice($extra, 0, 3)) ?: '-',
            ));
        }
    }

    /**
     * The package caps (PackageLimits) are enforced on model creation; a bulk insert would go
     * around them, so the export is held against them as a whole.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private function assertWithinPackage(array $tables): void
    {
        $wanted = [
            PackageLimits::DATA_UNIT => count($tables['products'] ?? []) + count($tables['posts'] ?? []),
        ];

        foreach ($wanted as $metric => $count) {
            $limit = $this->limits->limit($metric);
            if ($limit !== null && $count > $limit) {
                throw new ContentTransferRefused("package_limit: {$count} {$metric} in the export, the package allows {$limit}");
            }
        }
    }

    /** @return list<string> */
    private function userForeignKeys(string $table): array
    {
        $columns = [];
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['foreign_table'] ?? null) === 'users') {
                array_push($columns, ...$foreignKey['columns']);
            }
        }

        return array_values(array_unique($columns));
    }

    /** @return list<string> */
    private function ranMigrations(): array
    {
        $ran = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
        sort($ran);

        return $ran;
    }

    /** @return list<string> */
    private function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return array_values(array_diff(array_keys($files), $this->ranMigrations()));
    }
}
