<?php

namespace App\Support;

/**
 * The expand/contract rule for migrations (AI_BUILD_PROMPT.md, "Cập nhật phiên bản"):
 * a release must keep working with the code of the release before it, because a failed
 * update restores the old code and leaves the database as it is.
 *
 * In `up()` a normal migration may add tables, nullable columns, indexes and data. It may
 * not drop or rename columns or tables, change a column's type, or empty a table. Those
 * belong to a later "contract" release, once every website runs code that no longer needs
 * the old schema — such a migration carries the marker `@contract-migration` with a reason,
 * and the release that contains it is not rollback-safe (no automatic rollout).
 */
class MigrationSafety
{
    public const CONTRACT_MARKER = '@contract-migration';

    /** Migrations from this date on are held to the rule; older ones predate it. */
    public const POLICY_FROM = '2026_10_06_000000';

    /** pattern => what it breaks */
    private const FORBIDDEN = [
        '/->\s*dropColumn\s*\(/i' => 'drops a column',
        '/->\s*dropColumns\s*\(/i' => 'drops columns',
        '/->\s*renameColumn\s*\(/i' => 'renames a column',
        '/->\s*change\s*\(\s*\)/i' => 'changes a column type',
        '/Schema::\s*drop(IfExists)?\s*\(/i' => 'drops a table',
        '/Schema::\s*rename\s*\(/i' => 'renames a table',
        '/->\s*(dropTimestamps|dropSoftDeletes|dropRememberToken|dropMorphs|dropConstrainedForeignId)\s*\(/i' => 'drops columns',
        '/->\s*truncate\s*\(/i' => 'empties a table',
        '/\b(DROP\s+(TABLE|COLUMN)|RENAME\s+(TABLE|COLUMN)|MODIFY\s+COLUMN|CHANGE\s+COLUMN|TRUNCATE)\b/i' => 'destructive SQL',
    ];

    /** @return list<string> why the migration's up() is not backward-compatible (empty when it is) */
    public static function violations(string $file): array
    {
        $code = (string) file_get_contents($file);
        if (str_contains($code, self::CONTRACT_MARKER)) {
            return [];
        }

        $up = self::upMethod($code);
        $found = [];
        foreach (self::FORBIDDEN as $pattern => $reason) {
            if (preg_match($pattern, $up)) {
                $found[] = $reason;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * An extension's migration may only touch its own tables, named "<prefix>..." (ext_<id>_):
     * never a core table or another extension's. Foreign keys pointing at core tables are fine.
     *
     * @return list<string> why not (empty when every table it names is its own)
     */
    public static function foreignTables(string $file, string $prefix): array
    {
        $code = (string) file_get_contents($file);
        $found = [];

        $calls = '/(Schema::(?:connection\([^)]*\)->)?(create|table|drop|dropIfExists|rename|dropColumns)|DB::table)\s*\(\s*([^,)]*)(?:,\s*([^,)]*))?/i';
        preg_match_all($calls, $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $call = preg_replace('/\s+/', '', $match[1]);
            $tables = [$match[3]];
            if (strcasecmp($match[2] ?? '', 'rename') === 0 && isset($match[4])) {
                $tables[] = $match[4];
            }
            foreach ($tables as $argument) {
                $argument = trim($argument);
                if (! preg_match('/^([\'"])([A-Za-z0-9_]+)\1$/', $argument, $literal)) {
                    $found[] = "{$call}({$argument}): the table name must be a literal string";
                } elseif (! str_starts_with($literal[2], $prefix)) {
                    $found[] = "{$call}('{$literal[2]}'): not its own table (must start with {$prefix})";
                }
            }
        }
        if (preg_match('/DB::(statement|unprepared|insert|update|delete|select)\s*\(/i', $code)) {
            $found[] = 'raw SQL (DB::statement & co.) cannot be checked for table ownership';
        }

        return array_values(array_unique($found));
    }

    /** Foreign keys deleting rows in cascade: prefer restrictOnDelete() / nullOnDelete() towards core tables. */
    public static function cascades(string $file): bool
    {
        return (bool) preg_match('/cascadeOnDelete\s*\(|onDelete\s*\(\s*[\'"]cascade/i', (string) file_get_contents($file));
    }

    public static function isContract(string $file): bool
    {
        return str_contains((string) file_get_contents($file), self::CONTRACT_MARKER);
    }

    public static function underPolicy(string $file): bool
    {
        return strcmp(substr(basename($file), 0, 17), self::POLICY_FROM) >= 0;
    }

    /** The body of up(): from its declaration to down() (or the end). */
    private static function upMethod(string $code): string
    {
        $start = preg_match('/function\s+up\s*\(/i', $code, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : 0;
        $end = preg_match('/function\s+down\s*\(/i', $code, $n, PREG_OFFSET_CAPTURE, $start) ? $n[0][1] : strlen($code);

        return substr($code, $start, $end - $start);
    }
}
