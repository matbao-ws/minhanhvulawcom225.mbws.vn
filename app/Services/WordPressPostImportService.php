<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostCategory;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;

class WordPressPostImportService
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly WordPressImageImportService $images,
        private readonly WordPressContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: list<string>, images_downloaded: int, images_failed: int, image_errors: list<string>}
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

        $imageReport = $this->images->report();

        return [
            ...$results,
            'images_downloaded' => $imageReport['downloaded'],
            'images_failed' => $imageReport['failed'],
            'image_errors' => $imageReport['errors'],
        ];
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
            if (! $reader->open($filePath, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
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
                $item = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE);
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
            return 'skipped';
        }

        $category = $this->findOrCreateCategory($data['categories'][0] ?? null, $locale);
        $content = $this->htmlSanitizer->clean(
            $this->images->localizeHtml($this->contentNormalizer->normalize((string) $data['content'])),
        );
        $summary = trim(html_entity_decode(strip_tags((string) $data['excerpt']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $meta = $data['meta'];
        $seoTitle = trim((string) ($meta['_yoast_wpseo_title'] ?? ''));
        $seoDescription = trim((string) ($meta['_yoast_wpseo_metadesc'] ?? ''));
        $focusKeyword = trim((string) ($meta['_yoast_wpseo_focuskw'] ?? $meta['_yoast_wpseo_focuskw_text_input'] ?? ''));
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
            'robots_index' => true,
            'robots_follow' => true,
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

        return $result;
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
