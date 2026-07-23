<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostCategoryRequest;
use App\Models\PostCategory;
use App\Services\LanguageRegistry;
use App\Services\LocalizedSlugService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PostCategoryController extends Controller
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    /**
     * Display a listing of categories.
     */
    public function index(Request $request)
    {
        // Paginate root categories
        $rootCategories = PostCategory::query()
            ->whereNull('parent_id')
            ->when($request->query('q'), function ($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                      ->orWhere('slug', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        // Get all categories in one query to build tree in memory
        $allCategories = PostCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Group by parent_id
        $grouped = $allCategories->groupBy('parent_id');

        $flatCategories = collect();

        $flatten = function ($categories, $depth = 0) use (&$flatten, &$flatCategories, $grouped) {
            foreach ($categories as $category) {
                $category->depth = $depth;
                $flatCategories->push($category);

                $children = $grouped->get($category->id) ?? collect();
                if ($children->isNotEmpty()) {
                    $flatten($children, $depth + 1);
                }
            }
        };

        // Filter root categories matching current page's root IDs
        $rootIds = $rootCategories->pluck('id')->toArray();
        $rootItems = $allCategories->filter(fn ($c) => in_array($c->id, $rootIds));

        $flatten($rootItems);

        $rootCategories->setCollection($flatCategories);

        return view('admin.posts.categories.index', [
            'categories' => $rootCategories,
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        $category = new PostCategory([
            'is_active' => true,
            'sort_order' => 0,
        ]);
        return view('admin.posts.categories.create', [
            'category' => $category,
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(PostCategoryRequest $request)
    {
        $validated = $request->validated();
        $names = $this->cleanTranslations($validated['name']);
        $slugs = $this->cleanTranslations($validated['slug'] ?? []);
        DB::transaction(function () use ($validated, $names, $slugs, $request): void {
            $category = PostCategory::query()->create([
                'parent_id' => $validated['parent_id'] ?? null,
                'name' => $names,
                'slug' => $this->uniqueLegacySlug($slugs[$this->languages->defaultLocale()] ?? $names[$this->languages->defaultLocale()]),
                'description' => $this->cleanTranslations($validated['description'] ?? [], true),
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
            $this->localizedSlugs->sync($category, $slugs, $names);
        });

        return redirect()
            ->route('admin.post-categories.index')
            ->with('success', __('admin.blog_categories.created'));
    }

    /**
     * Show the form for editing the category.
     */
    public function edit(string $locale, PostCategory $postCategory)
    {
        return view('admin.posts.categories.edit', [
            'category' => $postCategory,
            'parentOptions' => $this->parentOptions($postCategory),
        ]);
    }

    /**
     * Update the category.
     */
    public function update(PostCategoryRequest $request, string $locale, PostCategory $postCategory)
    {
        $validated = $request->validated();
        $this->assertNoParentCycle($postCategory, $validated['parent_id'] ?? null);
        $names = $this->mergeTranslations($postCategory, 'name', $validated['name']);
        $slugs = $this->cleanTranslations($validated['slug'] ?? []);
        DB::transaction(function () use ($postCategory, $validated, $names, $slugs, $request): void {
            $postCategory->update([
                'parent_id' => $validated['parent_id'] ?? null,
                'name' => $names,
                'slug' => $this->uniqueLegacySlug($slugs[$this->languages->defaultLocale()] ?? $postCategory->slug, $postCategory->id),
                'description' => $this->mergeTranslations($postCategory, 'description', $validated['description'] ?? [], true),
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
            $this->localizedSlugs->sync($postCategory, $slugs, $names);
        });

        return redirect()
            ->route('admin.post-categories.index')
            ->with('success', __('admin.blog_categories.updated'));
    }

    /**
     * Quick update for Category (via Quick Edit Modal)
     */
    public function quickUpdate(PostCategoryRequest $request, string $locale, PostCategory $postCategory)
    {
        $validated = $request->validated();
        $this->assertNoParentCycle($postCategory, $validated['parent_id'] ?? null);
        $names = $this->mergeTranslations($postCategory, 'name', $validated['name']);
        $slugs = $this->cleanTranslations($validated['slug'] ?? []);
        DB::transaction(function () use ($postCategory, $validated, $names, $slugs, $request): void {
            $postCategory->update([
                'parent_id' => $validated['parent_id'] ?? null,
                'name' => $names,
                'slug' => $this->uniqueLegacySlug($slugs[$this->languages->defaultLocale()] ?? $postCategory->slug, $postCategory->id),
                'description' => $this->mergeTranslations($postCategory, 'description', $validated['description'] ?? [], true),
                'is_active' => $request->boolean('is_active', true),
            ]);
            $this->localizedSlugs->sync($postCategory, $slugs, $names);
        });

        return redirect()
            ->route('admin.post-categories.index')
            ->with('success', __('admin.blog_categories.updated'));
    }

    /**
     * Reorder categories via AJAX Drag & Drop
     */
    public function sort(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:post_categories,id'],
            'start_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $startOrder = (int) ($validated['start_order'] ?? 0);
        foreach ($validated['ids'] as $index => $id) {
            PostCategory::query()->where('id', $id)->update([
                'sort_order' => $startOrder + $index,
            ]);
        }

        return response()->json([
            'message' => 'Đã thay đổi thứ tự chuyên mục thành công.',
        ]);
    }

    /**
     * Remove the category.
     */
    public function destroy(string $locale, PostCategory $postCategory)
    {
        // Reassign posts belonging to this category to null
        $postCategory->posts()->update(['category_id' => null]);
        
        // Unparent direct children
        $postCategory->children()->update(['parent_id' => null]);

        $postCategory->delete();
        
        return redirect()
            ->route('admin.post-categories.index')
            ->with('success', __('admin.blog_categories.deleted'));
    }

    /**
     * Helper to list hierarchy categories options
     */
    private function parentOptions(?PostCategory $excluded = null)
    {
        $allCategories = PostCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $grouped = $allCategories->groupBy('parent_id');
        $rootCategories = $allCategories->whereNull('parent_id');

        $flatOptions = collect();

        $flatten = function ($categories, $depth = 0) use (&$flatten, &$flatOptions, $grouped, $excluded) {
            foreach ($categories as $category) {
                if ($excluded && $category->id === $excluded->id) {
                    continue;
                }

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

    private function assertNoParentCycle(PostCategory $category, ?int $parentId): void
    {
        $current = $parentId ? PostCategory::find($parentId) : null;
        while ($current) {
            if ($current->is($category)) {
                abort(422, 'Chuyên mục cha không thể là chuyên mục con của chính nó.');
            }
            $current = $current->parent_id ? PostCategory::find($current->parent_id) : null;
        }
    }

    private function cleanTranslations(array $values, bool $html = false): array
    {
        return collect($values)->filter(fn ($value, $code) => $this->languages->supports((string) $code) && is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $html ? $this->htmlSanitizer->clean(trim($value)) : trim($value))->all();
    }

    private function mergeTranslations(PostCategory $category, string $field, array $values, bool $html = false): array
    {
        return [...$category->getTranslations($field), ...$this->cleanTranslations($values, $html)];
    }

    private function uniqueLegacySlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $counter = 2;
        while (PostCategory::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) $slug = $base.'-'.$counter++;
        return $slug;
    }
}
