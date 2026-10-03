<?php

namespace App\Support\CoreUpdate;

/**
 * Just enough version logic for the updater: comparing core versions, and checking the running
 * PHP against a composer-style constraint (^8.2, ~8.2, >=8.2 <9, 8.3.*, a || b). composer/semver
 * is not a production dependency, so this stays small and local.
 */
class Semver
{
    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*\|\|?\s*/', trim($constraint)) as $alternative) {
            $parts = array_filter(preg_split('/[\s,]+/', trim($alternative)), fn ($p) => $p !== '');
            if ($parts !== [] && array_reduce($parts, fn ($ok, $part) => $ok && self::one($version, $part), true)) {
                return true;
            }
        }

        return false;
    }

    private static function one(string $version, string $constraint): bool
    {
        if ($constraint === '*') {
            return true;
        }
        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $m)) {
            $low = $m[1].'.'.($m[2] ?? 0).'.'.($m[3] ?? 0);
            $high = (int) $m[1] > 0 ? ($m[1] + 1).'.0.0' : '0.'.(($m[2] ?? 0) + 1).'.0';

            return self::compare($version, $low) >= 0 && self::compare($version, $high) < 0;
        }
        if (preg_match('/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $m)) {
            $low = $m[1].'.'.$m[2].'.'.($m[3] ?? 0);
            $high = isset($m[3]) ? $m[1].'.'.($m[2] + 1).'.0' : ($m[1] + 1).'.0.0';

            return self::compare($version, $low) >= 0 && self::compare($version, $high) < 0;
        }
        if (preg_match('/^(\d+(?:\.\d+)*)\.\*$/', $constraint, $m)) {
            return str_starts_with(self::normalize($version).'.', $m[1].'.');
        }
        if (preg_match('/^(>=|<=|>|<|==|=|!=)?\s*v?(\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?)$/', $constraint, $m)) {
            $op = ['' => '==', '=' => '=='][$m[1]] ?? $m[1];

            return version_compare(self::normalize($version), self::normalize($m[2]), $op);
        }

        return false; // unknown syntax: refuse rather than guess
    }

    /** 8.2 → 8.2.0, v1.4.0 → 1.4.0 */
    private static function normalize(string $version): string
    {
        $version = ltrim(trim($version), 'v');
        [$core, $pre] = array_pad(explode('-', $version, 2), 2, null);
        $parts = array_pad(explode('.', $core), 3, '0');

        return implode('.', array_slice($parts, 0, 3)).($pre !== null ? '-'.$pre : '');
    }
}
