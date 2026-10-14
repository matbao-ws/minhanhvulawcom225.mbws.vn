<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Redirect;
use App\Services\LanguageRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Old URLs that moved (a site brought over from WordPress, a page renamed by hand) answer with
 * a redirect instead of a 404, so search engines carry their rankings over.
 *
 * Looked up only when no route or record answers a URL (the NotFoundHttpException handler in
 * bootstrap/app.php), so a normal page view costs no query. What still answers 404 is logged
 * for admin/redirects, where a redirect is one click away.
 */
class RedirectService
{
    /** Storefront records an old URL can point at, by the theme route that shows them. */
    public const ENTITY_ROUTES = [
        Post::class => 'client.posts.show',
        Product::class => 'client.products.show',
        Page::class => 'client.pages.show',
        PostCategory::class => 'client.post-categories.show',
        Category::class => 'client.categories.show',
    ];

    /** Rows kept in the 404 log; past this, only URLs already there are counted. */
    public const NOT_FOUND_LOG_LIMIT = 5000;

    /** Not storefront pages: never redirected, never logged. */
    private const SKIPPED_PREFIXES = ['/api', '/admin', '/storage', '/admin-assets', '/client-assets', '/build', '/vendor', '/_'];

    /** What vulnerability scanners probe for: worth neither a lookup nor a log row. */
    private const PROBE_PATTERN = '#(\.(php\d?|asp|aspx|jsp|cgi|env|ini|log|sql|bak|old|swp|git|svn)(/|$))|(^|/)\.|(^|/)(wp-admin|wp-includes|wp-login|xmlrpc|cgi-bin|phpmyadmin)(/|$|\.)#i';

    /** @var array<string, string>|null source path => target, for rewriteLinks() */
    private ?array $linkMap = null;

    public function __construct(private readonly LanguageRegistry $languages) {}

