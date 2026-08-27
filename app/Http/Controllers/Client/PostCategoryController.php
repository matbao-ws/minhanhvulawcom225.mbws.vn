<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\LocalizedContent;
use App\Services\LocalizedSlugService;
use App\Support\FeatureGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PostCategoryController extends Controller
{
    public function __construct(
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly LocalizedContent $content,
        private readonly FeatureGate $features,
    ) {}

    public function show(Request $request, string $locale, string $slug): View
    {
        abort_unless($this->features->enabled('cms_page'), 404);

        $category = $this->localizedSlugs->find(PostCategory::class, $slug, app()->getLocale());

        abort_unless($category && $category->is_active, 404);

        $posts = Post::query()
            ->where('is_active', true)
            ->where('category_id', $category->id)
            ->with('localizedSlugs')
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('client.blog.category', [
            'category' => $category,
            'title' => $this->content->get($category, 'name'),
            'description' => $this->content->get($category, 'description'),
            // PostCategory carries no SEO fields of its own; the name and
            // description are the honest source for the document head.
            'posts' => $posts,
        ]);
    }
}
