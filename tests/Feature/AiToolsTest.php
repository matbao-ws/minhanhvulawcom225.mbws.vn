<?php

namespace Tests\Feature;

use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\PostSeoAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** FAQ, image alts, fixes by SEO score, product descriptions and the dashboard credits card. */
class AiToolsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private User $editor;

    /** @var array<string, mixed> task code => fake gateway answer */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'catalog', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);

        $this->editor = User::factory()->create(['role_id' => Role::query()->create([
            'name' => 'Editor', 'permissions' => ['products.view', 'products.update', 'posts.view', 'posts.update', 'pages.update', 'ai.use', 'ai.apply'],
        ])->id]);

        Http::fake([
            'gw.test/v1/tasks/*' => function (Request $request) {
                $code = basename(parse_url($request->url(), PHP_URL_PATH));

                return Http::response(['ok' => true, 'result' => $this->answers[$code] ?? [], 'suggestion_id' => 5,
                    'usage' => ['credits_charged' => 1, 'credits_used' => 7, 'credits_limit' => 100]]);
            },
            'gw.test/v1/suggestions/*' => Http::response(['ok' => true]),
            'gw.test/v1/usage' => Http::response(['ok' => true, 'usage' => ['period' => '2026-10', 'credits_used' => 12, 'credits_limit' => 100]]),
            '*' => Http::response('not allowed', 500),
        ]);
    }

    public function test_faq_is_appended_as_an_escaped_section_after_review(): void
    {
        $post = $this->makePost('<p>Ống uPVC dùng cho nước lạnh.</p>');
        $this->answers['content.faq'] = ['items' => [
            ['question' => 'Ống uPVC có dùng cho nước nóng không?', 'answer' => 'Không, ống uPVC dùng cho nước lạnh.'],
            ['question' => 'Câu bị bỏ', 'answer' => 'x'],
        ]];

        $id = $this->ask('/vi/admin/ai/faq', ['entity_type' => 'post', 'entity_id' => $post->id])
            ->assertOk()->assertJsonCount(2, 'data.items')->json('data.suggestion_id');

        // The owner kept one question and edited it; the answer carries markup that must stay text.
        $this->actingAs($this->editor)->postJson("/vi/admin/ai/faq/$id/apply", ['items' => [
            ['question' => 'Ống uPVC có dùng cho nước nóng không?', 'answer' => 'Không <script>x</script>, chỉ nước lạnh.'],
        ]])->assertOk();

        $html = $post->fresh()->getTranslation('content', 'vi', false);
        $this->assertStringStartsWith('<p>Ống uPVC dùng cho nước lạnh.</p>', $html);
        $this->assertStringContainsString('<h2>'.__('ai.faq.heading').'</h2><h3>Ống uPVC có dùng cho nước nóng không?</h3>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('Câu bị bỏ', $html);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/suggestions/5/events') && $r['event'] === 'applied_after_edit');
    }

    public function test_alt_text_is_made_from_the_sites_own_image_and_written_on_that_image_only(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/ong.png', base64_decode(self::PNG));
        $post = $this->makePost('<p>Bài</p><img src="/storage/media/ong.png"><img src="/storage/media/khac.png" alt="Đã có">');
        $this->answers['media.alt_text'] = ['alt' => 'Bó ống nhựa uPVC trắng', 'src' => '/storage/media/ong.png'];

        $this->ask('/vi/admin/ai/alt/missing', ['entity_type' => 'post', 'entity_id' => $post->id])
            ->assertOk()->assertJsonPath('data.images', ['/storage/media/ong.png']);

        $id = $this->ask('/vi/admin/ai/alt', ['entity_type' => 'post', 'entity_id' => $post->id, 'src' => '/storage/media/ong.png'])
            ->assertOk()->assertJsonPath('data.alt', 'Bó ống nhựa uPVC trắng')->json('data.suggestion_id');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/tasks/media.alt_text')
            && $r['image'] === ['mime_type' => 'image/png', 'data' => self::PNG]
            && $r['src'] === '/storage/media/ong.png');

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/alt/$id/apply", ['alt' => 'Bó ống nhựa uPVC trắng'])->assertOk();

        $html = $post->fresh()->getTranslation('content', 'vi', false);
        $this->assertMatchesRegularExpression('/<img[^>]*alt="Bó ống nhựa uPVC trắng"[^>]*src="\/storage\/media\/ong\.png"|<img[^>]*src="\/storage\/media\/ong\.png"[^>]*alt="Bó ống nhựa uPVC trắng"/', $html);
        $this->assertStringContainsString('alt="Đã có"', $html);

        // Applied once; the image now has alt text.
        $this->actingAs($this->editor)->postJson("/vi/admin/ai/alt/$id/apply", ['alt' => 'x'])->assertStatus(410);
    }

    public function test_an_image_on_another_host_is_never_fetched(): void
    {
        $post = $this->makePost('<p>Bài</p><img src="http://169.254.169.254/latest/meta-data">');

        $this->ask('/vi/admin/ai/alt', ['entity_type' => 'post', 'entity_id' => $post->id, 'src' => 'http://169.254.169.254/latest/meta-data'])
            ->assertStatus(503)->assertJsonPath('reason', 'image_unavailable');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/tasks/'));
    }

    public function test_seo_fix_targets_failing_rules_and_the_score_is_recomputed_by_code(): void
    {
        $post = $this->makePost('<p>'.trim(str_repeat('thi công nhà phố cần chuẩn bị kỹ ', 40)).'</p><h2>Chuẩn bị</h2><p>Nội dung.</p>', [
            'seo_keys' => 'thi công nhà phố', 'seo_title' => ['vi' => 'Lưu ý'],
        ]);
        $this->answers['seo.fix_suggest'] = [
            'seo_title' => 'Thi công nhà phố: những điều cần chuẩn bị kỹ',
            'seo_description' => 'Thi công nhà phố cần chuẩn bị kỹ về giấy phép, ngân sách và đội thợ. Bài viết tổng hợp các bước quan trọng trước khi khởi công.',
            'intro_html' => '',
            'notes' => [['rule' => 'internal_links', 'note' => 'Thêm liên kết tới trang dịch vụ.']],
        ];

        $response = $this->ask('/vi/admin/ai/seo-fixes', ['entity_id' => $post->id])->assertOk()
            ->assertJsonPath('data.proposal.intro_html', '');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/tasks/seo.fix_suggest')
            && in_array('title_keyword', $r['failing_rules'], true)
            && ! in_array('content_length', $r['failing_rules'], true)
            && $r['focus_keyword'] === 'thi công nhà phố');

        $result = $this->actingAs($this->editor)->postJson('/vi/admin/ai/seo-fixes/'.$response->json('data.suggestion_id').'/apply', [
            'seo_title' => $this->answers['seo.fix_suggest']['seo_title'],
            'seo_description' => $this->answers['seo.fix_suggest']['seo_description'],
        ])->assertOk();

        $post->refresh();
        $this->assertSame('Thi công nhà phố: những điều cần chuẩn bị kỹ', $post->getTranslation('seo_title', 'vi', false));
        // Both scores come from the checker, before and after the write.
        $this->assertGreaterThan($result->json('data.before'), $result->json('data.after'));
        $this->assertSame($post->seo_score, $result->json('data.after'));
    }

    public function test_seo_fix_works_on_a_product_and_rescores_it(): void
    {
        $product = Product::query()->create([
            'name' => ['vi' => 'Máy bơm nước'], 'slug' => 'may-bom-nuoc', 'price' => 1000000, 'is_active' => true,
            'description' => ['vi' => '<p>Máy bơm nước gia đình chạy êm.</p>'], 'seo_keys' => 'máy bơm nước',
            'meta_title' => ['vi' => 'Bơm'],
        ]);
        $this->answers['seo.fix_suggest'] = [
            'seo_title' => 'Máy bơm nước gia đình chạy êm, tiết kiệm điện 2026',
            'seo_description' => '', 'intro_html' => '', 'notes' => [],
        ];

        $id = $this->ask('/vi/admin/ai/seo-fixes', ['entity_type' => 'product', 'entity_id' => $product->id])->assertOk()->json('data.suggestion_id');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/tasks/seo.fix_suggest')
            && $r['entity_type'] === 'product' && $r['seo_title'] === 'Bơm' && $r['focus_keyword'] === 'máy bơm nước');

        $result = $this->actingAs($this->editor)->postJson("/vi/admin/ai/seo-fixes/$id/apply", [
            'seo_title' => $this->answers['seo.fix_suggest']['seo_title'],
        ])->assertOk();

        $product->refresh();
        $this->assertSame('Máy bơm nước gia đình chạy êm, tiết kiệm điện 2026', $product->getTranslation('meta_title', 'vi'));
        $this->assertGreaterThan($result->json('data.before'), $result->json('data.after'));
        $this->assertSame($product->seo_score, $result->json('data.after'));
    }

    public function test_seo_fix_rewrites_a_policy_pages_opening_through_its_revision_path(): void
    {
        $page = \App\Models\Page::query()->create([
            'title' => ['vi' => 'Chính sách đổi trả'], 'slug' => 'doi-tra', 'is_active' => true, 'seo_keys' => 'đổi trả',
            'published_html' => ['vi' => '<p>Khách được hoàn tiền.</p><h2>Điều kiện</h2><p>Còn tem.</p>'],
        ]);
        $this->answers['seo.fix_suggest'] = [
            'seo_title' => '', 'seo_description' => '',
            'intro_html' => '<p>Chính sách đổi trả: khách được hoàn tiền.</p>', 'notes' => [],
        ];

        $id = $this->ask('/vi/admin/ai/seo-fixes', ['entity_type' => 'page', 'entity_id' => $page->id])->assertOk()->json('data.suggestion_id');
        $this->actingAs($this->editor)->postJson("/vi/admin/ai/seo-fixes/$id/apply", [
            'intro_html' => $this->answers['seo.fix_suggest']['intro_html'],
        ])->assertOk();

        $page->refresh();
        $this->assertSame('<p>Chính sách đổi trả: khách được hoàn tiền.</p><h2>Điều kiện</h2><p>Còn tem.</p>', $page->getTranslation('published_html', 'vi'));
        $this->assertSame(1, $page->revisions()->count(), 'the old text is a revision');
        $this->assertNotNull($page->seo_score);
    }

    public function test_seo_fix_refuses_a_record_type_without_an_seo_box(): void
    {
        $this->ask('/vi/admin/ai/seo-fixes', ['entity_type' => 'category', 'entity_id' => 1])->assertStatus(422);
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_the_checker_not_a_stored_score_decides_what_fails(): void
    {
        $post = $this->makePost('<p>x</p>');
        $post->forceFill(['seo_score' => 100])->save();
        // Built to fail: the analyzer, not a stored score, decides.
        $this->ask('/vi/admin/ai/seo-fixes', ['entity_id' => $post->id])->assertOk();

        $this->assertSame(1, AiSuggestion::query()->count());
    }

    public function test_a_product_description_uses_catalogue_facts_but_never_the_price(): void
    {
        $product = Product::query()->create([
            'name' => ['vi' => 'Ống uPVC phi 21'], 'slug' => 'ong-21', 'sku' => 'U21', 'price' => 25000, 'is_active' => true,
            'short_description' => ['vi' => '<p>Ống cấp nước</p>'], 'description' => ['vi' => '<p>Cũ</p>'],
        ]);
        $group = ProductOptionGroup::query()->create(['product_id' => $product->id, 'code' => 'length', 'name' => ['vi' => 'Độ dài'], 'sort_order' => 0]);
        $group->values()->create(['label' => ['vi' => '4m'], 'code' => '4m', 'sort_order' => 0]);
        $this->answers['content.product_description'] = [
            'html' => '<p>Ống uPVC phi 21 cho nước sạch. <a href="https://spam.example">Mua</a></p><img src="https://x.example/a.jpg"><p>[cần bổ sung: chất liệu]</p>',
            'missing_info' => ['chất liệu'],
        ];

        $id = $this->ask('/vi/admin/ai/product-descriptions', ['entity_id' => $product->id, 'selling_points' => 'Bền, nhẹ', 'target_words' => 300])
            ->assertOk()->assertJsonPath('data.missing_info.0', 'chất liệu')->json('data.suggestion_id');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/tasks/content.product_description')
            && $r['attributes'] === ['Độ dài: 4m']
            && $r['short_description'] === 'Ống cấp nước'
            && ! str_contains(json_encode($r->data()), '25000'));

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/product-descriptions/$id/apply")->assertOk();
        $html = $product->fresh()->getTranslation('description', 'vi', false);
        $this->assertStringContainsString('Ống uPVC phi 21 cho nước sạch.', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_tools_need_the_records_own_permission_and_reject_is_reported(): void
    {
        $post = $this->makePost('<p>Nội dung</p>');
        $writerOnly = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Products', 'permissions' => ['products.update', 'ai.use']])->id]);

        $this->actingAs($writerOnly)->postJson('/vi/admin/ai/faq', ['entity_type' => 'post', 'entity_id' => $post->id, 'content_locale' => 'vi'])->assertForbidden();

        $this->answers['content.faq'] = ['items' => [['question' => 'Q?', 'answer' => 'A.']]];
        $id = $this->ask('/vi/admin/ai/faq', ['entity_type' => 'post', 'entity_id' => $post->id])->json('data.suggestion_id');
        $this->actingAs($this->editor)->postJson("/vi/admin/ai/suggestions/$id/reject", ['reason' => 'none_fit'])->assertOk();

        $this->assertSame(AiSuggestion::REJECTED, AiSuggestion::query()->findOrFail($id)->status);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/suggestions/5/events') && $r['event'] === 'rejected');
    }

    public function test_the_dashboard_shows_this_months_credits(): void
    {
        Cache::flush();

        $this->actingAs($this->editor)->get('/vi/admin')->assertOk()
            ->assertSee(__('ai.usage.title'))->assertSee('12 / 100');

        $noAi = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Viewer', 'permissions' => ['products.view']])->id]);
        $this->actingAs($noAi)->get('/vi/admin')->assertOk()->assertDontSee(__('ai.usage.title'));
    }

    public function test_the_tool_buttons_sit_beside_the_editors(): void
    {
        $post = $this->makePost('<p>x</p>');

        $this->actingAs($this->editor)->get("/vi/admin/posts/{$post->id}/edit")->assertOk()
            ->assertSee('js-ai-faq', false)->assertSee('js-ai-alt', false)->assertSee('js-ai-seo-fix', false)
            ->assertDontSee('btn-outline-primary js-ai-product', false);
    }

    private function makePost(string $html, array $attributes = []): Post
    {
        $post = Post::query()->create($attributes + [
            'title' => ['vi' => 'Thi công nhà phố'], 'slug' => 'thi-cong-nha-pho', 'content' => ['vi' => $html], 'is_active' => true,
        ]);
        app(PostSeoAnalyzer::class);

        return $post;
    }

    private function ask(string $url, array $data)
    {
        return $this->actingAs($this->editor)->postJson($url, $data + ['content_locale' => 'vi']);
    }
}
