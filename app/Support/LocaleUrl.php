<?php

namespace App\Support;

use App\Services\LanguageRegistry;

/**
 * Storefront URLs of the default language carry no prefix (`/chinh-sach/x`); every other language
 * keeps it (`/en/chinh-sach/x`). The admin always keeps it (`/vi/admin`). Switched off with
 * MULTILINGUAL_HIDE_DEFAULT_LOCALE=false, every storefront URL keeps its prefix as before.
 *
 * URLs built with route('client.…') get this from the path formatter in AppServiceProvider; this
 * class serves the places that put a URL together by hand (error pages, language switchers).
 */
final class LocaleUrl
{
    public static function enabled(): bool
    {
        return (bool) config('multilingual.hide_default_locale', true);
    }

    public static function defaultLocale(): string
    {
        return app(LanguageRegistry::class)->defaultLocale();
    }

    /** `/vi/chinh-sach/x` → `/chinh-sach/x` and `/vi` → `/` when vi is the default language. */
    public static function stripDefault(string $path): string
    {
        if (! self::enabled()) {
            return $path;
        }

        $prefix = '/'.self::defaultLocale();
        if ($path === $prefix) {
            return '/';
        }

        return str_starts_with($path, $prefix.'/') ? substr($path, strlen($prefix)) : $path;
    }

    /** The storefront path of `$rest` in `$locale`: `/chinh-sach/x` or `/en/chinh-sach/x`. */
    public static function path(string $locale, string $rest = ''): string
    {
        $rest = trim($rest, '/');
        if (self::enabled() && $locale === self::defaultLocale()) {
            return '/'.$rest;
        }

        return '/'.$locale.($rest !== '' ? '/'.$rest : '');
    }

    public static function home(string $locale): string
    {
        return url(self::path($locale));
    }

    /**
     * The language a path names in its first segment, and the path without it. A path without a
     * language segment is the default language's.
     *
     * @return array{0: string, 1: string, 2: bool} locale, rest, whether the path carried the prefix
     */
    public static function split(string $path): array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $segment): bool => $segment !== ''));
        $first = $segments[0] ?? '';

        if ($first !== '' && app(LanguageRegistry::class)->supportsRoute($first)) {
            return [$first, implode('/', array_slice($segments, 1)), true];
        }

        return [self::defaultLocale(), implode('/', $segments), false];
    }

    /**
     * The current page in another language. Admin pages always keep the prefix.
     */
    public static function switchTo(string $locale, ?string $path = null): string
    {
        [, $rest] = self::split($path ?? request()->path());

        if ($rest === 'admin' || str_starts_with($rest, 'admin/')) {
            return url($locale.'/'.$rest);
        }

        return url(self::path($locale, $rest));
    }
}
