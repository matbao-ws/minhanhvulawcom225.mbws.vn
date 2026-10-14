<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Seo\IndexNow;
use App\Services\Seo\SeoMetaFactory;
use App\Services\Seo\SitemapService;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml. Served by Laravel, not as files in public/, so
 * they follow APP_URL and the environment: a static robots.txt would tell
 * search engines to index staging copies of every shop.
 */
class SeoFileController extends Controller
{
    public function robots(SeoMetaFactory $seo): Response
    {
        if (! $seo->indexable()) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = implode("\n", [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /*/admin',
                'Disallow: /api/',
                '',
                'Sitemap: '.url('sitemap.xml'),
                '',
            ]);
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** The IndexNow key file: proves to the search engines that this site sent its URLs. */
    public function indexNowKey(string $indexNowKey, IndexNow $indexNow): Response
    {
        abort_unless(hash_equals($indexNow->key(), $indexNowKey), 404);

        return response($indexNow->key(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(SitemapService $sitemap): Response
    {
        return response()
            ->view('core.seo.sitemap', ['entries' => $sitemap->entries()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
