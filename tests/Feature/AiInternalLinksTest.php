<?php

namespace Tests\Feature;

use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\AiGateway\InternalLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiInternalLinksTest extends TestCase
{
    use RefreshDatabase;

    private const BODY = '<p>Nên chọn <strong>ống nhựa uPVC</strong> cho hệ thống cấp nước.</p><p>Keo dán ống rất quan trọng. Xem <a href="/vi/bang-gia">bảng giá keo dán ống</a>.</p>';

    private User $editor;

    private Post $post;

    /** @var array<string, mixed>|null */
    private ?array $sent = null;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);

        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'posts.update', 'ai.use', 'ai.apply']]);
        $this->editor = User::factory()->create(['role_id' => $role->id]);
        $this->post = Post::query()->create(['title' => ['vi' => 'Cách chọn ống'], 'slug' => 'cach-chon-ong', 'content' => ['vi' => self::BODY], 'is_active' => true]);

        Http::fake([
            'gw.test/v1/tasks/content.internal_links' => function (Request $request) {
                $this->sent = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => 77, 'usage' => ['credits_charged' => 1, 'credits_used' => 2, 'credits_limit' => 100],
                    'result' => ['links' => [
                        ['anchor' => 'ống nhựa uPVC', 'url' => 'https://spico.vn/vi/san-pham/ong-nhua-upvc', 'title' => 'Ống nhựa uPVC', 'reason' => 'Sản phẩm được nhắc tới.', 'needs_review' => false],
                        ['anchor' => 'keo dán ống', 'url' => 'https://spico.vn/vi/danh-muc/keo-dan-ong', 'title' => 'Keo dán ống', 'reason' => 'Danh mục liên quan.', 'needs_review' => true],
                        // Not a website URL: dropped before the admin sees it.
                        ['anchor' => 'hệ thống cấp nước', 'url' => 'javascript:alert(1)', 'title' => 'x', 'reason' => 'x', 'needs_review' => false],
                    ]]]);
            },
            'gw.test/v1/suggestions/*/events' => function (Request $request) {
                $this->events[] = $request->data();

                return Http::response(['ok' => true]);
            },
        ]);
    }

    public function test_links_are_suggested_from_the_saved_body_and_inserted_on_existing_phrases(): void
    {
        $response = $this->actingAs($this->editor)->postJson('/vi/admin/ai/internal-links', ['entity_type' => 'post', 'entity_id' => $this->post->id, 'content_locale' => 'vi'])
            ->assertOk()
            ->assertJsonCount(2, 'data.links')
            ->assertJsonPath('data.links.1.needs_review', true);

        $this->assertSame(self::BODY, $this->sent['html']);

        $this->actingAs($this->editor)->postJson($response->json('data.apply_url'), ['chosen' => [0, 1]])
            ->assertOk()
            ->assertJsonPath('message', __('ai.links.applied', ['count' => 2]));

        // One weak label per offered link, so the gateway can follow each to its decision.
        $this->assertSame([['option_applied', 0], ['option_applied', 1]], collect($this->events)->filter(fn ($e) => str_starts_with($e['event'], 'option_'))
            ->map(fn ($e) => [$e['event'], $e['option_index']])->values()->all());

        $html = (string) $this->post->fresh()->getTranslation('content', 'vi');
        $this->assertStringContainsString('<strong><a href="https://spico.vn/vi/san-pham/ong-nhua-upvc">ống nhựa uPVC</a></strong>', $html);
        // The first plain occurrence, never the one already inside a link.
        $this->assertStringContainsString('<a href="https://spico.vn/vi/danh-muc/keo-dan-ong">Keo dán ống</a> rất quan trọng', $html);
        $this->assertStringContainsString('<a href="/vi/bang-gia">bảng giá keo dán ống</a>', $html);
        // The words themselves are unchanged.
        $this->assertSame(strip_tags(self::BODY), strip_tags($html));
        $this->assertSame(AiSuggestion::APPLIED, AiSuggestion::query()->value('status'));
    }

    public function test_a_body_changed_since_the_suggestion_is_not_overwritten(): void
    {
        $url = $this->actingAs($this->editor)->postJson('/vi/admin/ai/internal-links', ['entity_type' => 'post', 'entity_id' => $this->post->id, 'content_locale' => 'vi'])
            ->json('data.apply_url');

        $this->post->setTranslation('content', 'vi', '<p>Nội dung mới.</p>')->save();

        $this->actingAs($this->editor)->postJson($url, ['chosen' => [0]])->assertStatus(409);
        $this->assertSame('<p>Nội dung mới.</p>', $this->post->fresh()->getTranslation('content', 'vi'));
    }

    public function test_it_needs_the_permission_to_edit_the_post(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Viewer', 'permissions' => ['posts.view', 'ai.use']])->id]);

        $this->actingAs($viewer)->postJson('/vi/admin/ai/internal-links', ['entity_type' => 'post', 'entity_id' => $this->post->id, 'content_locale' => 'vi'])
            ->assertForbidden();
        $this->assertNull($this->sent);
    }

    public function test_insertion_skips_phrases_that_are_not_plain_text(): void
    {
        [$html, $count] = InternalLinkService::insertLinks('<p>Giá <a href="/x">ống nhựa</a> tốt.</p>', [['anchor' => 'ống nhựa', 'url' => '/y']]);

        $this->assertSame(0, $count);
        $this->assertSame('<p>Giá <a href="/x">ống nhựa</a> tốt.</p>', $html);
    }
}
