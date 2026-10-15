<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PackageLimitExceeded;
use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Admin\Concerns\OpensAiToolAfterCreate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WordPressPostImportRequest;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\ActivityLogger;
use App\Services\CloudinaryService;
use App\Services\LanguageRegistry;
use App\Services\LocalizedSlugService;
use App\Services\Packages\PackageLimits;
use App\Services\PostSeoAnalyzer;
use App\Services\SiteBranding;
use App\Services\WordPressPostImportService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    use HandlesBulkActions, OpensAiToolAfterCreate;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly SiteBranding $siteBranding,
    ) {}

    /**
     * Display a listing of posts.
     */
    public function index(Request $request)
    {
        $posts = Post::query()
            ->with('category')
            ->when($request->query('q'), function ($query, $keyword) {
                $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%");
            })
            ->when($request->query('category_id'), function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->filled('status'), function ($query) {
                $query->where('is_active', request('status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = $this->categoryOptions();

        return view('admin.posts.index', compact('posts', 'categories'));
    }

    /**
     * Show the form for creating a new post.
     */
    public function create()
    {
        $post = new Post([
            'is_active' => true,
        ]);
        $categories = $this->categoryOptions();

        return view('admin.posts.create', [
            'post' => $post,
            'categories' => $categories,
            'siteBranding' => $this->siteBranding->current(),
        ]);
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request)
    {
        $this->normalizeLegacyInput($request);
        $validated = $request->validate($this->rules());
        $titles = $this->cleanTranslations($validated['title']);
        $submittedSlugs = $this->cleanTranslations($validated['slug'] ?? []);
        $baseSlug = $submittedSlugs[$this->languages->defaultLocale()] ?? $titles[$this->languages->defaultLocale()];
        $legacySlug = $this->uniqueLegacySlug($baseSlug);
        $contents = $this->cleanTranslations($validated['content'], true);
        $seoTitles = $this->cleanTranslations($validated['seo_title'] ?? []);
        $seoDescriptions = $this->cleanTranslations($validated['seo_description'] ?? []);
        $seoAnalysis = $this->analyzeSeo($titles, $contents, $seoTitles, $seoDescriptions, $legacySlug, $validated['seo_keys'] ?? null);

        $imageUrl = $validated['image_url'] ?? null;
        if ($request->hasFile('image_file')) {
            $cloudinaryService = app(CloudinaryService::class);
            $imageUrl = $cloudinaryService->uploadFile($request->file('image_file'), 'posts');
        }

        $post = DB::transaction(function () use ($validated, $titles, $submittedSlugs, $legacySlug, $contents, $seoTitles, $seoDescriptions, $seoAnalysis, $imageUrl, $request) {
            $active = $request->boolean('is_active');
            $post = Post::query()->create([
                'category_id' => $validated['category_id'] ?? null,
                'title' => $titles,
                'slug' => $legacySlug,
                'summary' => $this->summaries([], $this->cleanTranslations($validated['summary'] ?? []), $contents, $seoDescriptions),
                'content' => $contents,
                'image_url' => $imageUrl,
                'is_active' => $active,
                'seo_title' => $seoTitles,
                'seo_description' => $seoDescriptions,
                'seo_keys' => $validated['seo_keys'] ?? null,
                'og_title' => $this->cleanTranslations($validated['og_title'] ?? []),
                'og_description' => $this->cleanTranslations($validated['og_description'] ?? []),
                'og_image' => $validated['og_image'] ?? null,
                'schema_type' => $validated['schema_type'] ?? null,
                'schema_faq' => $request->has('schema_faq') ? $request->boolean('schema_faq') : true,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'robots_index' => $request->has('robots_index') ? $request->boolean('robots_index') : true,
                'robots_follow' => $request->has('robots_follow') ? $request->boolean('robots_follow') : true,
                'seo_score' => $seoAnalysis['score'],
                'seo_analysis' => $seoAnalysis,
                'published_at' => $active ? now() : null,
            ]);
            $this->localizedSlugs->sync($post, $submittedSlugs, $titles);

            return $post;
        });

        return $this->redirectAfterCreate($request, 'admin.posts.edit', $post, redirect()
            ->route('admin.posts.index')
            ->with('success', __('admin.posts.created')));
    }

    /**
     * Display post detail sheet (redirects or standard details).
     */
    public function show(string $locale, Post $post)
    {
        return redirect()->route('admin.posts.edit', $post);
    }

    /**
     * Show the form for editing the post.
     */
    public function edit(string $locale, Post $post)
    {
        $categories = $this->categoryOptions();

        return view('admin.posts.edit', [
            'post' => $post,
            'categories' => $categories,
            'siteBranding' => $this->siteBranding->current(),
        ]);
    }

    /**
     * Update the post.
     */
    public function update(Request $request, string $locale, Post $post)
    {
        $this->normalizeLegacyInput($request);
        $validated = $request->validate($this->rules());
        $titles = $this->mergeTranslations($post, 'title', $validated['title']);
        $submittedSlugs = $this->cleanTranslations($validated['slug'] ?? []);
        $baseSlug = $submittedSlugs[$this->languages->defaultLocale()] ?? $post->slug;
        $legacySlug = $this->uniqueLegacySlug($baseSlug, $post->id);
        $contents = $this->mergeTranslations($post, 'content', $validated['content'], true);
        $seoTitles = $this->mergeTranslations($post, 'seo_title', $validated['seo_title'] ?? []);
        $seoDescriptions = $this->mergeTranslations($post, 'seo_description', $validated['seo_description'] ?? []);
        $seoAnalysis = $this->analyzeSeo($titles, $contents, $seoTitles, $seoDescriptions, $legacySlug, $validated['seo_keys'] ?? null);

        $imageUrl = ($validated['image_url'] ?? null) ?: $post->image_url;
        if ($request->hasFile('image_file')) {
            $cloudinaryService = app(CloudinaryService::class);
            $imageUrl = $cloudinaryService->uploadFile($request->file('image_file'), 'posts');
        }

        DB::transaction(function () use ($post, $validated, $titles, $submittedSlugs, $legacySlug, $contents, $seoTitles, $seoDescriptions, $seoAnalysis, $imageUrl, $request): void {
            $active = $request->boolean('is_active');
            $post->update([
                'category_id' => $validated['category_id'] ?? null,
                'title' => $titles,
                'slug' => $legacySlug,
                'summary' => $this->summaries(
                    $post->getTranslations('summary'),
                    $this->cleanTranslations($validated['summary'] ?? []),
                    $contents,
                    $seoDescriptions,
                    $post->getTranslations('content'),
                    $post->getTranslations('seo_description'),
                ),
                'content' => $contents,
                'image_url' => $imageUrl,
                'is_active' => $active,
                'seo_title' => $seoTitles,
                'seo_description' => $seoDescriptions,
                'seo_keys' => $validated['seo_keys'] ?? null,
                // Sent emptied, a field goes back to following the SEO title/description.
                'og_title' => $request->has('og_title') ? $this->replaceTranslations($post, 'og_title', $validated['og_title'] ?? []) : $post->getTranslations('og_title'),
                'og_description' => $request->has('og_description') ? $this->replaceTranslations($post, 'og_description', $validated['og_description'] ?? []) : $post->getTranslations('og_description'),
                'og_image' => $request->has('og_image') ? ($validated['og_image'] ?? null) : $post->getRawOriginal('og_image'),
                'schema_type' => $request->has('schema_type') ? ($validated['schema_type'] ?? null) : $post->schema_type,
                'schema_faq' => $request->has('schema_faq') ? $request->boolean('schema_faq') : $post->schema_faq,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'robots_index' => $request->has('robots_index') ? $request->boolean('robots_index') : $post->robots_index,
                'robots_follow' => $request->has('robots_follow') ? $request->boolean('robots_follow') : $post->robots_follow,
                'seo_score' => $seoAnalysis['score'],
                'seo_analysis' => $seoAnalysis,
                'published_at' => $active ? ($post->published_at ?: now()) : null,
            ]);
            $this->localizedSlugs->sync($post, $submittedSlugs, $titles);
        });

        return redirect()
            ->route('admin.posts.index')
            ->with('success', __('admin.posts.updated'));
    }

    /**
     * Remove the post.
     */
    public function destroy(string $locale, Post $post)
    {
        $post->delete();

        return redirect()
            ->route('admin.posts.index')
            ->with('success', __('admin.posts.deleted'));
    }

    public function bulk(Request $request, string $locale)
    {
        $validated = $this->validatedBulkAction($request, 'posts', 'posts');
        $ids = $validated['ids'];

        if ($validated['action'] === 'delete') {
            $deleted = DB::transaction(function () use ($ids): int {
                $posts = Post::query()->whereIn('id', $ids)->lockForUpdate()->get();
                $posts->each->delete();

                return $posts->count();
            });
            ActivityLogger::log('bulk_deleted', null, 'Xóa hàng loạt bài viết', [
                'model' => Post::class,
                'ids' => $ids,
                'count' => $deleted,
            ]);

            return back()->with('success', 'Đã xóa '.$deleted.' bài viết.');
        }

        $isActive = $validated['action'] === 'activate';
        $updated = Post::query()->whereIn('id', $ids)->update([
            'is_active' => $isActive,
            'published_at' => $isActive ? DB::raw('COALESCE(published_at, CURRENT_TIMESTAMP)') : null,
        ]);
        ActivityLogger::log('bulk_status_changed', null, 'Cập nhật trạng thái hàng loạt bài viết', [
            'model' => Post::class,
            'ids' => $ids,
            'count' => $updated,
            'is_active' => $isActive,
        ]);

        return back()->with('success', 'Đã '.($isActive ? 'xuất bản' : 'tạm ẩn').' '.$updated.' bài viết.');
    }

    public function importWordPress(WordPressPostImportRequest $request, string $locale, WordPressPostImportService $importer)
    {
        $validated = $request->validated();
        try {
            $results = $importer->import(
                $request->file('import_file')->getPathname(),
                $validated['duplicate_action'],
            );
        } catch (\Throwable $exception) {
            report($exception);

            // The importer's own RuntimeExceptions explain the file; anything else (SQL, HTTP…) would
            // show schema or internal detail, so it stays in the log.
            $reason = $exception::class === \RuntimeException::class ? $exception->getMessage() : 'Lỗi hệ thống, xem log để biết chi tiết.';

            return back()->with('error', 'Import WordPress thất bại: '.$reason);
        }

        if ($results['imported'] + $results['updated'] + $results['skipped'] + $results['failed'] === 0) {
            // Nothing in the file is a post: say what it holds instead of a row of zeros.
            $found = collect($results['other_types'])->map(fn (int $count, string $type) => "$count $type")->implode(', ');

            return back()->with('error', $found === ''
                ? 'File WordPress này không có bài viết nào.'
                : "File WordPress này không có bài viết. File chứa: $found. Sản phẩm WooCommerce nhập ở Sản phẩm → Nhập (file CSV).");
        }

        ActivityLogger::log('wordpress_posts_imported', null, 'Import bài viết từ WordPress XML', [
            'imported' => $results['imported'],
            'updated' => $results['updated'],
            'skipped' => $results['skipped'],
            'failed' => $results['failed'],
            'images_downloaded' => $results['images_downloaded'],
            'images_failed' => $results['images_failed'],
            'redirects' => $results['redirects'] + $results['image_redirects'],
            'links_rewritten' => $results['links_rewritten'],
        ]);

        $message = "Import WordPress hoàn tất: {$results['imported']} bài mới, {$results['updated']} cập nhật, {$results['skipped']} bỏ qua, {$results['failed']} lỗi; {$results['images_downloaded']} ảnh đã lưu local, {$results['images_failed']} ảnh tải lỗi."
            // Old WordPress addresses keep their rankings (admin/redirects).
            .' Đã tạo '.($results['redirects'] + $results['image_redirects']).' chuyển hướng 301 từ link cũ, sửa link nội bộ trong '.$results['links_rewritten'].' bài.';

        if ($results['failed'] > 0 || $results['images_failed'] > 0) {
            $response = back()->with('warning', $message)->with('import_errors', [
                ...$results['errors'],
                ...array_map(fn (string $error): string => 'Ảnh: '.$error, $results['image_errors']),
            ]);

            // Posts refused by the package limit get the blocking dialog, not just a line in the error list.
            if ($limitHit = app(PackageLimits::class)->lastExceeded()) {
                $response->with(PackageLimitExceeded::FLASH_KEY, $limitHit->getMessage());
            }

            return $response;
        }

        return back()->with('success', $message);
    }

    /**
     * Helper to list hierarchy categories options
     */
    private function categoryOptions()
    {
        $allCategories = PostCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $grouped = $allCategories->groupBy('parent_id');
        $rootCategories = $allCategories->whereNull('parent_id');

        $flatOptions = collect();

        $flatten = function ($categories, $depth = 0) use (&$flatten, &$flatOptions, $grouped) {
            foreach ($categories as $category) {
                $category->depth = $depth;
                $flatOptions->push($category);

                $children = $grouped->get($category->id) ?? collect();
                if ($children->isNotEmpty()) {
                    $flatten($children, $depth + 1);
                }
            }
        };

        $flatten($rootCategories);

        return $flatOptions;
    }

    private function rules(): array
    {
        $rules = [
            'title' => ['required', 'array'], 'slug' => ['nullable', 'array'],
            'summary' => ['nullable', 'array'], 'content' => ['required', 'array'],
            'seo_title' => ['nullable', 'array'], 'seo_description' => ['nullable', 'array'],
            'category_id' => ['nullable', 'exists:post_categories,id'], 'image_file' => ['nullable', 'image', 'max:2048'], 'image_url' => ['nullable', 'string', 'max:255'],
            'seo_keys' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots_index' => ['nullable', 'boolean'],
            'robots_follow' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'og_title' => ['nullable', 'array'], 'og_description' => ['nullable', 'array'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'schema_type' => ['nullable', Rule::in(Post::SCHEMA_TYPES)],
            'schema_faq' => ['nullable', 'boolean'],
        ];
        foreach ($this->languages->codes() as $code) {
            $required = $code === $this->languages->defaultLocale() ? 'required' : 'nullable';
            $rules["title.$code"] = [$required, 'string', 'max:255'];
            $rules["slug.$code"] = ['nullable', 'string', 'max:255'];
            $rules["summary.$code"] = ['nullable', 'string'];
            $rules["content.$code"] = [$required, 'string'];
            $rules["seo_title.$code"] = ['nullable', 'string', 'max:255'];
            $rules["seo_description.$code"] = ['nullable', 'string', 'max:500'];
            $rules["og_title.$code"] = ['nullable', 'string', 'max:255'];
            $rules["og_description.$code"] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    private function normalizeLegacyInput(Request $request): void
    {
        $locale = $this->languages->resolve((string) ($request->route('locale') ?: app()->getLocale()));
        foreach (['title', 'slug', 'summary', 'content', 'seo_title', 'seo_description'] as $field) {
            if (is_string($request->input($field))) {
                $values = [$locale => $request->input($field)];
                if ($locale !== $this->languages->defaultLocale()) {
                    $values[$this->languages->defaultLocale()] = $request->input($field);
                }
                $request->merge([$field => $values]);
            }
        }
    }

    private function cleanTranslations(array $values, bool $html = false): array
    {
        return collect($values)->filter(fn ($value, $code) => $this->languages->supports((string) $code) && is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $html ? $this->htmlSanitizer->cleanRichText(trim($value)) : trim($value))->all();
    }

    /**
     * The summary (post cards, meta description fallback) has no field in the form any more:
     * like WordPress' automatic excerpt it follows the SEO description, else the opening of
     * the content. A summary sent explicitly, typed earlier or imported from WordPress stays.
     *
     * @param  array<string, string>  $stored
     * @param  array<string, string>  $submitted
     * @param  array<string, string>  $contents
     * @param  array<string, string>  $seoDescriptions
     * @param  array<string, string>  $oldContents
     * @param  array<string, string>  $oldSeoDescriptions
     * @return array<string, string>
     */
    private function summaries(array $stored, array $submitted, array $contents, array $seoDescriptions, array $oldContents = [], array $oldSeoDescriptions = []): array
    {
        $summaries = [...$stored, ...$submitted];
        foreach ($contents as $locale => $html) {
            if (isset($submitted[$locale])) {
                continue;
            }
            $current = trim((string) ($summaries[$locale] ?? ''));
            // Still the automatic one from the last save: follow the new content.
            if ($current === '' || $current === $this->autoSummary($oldSeoDescriptions[$locale] ?? null, $oldContents[$locale] ?? null)) {
                $summaries[$locale] = $this->autoSummary($seoDescriptions[$locale] ?? null, $html);
            }
        }

        return array_filter($summaries, fn (string $summary) => trim($summary) !== '');
    }

    private function autoSummary(?string $seoDescription, ?string $html): string
    {
        $text = trim((string) $seoDescription);
        if ($text === '') {
            $plain = html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', (string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim((string) preg_replace('/\s+/u', ' ', $plain));
        }

        return Str::limit($text, 200, '…');
    }

    /**
     * Like mergeTranslations(), but a language sent empty is cleared instead of kept: optional
     * fields whose emptiness means "use the default".
     */
    private function replaceTranslations(Post $post, string $field, array $values): array
    {
        $current = $post->getTranslations($field);
        foreach ($values as $code => $value) {
            if (! $this->languages->supports((string) $code)) {
                continue;
            }
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                unset($current[$code]);
            } else {
                $current[$code] = $value;
            }
        }

        return $current;
    }

    private function mergeTranslations(Post $post, string $field, array $values, bool $html = false): array
    {
        return [...$post->getTranslations($field), ...$this->cleanTranslations($values, $html)];
    }

    private function uniqueLegacySlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $counter = 2;
        while (Post::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, string>  $titles
     * @param  array<string, string>  $contents
     * @param  array<string, string>  $seoTitles
     * @param  array<string, string>  $seoDescriptions
     */
    private function analyzeSeo(
        array $titles,
        array $contents,
        array $seoTitles,
        array $seoDescriptions,
        string $slug,
        ?string $focusKeyword,
    ): array {
        $locale = $this->languages->defaultLocale();

        return $this->seoAnalyzer->analyze([
            'title' => $titles[$locale] ?? (reset($titles) ?: ''),
            'slug' => $slug,
            'content' => $contents[$locale] ?? (reset($contents) ?: ''),
            'seo_title' => $seoTitles[$locale] ?? '',
            'seo_description' => $seoDescriptions[$locale] ?? '',
            'focus_keyword' => $focusKeyword,
        ]);
    }
}
