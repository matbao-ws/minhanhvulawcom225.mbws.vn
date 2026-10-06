<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\ProductQueryService;
use App\Services\LocalizedContent;
use App\Services\LocalizedSlugService;
use App\Services\Seo\SeoMetaFactory;
use App\Support\FeatureGate;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly LocalizedContent $content,
        private readonly ProductQueryService $products,
        private readonly FeatureGate $features,
        private readonly SeoMetaFactory $seo,
    ) {}

    public function show(Request $request, string $locale, string $slug): View
    {
        abort_unless($this->features->enabled('catalog'), 404);

        $category = $this->localizedSlugs->find(Category::class, $slug, app()->getLocale());

        abort_unless($category && $category->is_active && ! $category->is_draft, 404);

        // Products come from the shared read service, never from a second copy
        // of the filtering rules living in this controller.
        $products = $this->products
            ->listing(['category' => $category->canonicalSlug(app()->getLocale())])
            ->paginate(12)
            ->withQueryString();

        $title = $this->content->get($category, 'name');
        $description = $this->content->get($category, 'description');
        $metaTitle = $this->content->get($category, 'meta_title');
        $metaDescription = $this->content->get($category, 'meta_description');

        return view('client.catalog.category', [
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'products' => $products,
            'seo' => $this->seo->forRecord(
                $category,
                'client.categories.show',
                $metaTitle ?: $title,
                $metaDescription ?: $description,
                image: MediaUrl::resolve($category->image_url),
                breadcrumbs: $this->seo->ancestorTrail($category, 'client.categories.show', 'name', fn (Category $parent) => $parent->is_active && ! $parent->is_draft),
            ),
        ]);
    }
}
