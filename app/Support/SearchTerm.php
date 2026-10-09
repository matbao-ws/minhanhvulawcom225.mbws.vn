<?php

namespace App\Support;

/**
 * A storefront search keyword, made safe for a LIKE scan. Public search runs
 * `%keyword%` over JSON columns on every row; an array (`?q[]=x`) used to end
 * in a 500, and an unbounded keyword full of `%`/`_` wildcards made each
 * request as expensive as the caller liked.
 */
final class SearchTerm
{
    public const MAX_LENGTH = 100;

    /** The trimmed keyword, or null when there is nothing usable to search for. */
    public static function from(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim(mb_substr((string) $value, 0, self::MAX_LENGTH));

        return $value === '' ? null : $value;
    }

    /** `%keyword%` with the keyword's own wildcards matched literally. */
    public static function contains(string $keyword): string
    {
        return '%'.addcslashes($keyword, '\\%_').'%';
    }
}
