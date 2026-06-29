<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
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

        return view('admin.posts.create', compact('post', 'categories'));
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'category_id' => 'nullable|exists:post_categories,id',
            'summary' => 'nullable|string',
            'content' => 'required|string',
            'image_file' => 'nullable|image|max:2048',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keys' => 'nullable|string|max:255',
        ]);

        $locale = app()->getLocale() ?: 'vi';
        $title = trim($validated['title']);
        $slug = isset($validated['slug']) && $validated['slug'] !== '' 
            ? Str::slug($validated['slug']) 
            : Str::slug($title);

        $slugCount = Post::query()->where('slug', 'like', "{$slug}%")->count();
        if ($slugCount > 0) {
            $slug = $slug . '-' . time();
        }

        $imageUrl = null;
        if ($request->hasFile('image_file')) {
            $cloudinaryService = app(\App\Services\CloudinaryService::class);
            $imageUrl = $cloudinaryService->uploadFile($request->file('image_file'), 'posts');
        }

        $post = Post::create([
            'category_id' => $validated['category_id'] ?? null,
            'title' => [$locale => $title],
            'slug' => $slug,
            'summary' => isset($validated['summary']) ? [$locale => trim($validated['summary'])] : null,
            'content' => [$locale => $validated['content']],
            'image_url' => $imageUrl,
            'is_active' => $request->has('is_active'),
            'seo_title' => isset($validated['seo_title']) ? [$locale => trim($validated['seo_title'])] : null,
            'seo_description' => isset($validated['seo_description']) ? [$locale => trim($validated['seo_description'])] : null,
            'seo_keys' => $validated['seo_keys'] ?? null,
            'published_at' => $request->has('is_active') ? now() : null,
        ]);

        return redirect()
            ->route('admin.posts.index')
            ->with('success', __('admin.posts.created'));
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
        return view('admin.posts.edit', compact('post', 'categories'));
    }

    /**
     * Update the post.
     */
    public function update(Request $request, string $locale, Post $post)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => "nullable|string|max:255|unique:posts,slug,{$post->id}",
            'category_id' => 'nullable|exists:post_categories,id',
            'summary' => 'nullable|string',
            'content' => 'required|string',
            'image_file' => 'nullable|image|max:2048',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keys' => 'nullable|string|max:255',
        ]);

        $title = trim($validated['title']);
        $slug = isset($validated['slug']) && $validated['slug'] !== '' 
            ? Str::slug($validated['slug']) 
            : Str::slug($title);

        $imageUrl = $post->image_url;
        if ($request->hasFile('image_file')) {
            $cloudinaryService = app(\App\Services\CloudinaryService::class);
            $imageUrl = $cloudinaryService->uploadFile($request->file('image_file'), 'posts');
        }

        $titleTrans = $post->getTranslations('title');
        $titleTrans[$locale] = $title;

        $summaryTrans = $post->getTranslations('summary');
        if (isset($validated['summary']) && trim($validated['summary']) !== '') {
            $summaryTrans[$locale] = trim($validated['summary']);
        } else {
            unset($summaryTrans[$locale]);
        }

        $contentTrans = $post->getTranslations('content');
        $contentTrans[$locale] = $validated['content'];

        $seoTitleTrans = $post->getTranslations('seo_title');
        if (isset($validated['seo_title']) && trim($validated['seo_title']) !== '') {
            $seoTitleTrans[$locale] = trim($validated['seo_title']);
        } else {
            unset($seoTitleTrans[$locale]);
        }

        $seoDescTrans = $post->getTranslations('seo_description');
        if (isset($validated['seo_description']) && trim($validated['seo_description']) !== '') {
            $seoDescTrans[$locale] = trim($validated['seo_description']);
        } else {
            unset($seoDescTrans[$locale]);
        }

        $post->update([
            'category_id' => $validated['category_id'] ?? null,
            'title' => $titleTrans,
            'slug' => $slug,
            'summary' => $summaryTrans,
            'content' => $contentTrans,
            'image_url' => $imageUrl,
            'is_active' => $request->has('is_active'),
            'seo_title' => $seoTitleTrans,
            'seo_description' => $seoDescTrans,
            'seo_keys' => $validated['seo_keys'] ?? null,
            'published_at' => $request->has('is_active') ? ($post->published_at ?: now()) : null,
        ]);

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
}
