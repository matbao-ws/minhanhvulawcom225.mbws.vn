<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historically, locally-uploaded media was persisted as an absolute URL built
 * from whatever APP_URL was set at upload time (e.g.
 * "http://localhost:8000/storage/avatars/x.jpg"). Those rows break as soon as
 * the site moves to another host. Rewrite them to root-relative paths so the
 * URL is resolved per request instead. External assets (Cloudinary, CDNs,
 * Unsplash) are left untouched.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $targets = [
        'products' => ['image_url'],
        'product_images' => ['image_url'],
        'product_variants' => ['image_url'],
        'product_option_values' => ['image_url'],
        'categories' => ['image_url'],
        'brands' => ['image_url'],
        'posts' => ['image_url'],
        'users' => ['avatar_url'],
        'banners' => ['image_path'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->normalizeColumn($table, $column);
            }
        }

        $this->normalizeSettings();
    }

    public function down(): void
    {
        // Relative paths are valid in every environment; nothing to roll back.
    }

    private function normalizeColumn(string $table, string $column): void
    {
        DB::table($table)
            ->select('id', $column)
            ->whereNotNull($column)
            ->where($column, 'like', 'http%/storage/%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $relative = $this->toRelative((string) $row->{$column});

                    if ($relative !== null) {
                        DB::table($table)->where('id', $row->id)->update([$column => $relative]);
                    }
                }
            }, 'id');
    }

    /**
     * project_settings stores JSON-encoded scalars, so the logo/favicon values
     * are quoted strings rather than plain columns.
     */
    private function normalizeSettings(): void
    {
        if (! Schema::hasTable('project_settings')) {
            return;
        }

        $rows = DB::table('project_settings')
            ->whereIn('setting_key', ['logo_url', 'favicon_url'])
            ->get(['id', 'setting_value']);

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->setting_value, true);

            if (! is_string($decoded)) {
                continue;
            }

            $relative = $this->toRelative($decoded);

            if ($relative === null) {
                continue;
            }

            DB::table('project_settings')
                ->where('id', $row->id)
                ->update(['setting_value' => json_encode($relative, JSON_THROW_ON_ERROR)]);
        }
    }

    /**
     * Absolute URL -> root-relative path, but only for Laravel's own public
     * disk mount. Returns null when the value should be left as-is.
     */
    private function toRelative(string $value): ?string
    {
        $value = trim($value);

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return null;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        $query = parse_url($value, PHP_URL_QUERY);

        return $path.($query !== null && $query !== '' ? '?'.$query : '');
    }
};
