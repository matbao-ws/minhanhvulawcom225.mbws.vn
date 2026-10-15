<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPostDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    private mixed $gatewayResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);

        $this->writer = User::factory()->create(['role_id' => Role::query()->create([
            'name' => 'Writer', 'permissions' => ['posts.view', 'posts.create', 'posts.update', 'ai.use'],
        ])->id]);

        Http::fake([
            'gw.test/v1/tasks/*' => fn () => $this->gatewayResponse ?? Http::response([
                'ok' => true,
                'result' => [
                    'title' => 'Cách chọn ống nhựa uPVC cho nhà phố',
                    'html' => '<h2>Phi 21 hay 27?</h2><p>Ống uPVC nhẹ, <a href="https://spam.example">dễ lắp</a>.</p><img src="https://x.example/a.jpg"><p>[cần bổ sung: thời gian bảo hành]</p><script>alert(1)</script>',
                    'meta_title' => 'Cách chọn ống nhựa uPVC cho nhà phố',
                    'meta_description' => 'Hướng dẫn chọn ống uPVC phi 21 hay 27 cho nhà phố và lưu ý khi dán keo.',
                    'claims_to_verify' => ['Ống chịu áp lực 10 bar'],
                    'missing_info' => ['thời gian bảo hành'],
                ],
                'suggestion_id' => 901,
                'usage' => ['credits_charged' => 4, 'credits_used' => 4, 'credits_limit' => 100],
            ]),
            'gw.test/v1/suggestions/*/events' => Http::response(['ok' => true]),
        ]);
    }

    public function test_a_brief_becomes_an_unpublished_post_opened_in_the_editor(): void
    {
        $response = $this->actingAs($this->writer)->post('/vi/admin/ai/post-drafts', $this->brief());

        $post = Post::query()->sole();
        $response->assertRedirect(route('admin.posts.edit', $post));

        $this->assertFalse($post->is_active);
        $this->assertNull($post->published_at);
        $this->assertSame('Cách chọn ống nhựa uPVC cho nhà phố', $post->getTranslation('title', 'vi', false));
        // From the keyword, so the SEO checker finds it in the slug.
        $this->assertSame('chon-ong-nhua-upvc', $post->slug);
        $this->assertSame('chọn ống nhựa uPVC', $post->seo_keys);
        $this->assertNotNull($post->seo_score);

        $html = $post->getTranslation('content', 'vi', false);
        // The AI may not add links, images or scripts; the placeholder stays for the owner.
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('dễ lắp', $html);
        $this->assertStringContainsString('[cần bổ sung: thời gian bảo hành]', $html);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gw.test/v1/tasks/content.draft'
            && $request['locale'] === 'vi'
            && $request['products'] === ['Ống uPVC 21mm', 'Keo dán ống uPVC']
            && $request['sources'] === []
            && $request['target_words'] === 800
            && $request->hasHeader('Idempotency-Key'));

        $this->assertSame(AiSuggestion::APPLIED, AiSuggestion::query()->sole()->status);
        $this->assertTrue(AdminActivityLog::query()->where('action', 'ai_post_drafted')->exists());
        // Typed once, offered again next time.
        $this->assertSame($this->brief()['business_info'], ProjectSetting::query()->where('setting_key', 'ai_business_info')->value('setting_value'));

        $this->actingAs($this->writer)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertSee(__('ai.draft.review_title'))
            ->assertSee('Ống chịu áp lực 10 bar')
            ->assertSee('thời gian bảo hành');
    }

    public function test_notes_mode_sends_only_the_notes(): void
    {
        $this->actingAs($this->writer)->post('/vi/admin/ai/post-drafts', [
            'mode' => 'notes',
            'notes' => "- ống uPVC nhẹ, dễ cắt\n- dùng keo dán chuyên dụng\n- khách hay hỏi phi 21 hay 27",
            'target_words' => 600,
        ])->assertRedirect();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gw.test/v1/tasks/content.from_notes'
            && ! isset($request['business_info'])
            && str_contains($request['notes'], 'keo dán chuyên dụng'));
        $this->assertSame(1, Post::query()->count());
    }

    public function test_the_brief_must_carry_the_business_facts_before_a_credit_is_spent(): void
    {
        $this->actingAs($this->writer)->post('/vi/admin/ai/post-drafts', ['business_info' => 'ngắn'] + $this->brief())
            ->assertSessionHasErrors('business_info');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/tasks/'));
        $this->assertSame(0, Post::query()->count());
    }

    public function test_a_sentence_is_refused_as_a_keyword(): void
    {
        $this->actingAs($this->writer)->post('/vi/admin/ai/post-drafts', ['keyword' => 'Những yếu tố cần xem xét kỹ trước khi thi công nhà phố'] + $this->brief())
            ->assertSessionHasErrors('keyword');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/tasks/'));
    }

    public function test_a_gateway_refusal_keeps_the_form_and_creates_nothing(): void
    {
        $this->gatewayResponse = Http::response(['ok' => false, 'reason' => 'quota_exceeded'], 429);

        $this->actingAs($this->writer)->from('/vi/admin/ai/post-drafts/create')->post('/vi/admin/ai/post-drafts', $this->brief())
            ->assertRedirect('/vi/admin/ai/post-drafts/create')
            ->assertSessionHas('error', __('ai.errors.quota_exceeded'))
            ->assertSessionHasInput('keyword');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_writing_needs_ai_use_and_posts_create(): void
    {
        $editorOnly = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'posts.update', 'ai.use']])->id]);
        $noAi = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Blogger', 'permissions' => ['posts.view', 'posts.create']])->id]);

        $this->actingAs($editorOnly)->post('/vi/admin/ai/post-drafts', $this->brief())->assertForbidden();
        // Without ai.use the lock answers first: back, with the reason.
        $this->actingAs($noAi)->from('/vi/admin/posts')->post('/vi/admin/ai/post-drafts', $this->brief())
            ->assertRedirect('/vi/admin/posts')->assertSessionHas('error', __('ai.locked.permission', ['feature' => __('ai.locked.features.ai_seo')]));
        // Always shown; without ai.use the button and the menu item are locked and say why.
        $this->actingAs($noAi)->get('/vi/admin/posts')->assertOk()
            ->assertSee('data-sidebar-ai-locked="seo_draft"', false)
            ->assertSee(e(__('ai.locked.permission', ['feature' => __('ai.locked.features.ai_seo')])), false);
        $this->actingAs($this->writer)->get('/vi/admin/posts')->assertOk()->assertSee(route('admin.ai.post-drafts.create'), false)
            ->assertDontSee('data-sidebar-ai-locked="seo_draft"', false);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_review_panel_disappears_once_the_post_is_published(): void
    {
        $this->actingAs($this->writer)->post('/vi/admin/ai/post-drafts', $this->brief());
        $post = Post::query()->sole();
        $post->update(['is_active' => true, 'published_at' => now()]);

        $this->actingAs($this->writer)->get(route('admin.posts.edit', $post))
            ->assertOk()->assertDontSee(__('ai.draft.review_title'));
    }

    private function brief(): array
    {
        return [
            'mode' => 'draft',
            'keyword' => 'chọn ống nhựa uPVC',
            'business_info' => 'Cửa hàng vật tư nước Minh Phát, Q.7 TP.HCM, bán ống và phụ kiện uPVC từ 2012.',
            'key_points' => "So sánh phi 21 và 27\nKhi nào dùng ống chịu áp\nLưu ý khi dán keo",
            'products' => "Ống uPVC 21mm\n\nKeo dán ống uPVC",
            'audience' => 'Chủ nhà tự sửa hệ thống nước',
            'tone' => 'thân thiện',
            'target_words' => 800,
        ];
    }
}
