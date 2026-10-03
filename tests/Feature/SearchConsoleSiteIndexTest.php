<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AiGateway\GatewayClient;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Search Console goes through the gateway like GA4: the website stores the
 * property, the gateway checks it, and the website publishes its page index.
 */
class SearchConsoleSiteIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|null */
    private ?array $connections = null;

    /** @var list<array<string, mixed>> */
    private array $chunks = [];

    /** Next answer to search-console/check: [status, body]. */
    private ?array $checkAnswer = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_test_project_token', 'app.url' => 'https://spico.vn']);
        foreach (['analytics', 'ai_seo'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        Http::fake([
            'gw.test/v1/project/connections' => function (Request $request) {
                $this->connections = $request->data();

                return Http::response(['ok' => true]);
            },
            'gw.test/v1/project' => Http::response(['ok' => true, 'project' => ['reader_email' => 'core-reader@x.iam.gserviceaccount.com']]),
            'gw.test/v1/search-console/check' => function () {
                [$status, $body] = $this->checkAnswer ?? [200, ['ok' => true, 'site_url' => 'sc-domain:spico.vn', 'permission' => 'siteRestrictedUser']];

                return Http::response($body, $status);
            },
            'gw.test/v1/site-index' => function (Request $request) {
                $this->chunks[] = $request->data();

                return Http::response(['ok' => true]);
            },
        ]);
    }

    public function test_the_site_url_is_validated_saved_and_handed_to_the_gateway(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/vi/admin/settings', ['shop_name' => 'Shop', 'google_analytics' => ['gsc_site_url' => 'spico.vn']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['google_analytics.gsc_site_url']);

        $this->actingAs($admin)->postJson('/vi/admin/settings', ['shop_name' => 'Shop', 'google_analytics' => ['property_id' => '345678901', 'gsc_site_url' => 'sc-domain:spico.vn']])
            ->assertOk();

        $this->assertSame('sc-domain:spico.vn', app(AnalyticsReport::class)->settings()['gsc_site_url']);
        $this->assertSame(['ga4_property_id' => '345678901', 'gsc_site_url' => 'sc-domain:spico.vn'], $this->connections);
    }

    public function test_check_reports_the_permission_or_a_plain_reason(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/vi/admin/settings/search-console/check')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'not_connected');

        app(AnalyticsReport::class)->saveSettings(['gsc_site_url' => 'https://spico.vn/']);

        $this->actingAs($admin)->postJson('/vi/admin/settings/search-console/check')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => __('admin.analytics.gsc_connected', ['permission' => 'siteRestrictedUser'])]);

        $this->checkAnswer = [403, ['ok' => false, 'reason' => 'no_access']];
        $this->actingAs($admin)->postJson('/vi/admin/settings/search-console/check')
            ->assertStatus(422)
            ->assertJson(['reason' => 'no_access', 'message' => __('admin.analytics.gsc_no_access')]);
    }

    public function test_check_needs_the_settings_permission(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Viewer', 'permissions' => ['settings.view']])->id]);

        $this->actingAs($viewer)->postJson('/vi/admin/settings/search-console/check')->assertForbidden();
    }

    public function test_the_index_lists_public_records_per_locale_with_their_content_counts(): void
    {
        $post = Post::query()->create([
            'title' => ['vi' => 'Cách chọn ống nhựa', 'en' => 'Choosing a pipe'],
            'slug' => 'cach-chon-ong-nhua',
            'content' => ['vi' => '<h2>Vì sao</h2><p>Ống nhựa bền và nhẹ.</p><img src="/a.jpg"><img src="/b.jpg" alt="Ống"><a href="/vi/san-pham/ong">ống</a><a href="https://spico.vn/x">x</a><a href="https://other.vn">o</a>'],
            'seo_keys' => 'ống nhựa',
            'is_active' => true,
            'robots_index' => false,
        ]);
        Product::query()->create(['name' => ['vi' => 'Ống ẩn'], 'slug' => 'ong-an', 'sku' => 'H-1', 'price' => 1, 'is_active' => false]);
        Category::query()->create(['name' => ['vi' => 'Nháp'], 'slug' => 'nhap', 'is_active' => true, 'is_draft' => true]);
        Page::query()->create(['title' => ['vi' => 'Chưa đăng'], 'slug' => 'chua-dang', 'is_active' => true, 'published_at' => null]);

        $this->artisan('ai:site-index')->assertSuccessful();

        $this->assertCount(1, $this->chunks);
        $this->assertTrue($this->chunks[0]['final']);
        $pages = collect($this->chunks[0]['pages']);
        $this->assertSame(['post'], $pages->pluck('entity_type')->unique()->values()->all());

        $vi = $pages->firstWhere('locale', 'vi');
        $this->assertSame($post->id, $vi['entity_id']);
        $this->assertSame('cach-chon-ong-nhua', $vi['slug']);
        $this->assertSame('ống nhựa', $vi['focus_keyword']);
        // No H1 in the body: the title is the H1. No category set.
        $this->assertSame(['Cách chọn ống nhựa', null], [$vi['h1'], $vi['category']]);
        $this->assertSame(1, $vi['h2_count']);
        $this->assertSame(1, $vi['images_without_alt']);
        $this->assertSame(2, $vi['internal_links']);
        $this->assertFalse($vi['indexable']);
        $this->assertGreaterThan(5, $vi['word_count']);
    }

    public function test_rows_go_in_chunks_under_one_generation(): void
    {
        foreach (range(1, 510) as $i) {
            Product::query()->create(['name' => ['vi' => 'Ống '.$i], 'slug' => 'ong-'.$i, 'sku' => 'S-'.$i, 'price' => 1, 'is_active' => true]);
        }
        $locales = count(app(\App\Services\LanguageRegistry::class)->codes());

        $this->artisan('ai:site-index')->assertSuccessful();

        $this->assertGreaterThan(1, count($this->chunks));
        $this->assertSame(510 * $locales, collect($this->chunks)->sum(fn ($chunk) => count($chunk['pages'])));
        $this->assertCount(1, collect($this->chunks)->pluck('generation')->unique());
        $this->assertSame([true], collect($this->chunks)->pluck('final')->filter()->values()->all());
        $this->assertTrue(end($this->chunks)['final']);
    }

    public function test_nothing_is_sent_without_the_feature(): void
    {
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false]);
        GatewayClient::forgetProject();

        $this->artisan('ai:site-index')->assertSuccessful();

        $this->assertSame([], $this->chunks);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin '.uniqid(), 'permissions' => ['*']])->id]);
    }
}
