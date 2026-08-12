<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves media references to URLs that always follow the current APP_URL.
 *
 * Media that lives on this application is persisted as a root-relative path
 * ("/storage/products/foo.jpg"), never as an absolute URL, so moving the site
 * between environments (local -> staging -> production) cannot leave stale
 * hosts baked into the database. Absolute URLs are only kept for genuinely
 * external assets such as Cloudinary or a third-party CDN.
 *
 * Models normalise their media columns through
 * {@see \App\Models\Concerns\HasMediaUrls}. That works on Eloquent writes only:
 * a query-builder `DB::table(...)->update()`, `->insert()` or `Model::query()
 * ->update()` bypasses the mutator, so pass such values through
 * {@see toStorable()} explicitly.
 */
class MediaUrl
{
    /**
     * Turn a stored media reference into a URL usable by the current request.
     */
    public static function resolve(?string $value, ?string $fallback = null): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        if ($value === null || $value === '') {
            return $fallback !== null ? asset(ltrim($fallback, '/')) : null;
        }

        // Inline data and protocol-relative URLs are already environment agnostic.
        if (self::isSchemeAgnostic($value)) {
            return $value;
        }

        if (self::isAbsolute($value)) {
            $local = self::localPathFromAbsoluteUrl($value);

            return $local !== null ? asset(ltrim($local, '/')) : $value;
        }

