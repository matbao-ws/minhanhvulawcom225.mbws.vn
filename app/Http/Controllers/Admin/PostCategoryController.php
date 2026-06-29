<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostCategoryController extends Controller
{
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:post_categories,slug',
            'parent_id' => 'nullable|exists:post_categories,id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $locale = app()->getLocale() ?: 'vi';
        $name = trim($validated['name']);
        
        $slug = isset($validated['slug']) && $validated['slug'] !== '' 
            ? Str::slug($validated['slug']) 
            : Str::slug($name);

        // Ensure slug is unique
        $slugCount = PostCategory::query()->where('slug', 'like', "{$slug}%")->count();
        if ($slugCount > 0) {
            $slug = $slug . '-' . time();
        }

        PostCategory::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => [$locale => $name],
            'slug' => $slug,
            'description' => isset($validated['description']) ? [$locale => trim($validated['description'])] : null,
            'is_active' => (bool) $request->input('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

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
    public function update(Request $request, string $locale, PostCategory $postCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "nullable|string|max:255|unique:post_categories,slug,{$postCategory->id}",
            'parent_id' => 'nullable|exists:post_categories,id',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $name = trim($validated['name']);
        $slug = isset($validated['slug']) && $validated['slug'] !== '' 
            ? Str::slug($validated['slug']) 
            : Str::slug($name);

        $nameTranslations = $postCategory->getTranslations('name');
        $nameTranslations[$locale] = $name;

        $descTranslations = $postCategory->getTranslations('description');
        if (isset($validated['description']) && trim($validated['description']) !== '') {
            $descTranslations[$locale] = trim($validated['description']);
        } else {
            unset($descTranslations[$locale]);
        }

        $postCategory->update([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $nameTranslations,
            'slug' => $slug,
            'description' => $descTranslations,
            'is_active' => (bool) $request->input('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('admin.post-categories.index')
            ->with('success', __('admin.blog_categories.updated'));
    }

    /**
     * Quick update for Category (via Quick Edit Modal)
     */
    public function quickUpdate(Request $request, string $locale, PostCategory $postCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "nullable|string|max:255|unique:post_categories,slug,{$postCategory->id}",
            'parent_id' => 'nullable|exists:post_categories,id',
            'description' => 'nullable|string',
        ]);

        $name = trim($validated['name']);
        $slug = isset($validated['slug']) && $validated['slug'] !== '' 
            ? Str::slug($validated['slug']) 
            : Str::slug($name);

        $nameTranslations = $postCategory->getTranslations('name');
        $nameTranslations[$locale] = $name;

        $descTranslations = $postCategory->getTranslations('description');
        if (isset($validated['description']) && trim($validated['description']) !== '') {
            $descTranslations[$locale] = trim($validated['description']);
        } else {
            unset($descTranslations[$locale]);
        }

        $postCategory->update([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $nameTranslations,
            'slug' => $slug,
            'description' => $descTranslations,
            'is_active' => (bool) $request->input('is_active', true),
        ]);

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
}