    /**
     * The form every source path is stored and looked up in: decoded, lower case, one leading
     * slash, no trailing slash, query parameters sorted ("/Tin-Tuc/?b=2&a=1" → "/tin-tuc?a=1&b=2").
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'http://placeholder.invalid/'.ltrim($url, '/');
        }
        $parts = parse_url($url) ?: [];
        $path = rawurldecode((string) ($parts['path'] ?? '/'));
        $path = mb_strtolower('/'.trim((string) preg_replace('#/+#', '/', $path), '/'), 'UTF-8');

        $query = (string) ($parts['query'] ?? '');
        if ($query !== '') {
            parse_str($query, $parameters);
            ksort($parameters);
            $query = http_build_query($parameters);
        }

        return $query === '' ? $path : $path.'?'.$query;
    }

    /** The redirect or 410 for a URL nothing answered; logs it as a 404 otherwise. */
    public function handleNotFound(Request $request): ?Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }

        $path = self::normalize($request->getPathInfo());
        if ($this->skipped($path) || $request->expectsJson()) {
            return null;
        }

        try {
            $redirect = $this->find($request);
            if ($redirect && ($response = $this->respond($redirect, $request))) {
                return $response;
            }

            $this->logNotFound($path, $request);
        } catch (QueryException) {
            // Tables not migrated yet (an update between code and migrations): a plain 404.
        }

        return null;
    }

    /** The storefront URL of a record, in the default language; null without its theme route. */
    public function urlFor(Model $model): ?string
    {
        $route = self::ENTITY_ROUTES[$model::class] ?? null;
        if ($route === null || ! Route::has($route) || ! method_exists($model, 'canonicalSlug')) {
            return null;
        }
        $locale = $this->languages->defaultLocale();

        return $this->relative(route($route, ['locale' => $locale, 'slug' => $model->canonicalSlug($locale)]));
    }

    /** Where a redirect sends a visitor now: the record's current URL, else the stored target. */
    public function targetFor(Redirect $redirect): ?string
    {
        $model = $redirect->redirectable_type ? $redirect->redirectable : null;
        if ($model instanceof Model && ($url = $this->urlFor($model)) !== null) {
            return $url;
        }

        return filled($redirect->target_url) ? (string) $redirect->target_url : null;
    }

    /**
     * Records that an imported item's old address now lives at $model (or $targetUrl). Nothing
     * is stored when both addresses are the same, and a redirect an admin made stays as it is.
     */
    public function remember(string $oldUrl, ?Model $model, ?string $targetUrl = null, string $origin = Redirect::ORIGIN_WORDPRESS): ?Redirect
    {
        $source = strtok(self::normalize($oldUrl), '?') ?: '/';
        $target = $targetUrl !== null ? $this->relative($targetUrl) : ($model ? $this->urlFor($model) : null);
        if ($source === '/' || mb_strlen($source) > 500 || ($target === null && $model === null)) {
            return null;
        }

        $existing = Redirect::query()->where('source_path', $source)->first();
        if ($existing && $existing->origin === Redirect::ORIGIN_MANUAL) {
            return $existing;
        }
        if ($target !== null && $this->isLocal($target) && strtok(self::normalize($target), '?') === $source) {
            $existing?->delete();

            return null;
        }

        $this->linkMap = null;

        return Redirect::query()->updateOrCreate(['source_path' => $source], [
            'target_url' => $target === null ? null : Str::limit($target, 2048, ''),
            'redirectable_type' => $model?->getMorphClass(),
            'redirectable_id' => $model?->getKey(),
            'status_code' => 301,
            'origin' => $origin,
            'is_active' => true,
        ]);
    }

    /**
     * Images copied from WordPress keep their old /wp-content/uploads/... address alive, so
     * image search and pages linking to them still land on the picture.
     *
     * @param  array<string, string>  $localized  original URL => local URL
     */
    public function rememberImages(array $localized): int
    {
        $count = 0;
        foreach ($localized as $source => $local) {
            if (str_starts_with(self::normalize($source), '/wp-content/uploads/') && $this->remember($source, null, $local)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Links inside imported content that point at an old address of the same site go straight
     * to the new one, instead of costing a redirect hop on every click and crawl.
     *
     * @param  list<string>  $hosts  hosts of the old site (www or not)
     */
    public function rewriteLinks(string $html, array $hosts): string
    {
        $hosts = array_map(fn (string $host) => preg_replace('/^www\./', '', strtolower($host)), $hosts);

        return (string) preg_replace_callback('/\bhref\s*=\s*(["\'])(.*?)\1/is', function (array $match) use ($hosts): string {
            $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            $internal = str_starts_with($href, '/') && ! str_starts_with($href, '//');
            if (! $internal && ($host === '' || ! in_array(preg_replace('/^www\./', '', $host), $hosts, true))) {
                return $match[0];
            }

            $target = $this->linkMap()[strtok(self::normalize($href), '?') ?: '/'] ?? null;
            if ($target === null) {
                return $match[0];
            }
            $fragment = parse_url($href, PHP_URL_FRAGMENT);

            return 'href='.$match[1].e($target.($fragment ? '#'.$fragment : '')).$match[1];
        }, $html);
    }

    /** Removes 404 log rows not seen for $days days. */
    public function pruneNotFound(int $days = 90): int
    {
        return NotFoundLog::query()->where('last_seen_at', '<', now()->subDays($days))->delete();
    }

    private function skipped(string $path): bool
    {
        $segments = explode('/', ltrim($path, '/'));
        // /{locale}/admin/... is the admin too.
        if (count($segments) > 1 && $segments[1] === 'admin' && $this->languages->supports($segments[0])) {
            return true;
        }
        foreach (self::SKIPPED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/') || ($prefix === '/_' && str_starts_with($path, '/_'))) {
                return true;
            }
        }

        return preg_match(self::PROBE_PATTERN, $path) === 1;
    }

    private function find(Request $request): ?Redirect
    {
        $full = self::normalize($request->getRequestUri());
        $candidates = array_values(array_unique([$full, strtok($full, '?') ?: '/']));

        // A rule written for one query string wins over the rule for the bare path.
        return Redirect::query()->whereIn('source_path', $candidates)->where('is_active', true)->get()
            ->sortByDesc(fn (Redirect $redirect) => str_contains($redirect->source_path, '?'))
            ->first();
    }

    private function respond(Redirect $redirect, Request $request): ?Response
    {
        if ($redirect->status_code === 410) {
            $this->hit($redirect);

            return response()->view('errors.404', ['exception' => new NotFoundHttpException], 410);
        }

        $target = $this->targetFor($redirect);
        if ($target === null) {
            return null;
        }
        $current = self::normalize($request->getRequestUri());
        if ($this->isLocal($target) && strtok(self::normalize($target), '?') === strtok($current, '?')) {
            return null; // pointing at itself: answering would loop
        }

        // utm_* and other parameters ride along unless the rule names its own.
        $query = (string) $request->getQueryString();
        if ($query !== '' && ! str_contains($redirect->source_path, '?') && ! str_contains($target, '?')) {
            $target .= '?'.$query;
        }

        $this->hit($redirect);

        return redirect()->to($target, in_array($redirect->status_code, Redirect::STATUS_CODES, true) ? $redirect->status_code : 301);
    }

    private function hit(Redirect $redirect): void
    {
        Redirect::query()->whereKey($redirect->getKey())->toBase()
            ->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);
    }

    private function logNotFound(string $path, Request $request): void
    {
        if (mb_strlen($path) > 500) {
            return;
        }
        $referer = Str::limit((string) $request->headers->get('referer'), 490, '');
        $now = now();

        $updated = NotFoundLog::query()->where('path', $path)->toBase()->update(array_filter([
            'hits' => DB::raw('hits + 1'),
            'last_seen_at' => $now,
            'updated_at' => $now,
            'referer' => $referer !== '' ? $referer : null,
        ], fn ($value) => $value !== null));

        if ($updated === 0 && NotFoundLog::query()->count() < self::NOT_FOUND_LOG_LIMIT) {
            NotFoundLog::query()->insertOrIgnore([
                'path' => $path,
                'hits' => 1,
                'referer' => $referer !== '' ? $referer : null,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return array<string, string> */
    private function linkMap(): array
    {
        if ($this->linkMap !== null) {
            return $this->linkMap;
        }

        $this->linkMap = [];
        Redirect::query()->with('redirectable')->where('is_active', true)->whereIn('status_code', [301, 302, 307])
            ->where('source_path', 'not like', '%?%')->lazy(500)
            ->each(function (Redirect $redirect): void {
                $target = $this->targetFor($redirect);
                if ($target !== null) {
                    $this->linkMap[$redirect->source_path] = $target;
                }
            });

        return $this->linkMap;
    }

    /** A URL on this site, made relative so it survives a move from staging to the live domain. */
    private function relative(string $url): string
    {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        $ownHosts = array_map(
            fn (string $own) => preg_replace('/^www\./', '', strtolower((string) parse_url($own, PHP_URL_HOST))),
            [(string) config('app.url'), url('/')],
        );
        if ($host === '' || ! in_array($host, $ownHosts, true)) {
            return $url;
        }
        $path = (string) parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }

    private function isLocal(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }
}
