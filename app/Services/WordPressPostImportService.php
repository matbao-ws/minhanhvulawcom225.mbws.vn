<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostCategory;
use App\Services\Seo\RedirectService;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;

class WordPressPostImportService
{
    /** @var array{name: string, description: string, hosts: list<string>} the WordPress site being imported */
    private array $site = ['name' => '', 'description' => '', 'hosts' => []];

    /** @var list<int> posts written by this batch, whose links finishBatch() rewrites */
    private array $touched = [];

    private int $redirectCount = 0;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly WordPressImageImportService $images,
        private readonly WordPressContentNormalizer $contentNormalizer,
        private readonly RedirectService $redirects,
        private readonly WordPressSeoMeta $seoMeta,
    ) {}

    /**
     * Starts an import of one WordPress site: its name fills the SEO title templates, its hosts
     * tell which links inside posts are internal.
     *
     * @param  array{name?: string, description?: string, hosts?: list<string>}  $site
     */
    public function startBatch(array $site = []): void
    {
        $this->site = [
            'name' => (string) ($site['name'] ?? ''),
            'description' => (string) ($site['description'] ?? ''),
            'hosts' => array_values(array_filter($site['hosts'] ?? [])),
        ];
        $this->touched = [];
        $this->redirectCount = 0;
    }

    /**
     * After every item of the batch is in: old image addresses redirect to the copies, and links
     * between imported posts point straight at the new URLs.
     *
     * @return array{redirects: int, image_redirects: int, links_rewritten: int}
     */
    public function finishBatch(): array
    {
        $imageRedirects = $this->redirects->rememberImages($this->images->localizedUrls());
        $rewritten = 0;

        Post::query()->whereKey($this->touched)->orderBy('id')->each(function (Post $post) use (&$rewritten): void {
            $contents = $post->getTranslations('content');
            $changed = false;
            foreach ($contents as $locale => $html) {
                $linked = $this->redirects->rewriteLinks((string) $html, $this->site['hosts']);
                if ($linked !== $html) {
                    $contents[$locale] = $linked;
                    $changed = true;
                }
            }
            if ($changed) {
                $post->update(['content' => $contents]);
                $rewritten++;
            }
        });

        return ['redirects' => $this->redirectCount, 'image_redirects' => $imageRedirects, 'links_rewritten' => $rewritten];
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: list<string>, images_downloaded: int, images_failed: int, image_errors: list<string>, redirects: int, image_redirects: int, links_rewritten: int}
     */
    public function import(string $filePath, string $duplicateAction = 'skip'): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('Không thể đọc file XML đã tải lên.');
        }

        if (! in_array($duplicateAction, ['skip', 'update'], true)) {
            throw new RuntimeException('Cách xử lý bài viết trùng không hợp lệ.');
        }

        $this->images->reset();
        $this->startBatch($this->channelInfo($filePath));
        $attachments = $this->attachmentMap($filePath);
        $results = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $postNumber = 0;

        $this->eachItem($filePath, function (SimpleXMLElement $item) use ($attachments, $duplicateAction, &$results, &$postNumber): void {
            $data = $this->itemData($item);
            if ($data['post_type'] !== 'post') {
                return;
            }

            $postNumber++;
            if ($data['title'] === '' || in_array($data['status'], ['trash', 'auto-draft', 'inherit'], true)) {
                $results['skipped']++;

                return;
            }

            try {
                $result = DB::transaction(fn (): string => $this->persistPost($data, $attachments, $duplicateAction));
                $results[$result]++;
            } catch (Throwable $exception) {
                $results['failed']++;
                $results['errors'][] = 'Bài '.($data['title'] ?: '#'.$postNumber).': '.$exception->getMessage();
            }
        });

        $finished = $this->finishBatch();
        $imageReport = $this->images->report();

        return [
            ...$results,
            ...$finished,
            'images_downloaded' => $imageReport['downloaded'],
            'images_failed' => $imageReport['failed'],
            'image_errors' => $imageReport['errors'],
        ];
    }

    /**
     * One post from the WordPress REST API (`/wp/v2/posts?_embed`), stored exactly like a WXR
     * item: same normalizer, image localization, Yoast meta, SEO score and slug.
     *
     * @param  array<string, mixed>  $post
     * @return string imported | updated | skipped
     */
    public function importRestPost(array $post, string $duplicateAction = 'skip'): string
    {
        $embedded = (array) ($post['_embedded'] ?? []);
        $categories = collect((array) ($embedded['wp:term'] ?? []))->flatten(1)
            ->filter(fn ($term) => is_array($term) && ($term['taxonomy'] ?? '') === 'category')
            ->map(fn (array $term) => ['name' => html_entity_decode((string) ($term['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'slug' => rawurldecode((string) ($term['slug'] ?? '')), 'link' => (string) ($term['link'] ?? '')])
            ->values()->all();
        $featuredId = (string) ($post['featured_media'] ?? '');
        $featuredUrl = (string) ($embedded['wp:featuredmedia'][0]['source_url'] ?? '');
        $link = (string) ($post['link'] ?? '');
        // Rendered by Yoast, or by Rank Math's headless getHead (WordPressSiteImportService).
        $head = (array) ($post['seo_head'] ?? $post['yoast_head_json'] ?? []);

        $data = [
            'post_id' => (string) ($post['id'] ?? ''),
            'post_type' => 'post',
            // The public API lists published posts only.
            'status' => (string) ($post['status'] ?? 'publish'),
            'slug' => rawurldecode((string) ($post['slug'] ?? '')),
            'post_date' => (string) ($post['date_gmt'] ?? $post['date'] ?? ''),
            'post_password' => ! empty($post['content']['protected']) ? 'protected' : '',
            'attachment_url' => '',
            'title' => trim(html_entity_decode(strip_tags((string) ($post['title']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'content' => (string) ($post['content']['rendered'] ?? ''),
            'excerpt' => (string) ($post['excerpt']['rendered'] ?? ''),
            'categories' => $categories,
            'link' => $link,
            'meta' => array_filter(['_thumbnail_id' => $featuredId]),
            'seo' => $head === [] ? null : $this->seoMeta->fromHead($head, $link),
        ];

        if ($data['title'] === '') {
            return 'skipped';
        }

        $attachments = $featuredId !== '' && ($url = $this->safeUrl($featuredUrl)) !== null ? [$featuredId => $url] : [];

        return DB::transaction(fn (): string => $this->persistPost($data, $attachments, $duplicateAction));
    }

    /** @return array<string, string> */
    private function attachmentMap(string $filePath): array
    {
        $attachments = [];

        $this->eachItem($filePath, function (SimpleXMLElement $item) use (&$attachments): void {
            $data = $this->itemData($item);
            if ($data['post_type'] !== 'attachment' || $data['post_id'] === '') {
                return;
            }

            $url = $this->safeUrl($data['attachment_url']);
            if ($url !== null) {
                $attachments[$data['post_id']] = $url;
            }
        });

        return $attachments;
    }

    private function eachItem(string $filePath, callable $callback): void
    {
        $reader = new XMLReader;
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            // No LIBXML_PARSEHUGE: libxml's own size limits are what stop a crafted export from exhausting memory.
            if (! $reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('File XML không hợp lệ hoặc không thể mở.');
            }

            $rootFound = false;
            $rootClosed = false;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === 0) {
                    $rootFound = $reader->localName === 'rss';
                    if (! $rootFound) {
                        throw new RuntimeException('Đây không phải file WordPress WXR hợp lệ.');
                    }
                }

                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === 0 && $reader->localName === 'rss') {
                    $rootClosed = true;
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'item') {
                    continue;
                }

                $xml = $reader->readOuterXml();
                libxml_clear_errors();
                $item = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                if (! $item) {
                    throw new RuntimeException('Không thể đọc một mục trong file WordPress XML. '.$this->xmlErrorMessage());
                }
                libxml_clear_errors();

                $callback($item);
                // HTMLPurifier and other callbacks also use libxml. Their warnings are
                // unrelated to the WXR stream and must not invalidate the uploaded XML.
                libxml_clear_errors();
            }

            if (! $rootFound) {
                throw new RuntimeException('File XML rỗng hoặc không đúng định dạng WordPress WXR.');
            }

            if (! $rootClosed) {
                throw new RuntimeException('File WordPress XML bị thiếu phần kết thúc hoặc đã bị cắt. '.$this->xmlErrorMessage());
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    private function xmlErrorMessage(): string
    {
        $errors = libxml_get_errors();
        if ($errors === []) {
            return '';
        }

        $error = end($errors);
        $message = trim((string) $error->message);

        return $message === '' ? '' : 'Chi tiết: '.$message;
    }

    /** @return array<string, mixed> */
    private function itemData(SimpleXMLElement $item): array
    {
        $namespaces = $item->getNamespaces(true);
        $wp = isset($namespaces['wp']) ? $item->children($namespaces['wp']) : null;
        $content = isset($namespaces['content']) ? $item->children($namespaces['content']) : null;
        $excerpt = isset($namespaces['excerpt']) ? $item->children($namespaces['excerpt']) : null;
        $meta = [];

        if ($wp) {
            foreach ($wp->postmeta as $postMeta) {
                $key = trim((string) $postMeta->meta_key);
                if ($key !== '') {
                    $meta[$key] = (string) $postMeta->meta_value;
                }
            }
        }

        $categories = [];
        foreach ($item->category as $category) {
            if ((string) $category['domain'] !== 'category') {
                continue;
            }

            $name = trim((string) $category);
            if ($name !== '') {
                $categories[] = [
                    'name' => $name,
                    'slug' => trim((string) $category['nicename']),
                ];
            }
        }

        return [
            'post_id' => trim((string) ($wp?->post_id ?? '')),
            'post_type' => trim((string) ($wp?->post_type ?? 'post')),
            'status' => trim((string) ($wp?->status ?? 'draft')),
            'slug' => trim((string) ($wp?->post_name ?? '')),
            'post_date' => trim((string) ($wp?->post_date_gmt ?? $wp?->post_date ?? '')),
            'post_password' => trim((string) ($wp?->post_password ?? '')),
            'attachment_url' => trim((string) ($wp?->attachment_url ?? '')),
            'title' => trim((string) $item->title),
            'link' => trim((string) $item->link),
            'content' => (string) ($content?->encoded ?? ''),
            'excerpt' => (string) ($excerpt?->encoded ?? ''),
            'categories' => $categories,
            'meta' => $meta,
        ];
    }

    /** @param array<string, mixed> $data @param array<string, string> $attachments */
    private function persistPost(array $data, array $attachments, string $duplicateAction): string
    {
        $locale = $this->languages->defaultLocale();
        $slug = Str::slug((string) ($data['slug'] ?: $data['title'])) ?: Str::lower(Str::random(8));
        $existing = Post::query()->where('slug', $slug)->lockForUpdate()->first();

        if ($existing && $duplicateAction === 'skip') {
            // Already here: its old address still has to lead to it.
            $this->rememberUrl((string) ($data['link'] ?? ''), $existing);

            return 'skipped';
        }

        $category = $this->findOrCreateCategory($data['categories'][0] ?? null, $locale);
        $content = $this->htmlSanitizer->clean(
            $this->images->localizeHtml($this->contentNormalizer->normalize((string) $data['content'])),
        );
        $summary = trim(html_entity_decode(strip_tags((string) $data['excerpt']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $meta = $data['meta'];
        // Rank Math or Yoast: the title and description Google showed, keywords, canonical, robots.
        $seo = $data['seo'] ?? $this->seoMeta->fromPostMeta($meta, [
            'title' => (string) $data['title'],
            'excerpt' => Str::limit($summary, 156, ''),
            'site_name' => $this->site['name'],
            'site_description' => $this->site['description'],
            'category' => (string) ($data['categories'][0]['name'] ?? ''),
            'date' => (string) $data['post_date'],
            'link' => (string) ($data['link'] ?? ''),
        ]);
        $seoTitle = $seo['title'];
        $seoDescription = $seo['description'];
        $focusKeyword = $seo['keywords'];
        $thumbnailId = trim((string) ($meta['_thumbnail_id'] ?? ''));
        $sourceImageUrl = $attachments[$thumbnailId] ?? $this->firstImageUrl($content);
        $imageUrl = $this->images->localize($sourceImageUrl);
        $active = $data['status'] === 'publish' && $data['post_password'] === '';
        $publishedAt = $active ? $this->publishedAt((string) $data['post_date']) : null;
        $analysis = $this->seoAnalyzer->analyze([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'focus_keyword' => $focusKeyword,
        ]);

        $payload = [
            'category_id' => $category?->id,
            'title' => [$locale => Str::limit((string) $data['title'], 255, '')],
            'slug' => $slug,
            'summary' => $summary === '' ? [] : [$locale => $summary],
            'content' => [$locale => $content],
            'image_url' => $imageUrl,
            'is_active' => $active,
            'seo_title' => $seoTitle === '' ? [] : [$locale => Str::limit($seoTitle, 255, '')],
            'seo_description' => $seoDescription === '' ? [] : [$locale => Str::limit($seoDescription, 500, '')],
            'seo_keys' => $focusKeyword === '' ? null : Str::limit($focusKeyword, 255, ''),
            'canonical_url' => $seo['canonical'],
            'robots_index' => $seo['index'],
            'robots_follow' => $seo['follow'],
            'seo_score' => $analysis['score'],
            'seo_analysis' => $analysis,
            'published_at' => $publishedAt,
        ];

        if ($existing) {
            $existing->update($payload);
            $post = $existing;
            $result = 'updated';
        } else {
            $post = Post::query()->create($payload);
            $result = 'imported';
        }

        $this->localizedSlugs->sync($post, [$locale => $slug], [$locale => (string) $data['title']]);
        $this->touched[] = (int) $post->getKey();
        $this->rememberUrl((string) ($data['link'] ?? ''), $post);
        if ($category) {
            // WXR names the category but not its URL: WordPress's default /category/{slug}/.
            $categoryData = (array) $data['categories'][0];
            $this->rememberUrl((string) ($categoryData['link'] ?? '') ?: '/category/'.rawurlencode((string) ($categoryData['slug'] ?? '')).'/', $category);
        }

        return $result;
    }

    /** The old WordPress address of an item now redirects (301) to where it lives here. */
    public function rememberUrl(string $oldUrl, Model $model): void
    {
        if (trim($oldUrl) !== '' && $this->redirects->remember($oldUrl, $model)) {
            $this->redirectCount++;
        }
    }

    /**
     * The site name and address from a WXR export's <channel>: titles use the name, links to the
     * address are the site's own.
     *
     * @return array{name: string, description: string, hosts: list<string>}
     */
    private function channelInfo(string $filePath): array
    {
        $info = ['name' => '', 'description' => '', 'hosts' => []];
        $reader = new XMLReader;
        $previousErrors = libxml_use_internal_errors(true);

        try {
            if (! $reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT)) {
                return $info;
            }
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->depth !== 2) {
                    continue;
                }
                if ($reader->localName === 'item') {
                    break; // channel fields come before the items
                }
                $value = trim($reader->readString());
                match ($reader->localName) {
                    'title' => $info['name'] = $value,
                    'description' => $info['description'] = $value,
                    'link', 'base_site_url', 'base_blog_url' => $info['hosts'][] = strtolower((string) parse_url($value, PHP_URL_HOST)),
                    default => null,
                };
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $info['hosts'] = array_values(array_unique(array_filter($info['hosts'])));

        return $info;
    }

    /** @param array{name?: string, slug?: string}|null $data */
    private function findOrCreateCategory(?array $data, string $locale): ?PostCategory
    {
        if (! $data || trim((string) ($data['name'] ?? '')) === '') {
            return null;
        }

        $name = trim((string) $data['name']);
        $slug = Str::slug((string) ($data['slug'] ?: $name)) ?: Str::lower(Str::random(8));
        $category = PostCategory::query()->where('slug', $slug)->first();
        if ($category) {
            return $category;
        }

        $category = PostCategory::query()->create([
            'name' => [$locale => Str::limit($name, 255, '')],
            'slug' => $slug,
            'description' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->localizedSlugs->sync($category, [$locale => $slug], [$locale => $name]);

        return $category;
    }

    private function firstImageUrl(string $content): ?string
    {
        if (preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/i', $content, $matches) !== 1) {
            return null;
        }

        return $this->safeUrl($matches[2]);
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if (strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private function publishedAt(string $value): Carbon
    {
        try {
            return trim($value) === '' || str_starts_with($value, '0000-00-00') ? now() : Carbon::parse($value);
        } catch (Throwable) {
            return now();
        }
    }
}
