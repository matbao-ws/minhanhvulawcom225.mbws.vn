<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\LocalizedContent;
use App\Services\LocalizedSlugService;
use App\Services\Seo\SeoMetaFactory;
use App\Support\FeatureGate;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly LocalizedSlugService $localizedSlugs,
        private readonly LocalizedContent $content,
        private readonly FeatureGate $features,
        private readonly SeoMetaFactory $seo,
    ) {}

    public function show(string $locale, string $slug): View
    {
        abort_unless($this->features->enabled('cms_page'), 404);

        $page = $this->localizedSlugs->find(Page::class, $slug, app()->getLocale());

        abort_unless(
            $page instanceof Page
                && $page->is_active
                && $page->published_at
                && ! $page->published_at->isFuture(),
            404,
        );

        $page->load('localizedSlugs');

        $title = $this->content->get($page, 'title');
        $metaTitle = $this->content->get($page, 'meta_title');
        $metaDescription = $this->content->get($page, 'meta_description');

        return view('client.pages.show', [
            'page' => $page,
            'title' => $title,
            'html' => $this->content->get($page, 'published_html'),
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'seo' => $this->seo->forPage($page),
        ]);
    }
}
