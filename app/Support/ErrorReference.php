<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A short code tying what a page shows to what the log holds: the page says only "error code
 * ERR-20261005103012-K7QD", the log line carries the same code with the technical detail. Never put
 * exception messages, SQL, paths or stack traces on a page — log them under a reference instead.
 */
class ErrorReference
{
    /** @var array<string, string> one per prefix and request */
    private static array $current = [];

    /** The reference of the current request (stable within it), e.g. ERR-20261005103012-K7QD. */
    public static function current(string $prefix = 'ERR'): string
    {
        return self::$current[$prefix] ??= self::make($prefix);
    }

    /** A new reference, for an event that is not "the" error of the request (an update run). */
    public static function make(string $prefix = 'ERR'): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    /** Tests only. */
    public static function forget(): void
    {
        self::$current = [];
    }
}
