<?php

namespace App\Support;

/**
 * URL of a file in public/ with ?v=<mtime>, so browsers and CDNs fetch it again
 * after a deploy changes it instead of serving the copy they cached.
 */
class AssetVersion
{
    /** @var array<string, string> */
    private static array $urls = [];

    public static function url(string $path): string
    {
        return self::$urls[$path] ??= (function () use ($path) {
            $file = public_path($path);
            $mtime = is_file($file) ? filemtime($file) : false;

            return asset($path).($mtime ? '?v='.$mtime : '');
        })();
    }
}
