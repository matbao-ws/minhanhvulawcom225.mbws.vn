<?php

namespace App\Services\Seo;

use App\Jobs\SubmitIndexNow;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\LanguageRegistry;
use App\Support\Seo\SeoBox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * IndexNow (config/seo.php): the storefront URLs of posts, products and policy pages that were
 * published, changed or removed in a request are sent once, at the end of it — an import of
 * five hundred posts is one submission, not five hundred.
 *
 * The key is derived from APP_KEY, so there is nothing to store, and /{key}.txt proves to the
 * search engines that the site owns it (SeoFileController::indexNowKey).
 */
class IndexNow
{
    /** IndexNow's own limit per submission. */
    private const MAX_URLS = 10000;

    /** @var array<string, true> */
    private array $pending = [];

    private bool $flushRegistered = false;

    public function key(): string
    {
        return substr(hash_hmac('sha256', 'indexnow', (string) config('app.key')), 0, 32);
    }

    public function enabled(): bool
    {
        return (bool) config('seo.indexnow', true) && app()->isProduction();
    }

    /** After a record's save or delete: its storefront URL, if search engines should know of it. */
    public function recordChanged(Model $record, bool $deleted = false): void
    {
        if (! $this->enabled() || ! $this->concernsSearch($record, $deleted)) {
            return;
        }

        $route = SeoBox::MODELS[$record::class]['route'] ?? null;
        if ($route === null || ! Route::has($route) || ! method_exists($record, 'canonicalSlug')) {
            return;
        }

        $locale = app(LanguageRegistry::class)->defaultLocale();
        $this->pending[route($route, ['locale' => $locale, 'slug' => $record->canonicalSlug($locale)])] = true;

        if (! $this->flushRegistered) {
            $this->flushRegistered = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    /** Sends what this request changed (queued; sync hosting runs it after the response). */
    public function flush(): void
    {
        $urls = array_keys($this->pending);
        $this->pending = [];
        foreach (array_chunk($urls, self::MAX_URLS) as $chunk) {
            SubmitIndexNow::dispatch($chunk);
        }
    }

    /** @param list<string> $urls */
    public function submit(array $urls): void
    {
        if ($urls === []) {
            return;
        }

        try {
            $response = Http::connectTimeout(5)->timeout(10)->acceptJson()->post((string) config('seo.indexnow_endpoint'), [
                'host' => parse_url(url('/'), PHP_URL_HOST),
                'key' => $this->key(),
                'keyLocation' => url($this->key().'.txt'),
                'urlList' => $urls,
            ]);
            // 200/202 accepted; 4xx means a key or host problem worth a line in the log.
            if (! $response->successful()) {
                Log::warning('IndexNow refused the submission', ['status' => $response->status(), 'urls' => count($urls)]);
            }
        } catch (Throwable $exception) {
            Log::info('IndexNow unreachable', ['error' => $exception->getMessage()]);
        }
    }

    /** Public, indexable, and actually changed (or just removed) — drafts and noindex stay quiet. */
    private function concernsSearch(Model $record, bool $deleted): bool
    {
        if (! in_array($record::class, [Post::class, Product::class, Page::class], true)) {
            return false;
        }
        $public = (bool) $record->getAttribute('is_active') && $record->getAttribute('robots_index') !== false;
        if ($deleted) {
            return $public;
        }
        // Unpublished just now: the URL now answers 404, which search engines should learn too.
        $wasPublic = (bool) $record->getOriginal('is_active');

        return ($public || $wasPublic) && ($record->wasRecentlyCreated || $record->wasChanged());
    }
}