        return asset(ltrim($value, '/'));
    }

    /**
     * Normalise a value before persisting it, stripping our own host so the
     * stored reference stays portable across environments.
     */
    public static function toStorable(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        if ($value === null || $value === '') {
            return null;
        }

        if (self::isSchemeAgnostic($value)) {
            return $value;
        }

        if (self::isAbsolute($value)) {
            return self::localPathFromAbsoluteUrl($value) ?? $value;
        }

        return '/'.ltrim($value, '/');
    }

    /**
     * Path of a media reference on the "public" disk, or null when the asset is
     * not stored there. Used when deleting the file behind a stored URL.
     */
    public static function publicDiskPath(?string $value): ?string
    {
        $path = self::toStorable($value);

        if ($path === null) {
            return null;
        }

        // A query or fragment is part of the URL, never part of the filename.
        return self::diskRelativePath((string) preg_replace('/[?#].*$/', '', $path));
    }

    /**
     * "/storage/<path>" -> "<path>" as it exists on the public disk, or null
     * when the reference does not point into that disk.
     */
    private static function diskRelativePath(string $path): ?string
    {
        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        // Decode before validating, so an encoded "%2e%2e" cannot smuggle
        // traversal past the check.
        $relative = rawurldecode(ltrim(substr($path, strlen('/storage/')), '/'));

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
    }

    /**
     * Rewrite absolute media sources that point at this application into
     * root-relative paths, so rich-text content saved in one environment keeps
     * working in another. External sources are left untouched.
     */
    public static function relativizeHtmlSources(string $html): string
    {
        if ($html === '' || ! preg_match('#https?://#i', $html)) {
            return $html;
        }

        // Only absolute URLs are touched. An already-relative source is left as
        // written (bar surrounding whitespace, which browsers ignore anyway):
        // turning "images/a.jpg" into "/images/a.jpg" would change what it
        // resolves to, and that is not ours to decide.
        $rewrite = static fn (string $url): string => self::isAbsolute($url)
            ? (self::toStorable($url) ?? $url)
            : $url;

        return self::mapMediaAttributes($html, $rewrite);
    }

    /**
     * Inverse of {@see relativizeHtmlSources}: expand root-relative media
     * sources to absolute URLs. Needed when the HTML leaves the site — API
     * payloads consumed by a separate frontend, feeds, or e-mail.
     */
    public static function absolutizeHtmlSources(?string $html): string
    {
        $html = (string) $html;

        if ($html === '' || ! str_contains($html, '=')) {
            return $html;
        }

        return self::mapMediaAttributes($html, static fn (string $url): string => self::resolve($url) ?? $url);
    }

    /**
     * Apply $rewrite to every URL inside a media source attribute (`src`,
     * `srcset`, `poster`, including the `data-` lazy-loading variants).
     *
     * @param  callable(string): string  $rewrite
     */
    private static function mapMediaAttributes(string $html, callable $rewrite): string
    {
        return (string) preg_replace_callback(
            '/\b(src|srcset|poster)\s*=\s*(["\'])(.*?)\2/is',
            static function (array $match) use ($rewrite): string {
                $value = strtolower($match[1]) === 'srcset'
                    ? self::mapSrcset($match[3], $rewrite)
                    : $rewrite(trim($match[3]));

                return $match[1].'='.$match[2].$value.$match[2];
            },
            $html,
        );
    }

    /**
     * Rewrite the URL of every candidate in a srcset, preserving descriptors
     * ("2x", "640w").
     *
     * Splitting on "," is wrong: a comma is legal inside a URL (Cloudinary
     * transformations like "w_100,h_50", and every base64 data URI). This
     * follows the HTML spec instead — a candidate's URL runs to the next
     * whitespace, and only a comma after that whitespace separates candidates.
     *
     * @param  callable(string): string  $rewrite
     */
    private static function mapSrcset(string $value, callable $rewrite): string
    {
        $length = strlen($value);
        $position = 0;
        $candidates = [];

        while ($position < $length) {
            // Separators between candidates.
            while ($position < $length && (ctype_space($value[$position]) || $value[$position] === ',')) {
                $position++;
            }

            if ($position >= $length) {
                break;
            }

            $start = $position;
            while ($position < $length && ! ctype_space($value[$position])) {
                $position++;
            }
            $url = substr($value, $start, $position - $start);

            $descriptor = '';
            if (str_ends_with($url, ',')) {
                // Trailing comma ends the candidate; it has no descriptor.
                $url = rtrim($url, ',');
            } else {
                while ($position < $length && ctype_space($value[$position])) {
                    $position++;
                }
                $start = $position;
                while ($position < $length && $value[$position] !== ',') {
                    $position++;
                }
                $descriptor = trim(substr($value, $start, $position - $start));
            }

            if ($url === '') {
                continue;
            }

            $url = $rewrite($url);
            $candidates[] = $descriptor === '' ? $url : $url.' '.$descriptor;
        }

        return implode(', ', $candidates);
    }

    private static function isAbsolute(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', $value);
    }

    /**
     * Values that carry their own scheme-independent location and must never be
     * rewritten: inline data and protocol-relative URLs.
     */
    private static function isSchemeAgnostic(string $value): bool
    {
        return str_starts_with($value, '//') || (bool) preg_match('#^data:#i', $value);
    }

    /**
     * Return the root-relative path when an absolute URL points at this
     * application, otherwise null (meaning: a genuinely external asset).
     */
    private static function localPathFromAbsoluteUrl(string $value): ?string
    {
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return null;
        }

        $query = parse_url($value, PHP_URL_QUERY);
        $relative = $path.($query !== null && $query !== '' ? '?'.$query : '');

        if (in_array($host, self::knownHosts(), true)) {
            return $relative;
        }

        // A stale host from another environment (e.g. an old localhost URL kept
        // in the database) is only rewritten when the file is actually one of
        // ours, so a real CDN that happens to serve /storage/ is left alone.
        return self::existsOnPublicDisk($path) ? $relative : null;
    }

    /**
     * Hosts that unambiguously belong to this application.
     *
     * @return array<int, string>
     */
    private static function knownHosts(): array
    {
        $hosts = [
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            strtolower((string) parse_url((string) config('app.asset_url'), PHP_URL_HOST)),
        ];

        if (app()->runningInConsole() === false) {
            $hosts[] = strtolower(request()->getHost());
        }

        return array_values(array_filter(array_unique($hosts)));
    }

    private static function existsOnPublicDisk(string $path): bool
    {
        // Only probe local disks; a remote driver would turn every rendered
        // image into a network round trip.
        if (config('filesystems.disks.public.driver') !== 'local') {
            return false;
        }

        $relative = self::diskRelativePath($path);

        return $relative !== null && Storage::disk('public')->exists($relative);
    }
}
