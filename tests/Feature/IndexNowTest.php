<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Post;
use App\Services\Seo\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Published, changed or removed posts, products and policy pages are sent to IndexNow once per
 * request, in production only; the key file proves the site owns the submission.
 */
class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        Route::middleware(['web', 'setLocale'])->get('{locale}/bai-viet/{slug}', fn () => 'theme page')->name('client.posts.show');
        Route::getRoutes()->refreshNameLookups();
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);
    }

    public function test_the_key_file_answers_only_its_own_key(): void
    {
        $key = app(IndexNow::class)->key();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
        $this->get("/$key.txt")->assertOk()->assertSee($key);
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }

    public function test_changes_in_one_request_are_sent_together_in_production(): void
    {
        $this->app['env'] = 'production';

        $first = $this->makePost('cach-chon-giay');
        $second = $this->makePost('giay-cho-nguoi-moi');
        $first->update(['title' => ['vi' => 'Cách chọn giày chạy bộ']]);
        app(IndexNow::class)->flush();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.indexnow.org/indexnow'
                && $request['key'] === app(IndexNow::class)->key()
                && str_ends_with($request['keyLocation'], app(IndexNow::class)->key().'.txt')
                && count($request['urlList']) === 2
                && str_ends_with($request['urlList'][0], '/bai-viet/cach-chon-giay');
        });
        $this->assertNotNull($second);
    }

    public function test_drafts_noindex_staging_and_the_switch_stay_quiet(): void
    {
        // Staging/local: never.
        $this->makePost('ban-staging');
        app(IndexNow::class)->flush();

        $this->app['env'] = 'production';
        $this->makePost('ban-nhap', ['is_active' => false]);
        $this->makePost('khong-index', ['robots_index' => false]);
        app(IndexNow::class)->flush();

        config(['seo.indexnow' => false]);
        $this->makePost('tat-indexnow');
        app(IndexNow::class)->flush();

        Http::assertNothingSent();
    }

    public function test_unpublishing_and_deleting_are_announced_too(): void
    {
        $this->app['env'] = 'production';
        $hidden = $this->makePost('se-an');
        $gone = $this->makePost('se-xoa');
        app(IndexNow::class)->flush();

        $hidden->update(['is_active' => false]);
        $gone->delete();
        app(IndexNow::class)->flush();

        Http::assertSent(fn (Request $request) => count($request['urlList']) === 2
            && str_ends_with($request['urlList'][0], '/bai-viet/se-an'));
    }

    private function makePost(string $slug, array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'title' => ['vi' => $slug], 'slug' => $slug, 'content' => ['vi' => '<p>x</p>'], 'is_active' => true,
        ]);
    }
}
