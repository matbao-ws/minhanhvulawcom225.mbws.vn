<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiMetaSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Product $product;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    /** What the fake gateway answers to meta.suggest; a second Http::fake() would not replace it. */
    private mixed $metaResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'catalog', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);

        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['products.view', 'products.update', 'posts.update', 'ai.use']]);
        $this->editor = User::factory()->create(['role_id' => $role->id]);

        $this->product = Product::query()->create([
            'name' => ['vi' => 'Ống nhựa uPVC'],
            'slug' => 'ong-nhua-upvc',
            'sku' => 'UPVC-21',
            'price' => 25000,
            'is_active' => true,
            'description' => ['vi' => '<p>Ống nhựa <strong>uPVC</strong> cho cấp thoát nước.</p>'],
            'meta_title' => ['vi' => 'Ống nhựa'],
        ]);

        Http::fake([
            'gw.test/v1/tasks/meta.suggest' => fn () => $this->metaResponse ?? Http::response([
                'ok' => true,
                'result' => ['options' => [
                    ['title' => 'Ống nhựa uPVC cấp thoát nước bền, dễ lắp', 'description' => 'Ống nhựa uPVC chịu áp tốt cho cấp thoát nước gia đình.', 'angle' => 'lợi ích'],
                    ['title' => 'Ống uPVC 21mm chính hãng', 'description' => 'Ống uPVC nhiều kích cỡ, giao nhanh.', 'angle' => 'sản phẩm'],
                    ['title' => 'Nên chọn ống nhựa nào cho nhà?', 'description' => 'Ống uPVC: bền, nhẹ, dễ lắp đặt.', 'angle' => 'câu hỏi'],
                ], 'focus_keyword' => 'ống nhựa upvc'],
                'suggestion_id' => 501,
                'usage' => ['credits_charged' => 1, 'credits_used' => 3, 'credits_limit' => 100],
            ]),
            'gw.test/v1/suggestions/*/events' => function (Request $request) {
                $this->events[] = $request->data();

                return Http::response(['ok' => true]);
            },
        ]);
    }

    public function test_the_site_builds_the_input_from_the_record_and_keeps_the_suggestion(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson('/vi/admin/ai/meta-suggestions', ['entity_type' => 'product', 'entity_id' => $this->product->id, 'content_locale' => 'vi'])
            ->assertOk()
            ->assertJsonPath('data.current.title', 'Ống nhựa')
            ->assertJsonCount(3, 'data.options')
            ->assertJsonPath('data.usage.credits_used', 3);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://gw.test/v1/tasks/meta.suggest') {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer mbp_project_token')
                && preg_match('/^[0-9a-f-]{36}$/', $request->header('Idempotency-Key')[0] ?? '') === 1
                // Only these fields leave the site, and content goes as plain text.
                && array_keys($request->data()) === ['locale', 'entity_type', 'entity_id', 'title', 'content_text', 'current_meta_title', 'current_meta_description', 'site_name', 'focus_keyword']
                && $request['content_text'] === 'Ống nhựa uPVC cho cấp thoát nước.'
                && $request['entity_id'] === $this->product->id;
        });

        $suggestion = AiSuggestion::query()->findOrFail($response->json('data.suggestion_id'));
        $this->assertSame([501, 'proposed', ['title' => 'Ống nhựa', 'description' => '']], [$suggestion->gateway_suggestion_id, $suggestion->status, $suggestion->old_values]);
        // Asking changes nothing on the record.
        $this->assertSame('Ống nhựa', $this->product->fresh()->getTranslation('meta_title', 'vi', false));
    }

    public function test_applying_writes_only_the_meta_of_that_locale_logs_it_and_reports_back(): void
    {
        $suggestion = $this->suggest();

        $this->actingAs($this->editor)
            ->postJson("/vi/admin/ai/meta-suggestions/{$suggestion->id}/apply", [
                'option' => 0,
                'title' => 'Ống nhựa uPVC cấp thoát nước bền, dễ lắp',
                'description' => 'Ống nhựa uPVC chịu áp tốt cho cấp thoát nước gia đình.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Ống nhựa uPVC cấp thoát nước bền, dễ lắp');

        $product = $this->product->fresh();
        $this->assertSame('Ống nhựa uPVC cấp thoát nước bền, dễ lắp', $product->getTranslation('meta_title', 'vi', false));
        $this->assertSame('Ống nhựa uPVC chịu áp tốt cho cấp thoát nước gia đình.', $product->getTranslation('meta_description', 'vi', false));
        $this->assertSame('Ống nhựa uPVC', $product->getTranslation('name', 'vi', false));

        $this->assertSame([AiSuggestion::APPLIED, 0], [$suggestion->fresh()->status, $suggestion->fresh()->applied_option]);
        $this->assertTrue(AdminActivityLog::query()->where('action', 'ai_meta_applied')->exists());
        $this->assertSame([['event' => 'applied', 'option_index' => 0, 'edit_distance' => 0.0]], $this->events);
    }

    public function test_an_edited_option_is_reported_with_its_edit_distance(): void
    {
        $suggestion = $this->suggest();

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/meta-suggestions/{$suggestion->id}/apply", [
            'option' => 1, 'title' => 'Ống uPVC Bình Minh 21mm chính hãng', 'description' => 'Ống uPVC nhiều kích cỡ, giao nhanh.',
        ])->assertOk();

        $this->assertSame('applied_after_edit', $this->events[0]['event']);
        $this->assertGreaterThan(0, $this->events[0]['edit_distance']);
        $this->assertLessThan(1, $this->events[0]['edit_distance']);
    }

    public function test_a_meta_changed_since_the_suggestion_is_not_overwritten(): void
    {
        $suggestion = $this->suggest();
        $this->product->setTranslation('meta_title', 'vi', 'Ai đó vừa sửa')->save();

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/meta-suggestions/{$suggestion->id}/apply", [
            'option' => 0, 'title' => 'Mới', 'description' => 'Mới',
        ])->assertStatus(409);

        $this->assertSame('Ai đó vừa sửa', $this->product->fresh()->getTranslation('meta_title', 'vi', false));
        $this->assertSame([], $this->events);

        // And a suggestion is applied at most once.
        $fresh = $this->suggest();
        $apply = fn () => $this->actingAs($this->editor)->postJson("/vi/admin/ai/meta-suggestions/{$fresh->id}/apply", ['option' => 0, 'title' => 'A', 'description' => 'B']);
        $apply()->assertOk();
        $apply()->assertStatus(410);
    }

    public function test_asking_and_applying_need_ai_use_and_the_records_own_update_permission(): void
    {
        $suggestion = $this->suggest();
        $noAi = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Catalog', 'permissions' => ['products.update']])->id]);
        $noProducts = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Writer', 'permissions' => ['posts.update', 'ai.use']])->id]);

        $ask = ['entity_type' => 'product', 'entity_id' => $this->product->id, 'content_locale' => 'vi'];
        $this->actingAs($noAi)->postJson('/vi/admin/ai/meta-suggestions', $ask)->assertForbidden();
        $this->actingAs($noProducts)->postJson('/vi/admin/ai/meta-suggestions', $ask)->assertForbidden();
        $this->actingAs($noProducts)->postJson("/vi/admin/ai/meta-suggestions/{$suggestion->id}/apply", ['option' => 0, 'title' => 'x', 'description' => 'y'])->assertForbidden();

        Http::assertSentCount(1);
        $this->assertSame('Ống nhựa', $this->product->fresh()->getTranslation('meta_title', 'vi', false));
    }

    public function test_the_feature_switch_closes_the_endpoints_and_hides_the_button(): void
    {
        $this->actingAs($this->editor)->get("/vi/admin/products/{$this->product->id}/edit")
            ->assertOk()->assertSee('js-ai-meta-suggest', false)
            // The button's fetch() sends this token; without the tag every click was a 419.
            ->assertSee('<meta name="csrf-token" content="'.csrf_token().'">', false);

        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false]);

        $this->actingAs($this->editor)->get("/vi/admin/products/{$this->product->id}/edit")
            ->assertOk()->assertDontSee('js-ai-meta-suggest', false);
        $this->actingAs($this->editor)
            ->postJson('/vi/admin/ai/meta-suggestions', ['entity_type' => 'product', 'entity_id' => $this->product->id, 'content_locale' => 'vi'])
            ->assertForbidden();
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/tasks/'));
    }

    public function test_superadmin_is_not_held_back_by_a_switched_off_record_feature(): void
    {
        // Local install whose package mapped to no features: catalog off, superadmin still edits products.
        FeatureSetting::query()->where('feature_code', 'catalog')->update(['is_enabled' => false]);
        $superadmin = User::factory()->create(['role_id' => Role::query()->create([
            'name' => 'Superadmin', 'is_system' => true, 'is_superadmin' => true, 'permissions' => ['*'],
        ])->id]);
        $ask = ['entity_type' => 'product', 'entity_id' => $this->product->id, 'content_locale' => 'vi'];

        $this->actingAs($superadmin)->postJson('/vi/admin/ai/meta-suggestions', $ask)->assertOk();

        // Staff keep the feature rule, and get a message instead of an empty one.
        $this->actingAs($this->editor)->postJson('/vi/admin/ai/meta-suggestions', $ask)
            ->assertNotFound()->assertJsonPath('message', __('ai.errors.not_found'));
    }

    public function test_a_gateway_refusal_becomes_a_plain_message_and_stores_nothing(): void
    {
        $this->metaResponse = Http::response(['ok' => false, 'reason' => 'quota_exceeded'], 429);

        $this->actingAs($this->editor)
            ->postJson('/vi/admin/ai/meta-suggestions', ['entity_type' => 'product', 'entity_id' => $this->product->id, 'content_locale' => 'vi'])
            ->assertStatus(429)
            ->assertJsonPath('reason', 'quota_exceeded')
            ->assertJsonPath('message', __('ai.errors.quota_exceeded'));

        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_rejecting_reports_it_and_leaves_the_record_alone(): void
    {
        $suggestion = $this->suggest();

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/meta-suggestions/{$suggestion->id}/reject", ['reason' => 'none_fit'])->assertOk();

        $this->assertSame(AiSuggestion::REJECTED, $suggestion->fresh()->status);
        $this->assertSame([['event' => 'rejected', 'reason_code' => 'none_fit']], $this->events);
        $this->assertSame('Ống nhựa', $this->product->fresh()->getTranslation('meta_title', 'vi', false));
    }

    public function test_a_post_gets_its_seo_fields_and_score_updated(): void
    {
        $post = Post::query()->create([
            'title' => ['vi' => 'Cách chọn ống nhựa'],
            'slug' => 'cach-chon-ong-nhua',
            'content' => ['vi' => '<p>Nội dung bài viết về ống nhựa uPVC.</p>'],
            'is_active' => true,
            'seo_keys' => 'ống nhựa uPVC',
        ]);

        $suggestion = $this->suggest('post', $post->id);
        // The checker scores the meta against this keyword, so the model gets it.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/tasks/meta.suggest') && $request['focus_keyword'] === 'ống nhựa uPVC');
        $this->actingAs($this->editor)->postJson("/vi/admin/ai/meta-suggestions/{$suggestion->id}/apply", [
            'option' => 2, 'title' => 'Nên chọn ống nhựa nào cho nhà?', 'description' => 'Ống uPVC: bền, nhẹ, dễ lắp đặt.',
        ])->assertOk();

        $post->refresh();
        $this->assertSame('Nên chọn ống nhựa nào cho nhà?', $post->getTranslation('seo_title', 'vi', false));
        $this->assertNotNull($post->seo_score);
    }

    private function suggest(string $type = 'product', ?int $id = null): AiSuggestion
    {
        $id = $this->actingAs($this->editor)
            ->postJson('/vi/admin/ai/meta-suggestions', ['entity_type' => $type, 'entity_id' => $id ?? $this->product->id, 'content_locale' => 'vi'])
            ->assertOk()
            ->json('data.suggestion_id');

        return AiSuggestion::query()->findOrFail($id);
    }
}
