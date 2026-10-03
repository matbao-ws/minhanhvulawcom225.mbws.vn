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
