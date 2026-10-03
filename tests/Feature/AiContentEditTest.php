<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiContentEditTest extends TestCase
{
    use RefreshDatabase;

    private const SAVED = '<p>Ong nhua uPVC dung cho cap thoat nuoc. <a href="/vi/chinh-sach/bao-hanh">Bao hanh</a></p><p><img src="/storage/media/ong.jpg" alt="Ống"></p>';

    private const EDITED = '<p>Ống nhựa uPVC dùng cho cấp thoát nước. <a href="/vi/chinh-sach/bao-hanh">Bảo hành</a></p><p><img src="/storage/media/ong.jpg" alt="Ống"></p>';

    private User $editor;

    private Product $product;

    private mixed $editResponse = null;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'catalog', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);

        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['products.view', 'products.update', 'pages.update', 'ai.use', 'ai.apply']]);
        $this->editor = User::factory()->create(['role_id' => $role->id]);
        $this->product = Product::query()->create([
            'name' => ['vi' => 'Ống nhựa uPVC'], 'slug' => 'ong-nhua', 'sku' => 'UPVC', 'price' => 1000, 'is_active' => true,
            'description' => ['vi' => self::SAVED],
        ]);

        Http::fake([
            'gw.test/v1/tasks/content.edit' => fn () => $this->editResponse ?? $this->answer(self::EDITED),
            'gw.test/v1/suggestions/*/events' => function (Request $request) {
                $this->events[] = $request->data();

                return Http::response(['ok' => true]);
            },
        ]);
    }

    public function test_the_saved_body_is_edited_and_shown_sanitized_without_touching_the_record(): void
    {
        $this->editResponse = $this->answer(self::EDITED.'<script>alert(1)</script>');

        $response = $this->ask()->assertOk()
            ->assertJsonPath('data.changes.0.after', 'Ống nhựa');

        $this->assertStringNotContainsString('<script', $response->json('data.html'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://gw.test/v1/tasks/content.edit'
            // What is stored on the site, not what the browser holds.
            && $request['html'] === self::SAVED
            && $request['level'] === 'light'
            && $request->hasHeader('Idempotency-Key'));

        $this->assertSame(self::SAVED, $this->product->fresh()->getTranslation('description', 'vi', false));
        $this->assertSame(['html' => self::SAVED], AiSuggestion::query()->sole()->old_values);
    }

    public function test_an_edit_that_changes_a_link_or_image_is_discarded(): void
    {
        $this->editResponse = $this->answer(str_replace('/vi/chinh-sach/bao-hanh', 'https://spam.example', self::EDITED));

        $this->ask()->assertStatus(422)->assertJsonPath('reason', 'unsafe_output')
            ->assertJsonPath('message', __('ai.errors.unsafe_output'));

        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_applying_writes_the_edit_logs_it_and_reports_back(): void
    {
        $id = $this->ask()->json('data.suggestion_id');

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/content-edits/$id/apply")
            ->assertOk()->assertJsonPath('data.reload', false);

        $this->assertStringContainsString('Ống nhựa uPVC dùng cho cấp thoát nước', $this->product->fresh()->getTranslation('description', 'vi', false));
        $this->assertTrue(AdminActivityLog::query()->where('action', 'ai_content_edited')->exists());
        $this->assertSame([['event' => 'applied', 'option_index' => 0, 'edit_distance' => 0]], $this->events);
    }

    public function test_a_body_changed_since_the_edit_is_not_overwritten(): void
    {
        $id = $this->ask()->json('data.suggestion_id');
        $this->product->setTranslation('description', 'vi', '<p>Ai đó vừa sửa</p>')->save();

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/content-edits/$id/apply")
            ->assertStatus(409)->assertJsonPath('message', __('ai.errors.conflict_content'));

        $this->assertSame('<p>Ai đó vừa sửa</p>', $this->product->fresh()->getTranslation('description', 'vi', false));
    }

    public function test_a_policy_page_is_written_through_the_page_content_service(): void
    {
        $page = Page::query()->create([
            'title' => ['vi' => 'Bảo hành'], 'slug' => 'bao-hanh', 'published_html' => ['vi' => self::SAVED, 'en' => '<p>Warranty</p>'],
            'is_active' => true, 'published_at' => now(),
        ]);

        $id = $this->ask('page', $page->id)->assertOk()->json('data.suggestion_id');
        $this->actingAs($this->editor)->postJson("/vi/admin/ai/content-edits/$id/apply")
            ->assertOk()->assertJsonPath('data.reload', true);

        $page->refresh();
        $this->assertStringContainsString('Ống nhựa uPVC', $page->getTranslation('published_html', 'vi', false));
        // Other locales are kept, as with the inline editor.
        $this->assertSame('<p>Warranty</p>', $page->getTranslation('published_html', 'en', false));
    }

    public function test_suggestions_cannot_cross_between_meta_and_content_flows(): void
    {
        $id = $this->ask()->json('data.suggestion_id');

        $this->actingAs($this->editor)->postJson("/vi/admin/ai/meta-suggestions/$id/apply", ['option' => 0, 'title' => 'x', 'description' => 'y'])
            ->assertNotFound();
        $this->assertSame(self::SAVED, $this->product->fresh()->getTranslation('description', 'vi', false));
    }

    public function test_an_empty_body_is_refused_before_spending_a_credit(): void
    {
        $this->product->setTranslation('description', 'vi', '<p> </p>')->save();

        $this->ask()->assertStatus(422)->assertJsonPath('message', __('ai.edit.empty'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/tasks/'));
    }

    public function test_the_button_sits_beside_the_editor_for_allowed_users_only(): void
    {
        $this->actingAs($this->editor)->get("/vi/admin/products/{$this->product->id}/edit")
            ->assertOk()->assertSee('js-ai-edit', false)->assertSee('data-target="description_vi"', false);

        $readOnly = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Catalog', 'permissions' => ['products.view', 'products.update']])->id]);
        $this->actingAs($readOnly)->get("/vi/admin/products/{$this->product->id}/edit")
            ->assertOk()->assertDontSee('js-ai-edit', false);
    }

    private function ask(string $type = 'product', ?int $id = null)
    {
        return $this->actingAs($this->editor)->postJson('/vi/admin/ai/content-edits', [
            'entity_type' => $type, 'entity_id' => $id ?? $this->product->id, 'content_locale' => 'vi', 'level' => 'light',
        ]);
    }

    private function answer(string $html)
    {
        return Http::response([
            'ok' => true,
            'result' => ['html' => $html, 'changes' => [['before' => 'Ong nhua', 'after' => 'Ống nhựa', 'reason' => 'Thêm dấu tiếng Việt']]],
            'suggestion_id' => 777,
            'usage' => ['credits_charged' => 2, 'credits_used' => 2, 'credits_limit' => 100],
        ]);
    }
}
