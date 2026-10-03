<?php

namespace Tests\Feature;

use App\Models\AiBulkJob;
use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bulk SEO fixes and image alts: built like the single tools, applied through them. */
class AiBulkFixAltTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private User $editor;

    /** @var array<string, list<array<string, mixed>>> items the gateway received, by job type */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);
        $this->editor = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'posts.update', 'ai.use', 'ai.apply']])->id]);

        Http::fake([
            'gw.test/v1/jobs/bulk.*' => function (Request $request) {
                $type = basename((string) parse_url($request->url(), PHP_URL_PATH));
                $this->sent[$type] = $request['items'];

                return Http::response(['ok' => true, 'job_id' => $type === 'bulk.alt' ? 702 : 701, 'credits_estimated' => count($request['items']), 'credits_remaining' => 50], 202);
            },
            'gw.test/v1/jobs/*' => function (Request $request) {
                // Every matching stub is called; this one answers job reads only.
                if ($request->method() !== 'GET') {
                    return null;
                }
                $type = str_ends_with($request->url(), '702') ? 'bulk.alt' : 'bulk.seo_fix';
                $items = array_map(fn (array $input, int $i) => [
                    'position' => $i, 'entity_type' => $input['entity_type'], 'entity_id' => $input['entity_id'], 'locale' => 'vi',
                    'status' => 'done', 'suggestion_id' => 800 + $i + ($type === 'bulk.alt' ? 50 : 0), 'error_reason' => null,
                    'result' => $type === 'bulk.alt'
                        ? ['alt' => 'Bó ống nhựa uPVC trắng '.$i, 'src' => $input['src']]
                        : ['seo_title' => 'Thi công nhà phố: những điều cần chuẩn bị kỹ', 'seo_description' => 'Thi công nhà phố cần chuẩn bị kỹ về giấy phép, ngân sách và đội thợ. Bài viết tổng hợp các bước quan trọng trước khi khởi công.',
                            'intro_html' => '<p>Thi công nhà phố cần chuẩn bị <a href="/x">liên kết lạ</a>.</p>', 'notes' => [['rule' => 'title_length', 'note' => 'Viết lại tiêu đề.']]],
                ], $this->sent[$type], array_keys($this->sent[$type]));

                return Http::response(['ok' => true, 'job' => ['id' => 1, 'status' => 'completed', 'stop_reason' => null, 'items' => $items]]);
            },
            'gw.test/v1/suggestions/*/events' => Http::response(['ok' => true]),
        ]);
    }

    public function test_posts_failing_text_rules_get_fixes_and_a_changed_link_in_the_opening_is_dropped(): void
    {
        $post = $this->makePost('<p>'.trim(str_repeat('thi công nhà phố cần chuẩn bị kỹ ', 40)).'</p><h2>Chuẩn bị</h2><p>Nội dung.</p>', 'thi công nhà phố', 'Lưu ý');
        // No focus keyword: the checker cannot score it, so it is not sent.
        $this->makePost('<p>Bài khác.</p>', null, null);

        $this->actingAs($this->editor)->post('/vi/admin/ai/bulk', ['kind' => 'seo_fix', 'content_locale' => 'vi'])->assertRedirect()->assertSessionMissing('error');
        $this->assertCount(1, $this->sent['bulk.seo_fix']);
        $this->assertContains('title_length', $this->sent['bulk.seo_fix'][0]['failing_rules']);

        $job = AiBulkJob::query()->sole();
        $this->actingAs($this->editor)->get("/vi/admin/ai/bulk/{$job->id}")->assertOk()
            ->assertSee('Thi công nhà phố: những điều cần chuẩn bị kỹ')
            // The rewritten opening added a link: never offered.
            ->assertDontSee(__('ai.bulk.use_intro'));

        $id = AiSuggestion::query()->sole()->id;
        $this->actingAs($this->editor)->post("/vi/admin/ai/bulk/{$job->id}/apply", [
            'chosen' => [$id], 'seo_title' => [$id => 'Thi công nhà phố: chuẩn bị gì trước khi khởi công?'], 'seo_description' => [$id => ''],
        ])->assertSessionHas('success');

        $this->assertSame('Thi công nhà phố: chuẩn bị gì trước khi khởi công?', $post->fresh()->getTranslation('seo_title', 'vi'));
        $this->assertSame('', (string) $post->fresh()->getTranslation('seo_description', 'vi', false));
    }

    public function test_images_without_alt_are_sent_with_their_bytes_and_alts_are_applied_per_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/ong.png', base64_decode(self::PNG));
        Storage::disk('public')->put('media/van.png', base64_decode(self::PNG));
        $post = $this->makePost('<p>Bài</p><img src="/storage/media/ong.png"><img src="/storage/media/van.png"><img src="https://other.vn/x.png"><img src="/storage/media/co.png" alt="Đã có">', null, null);

        $this->actingAs($this->editor)->get('/vi/admin/ai/bulk')->assertOk()->assertSee(__('ai.bulk.kinds.alt.count', ['count' => 3]));
        $this->actingAs($this->editor)->post('/vi/admin/ai/bulk', ['kind' => 'alt', 'content_locale' => 'vi'])->assertRedirect()->assertSessionMissing('error');

        // An image on another host is never fetched or sent.
        $this->assertSame(['/storage/media/ong.png', '/storage/media/van.png'], array_column($this->sent['bulk.alt'], 'src'));
        $this->assertSame(['mime_type' => 'image/png', 'data' => self::PNG], $this->sent['bulk.alt'][0]['image']);

        $job = AiBulkJob::query()->sole();
        $this->actingAs($this->editor)->get("/vi/admin/ai/bulk/{$job->id}")->assertOk()->assertSee('Bó ống nhựa uPVC trắng 0');

        // The second image got an alt by hand after the run.
        $post->setTranslation('content', 'vi', str_replace('<img src="/storage/media/van.png">', '<img src="/storage/media/van.png" alt="Van tay">', $post->getTranslation('content', 'vi')))->save();

        $ids = AiSuggestion::query()->orderBy('id')->pluck('id')->all();
        $this->actingAs($this->editor)->post("/vi/admin/ai/bulk/{$job->id}/apply", [
            'chosen' => $ids, 'alt' => [$ids[0] => 'Ống nhựa uPVC', $ids[1] => 'Van'],
        ])->assertSessionHas('warning', __('ai.bulk.applied', ['applied' => 1, 'conflicts' => 1, 'closed' => 0]));

        $html = (string) $post->fresh()->getTranslation('content', 'vi');
        $this->assertStringContainsString('<img alt="Ống nhựa uPVC" src="/storage/media/ong.png"', $html);
        $this->assertStringContainsString('alt="Van tay"', $html);
    }

    public function test_a_file_name_alt_counts_as_missing(): void
    {
        $images = app(\App\Services\AiGateway\AltTextService::class)->imagesWithoutAlt(
            '<img src="/storage/media/ong-nhua.png" alt="ong-nhua.png" /><img src="/storage/media/van.jpg" alt="Van" />'
            .'<img src="/storage/media/van.jpg" alt="Van khoá đồng" /><img src="/storage/media/co.png" alt="co" />',
        );

        $this->assertSame(['/storage/media/ong-nhua.png', '/storage/media/van.jpg', '/storage/media/co.png'], $images);
    }

    private function makePost(string $content, ?string $keyword, ?string $seoTitle): Post
    {
        return Post::query()->create(array_filter([
            'title' => ['vi' => 'Thi công nhà phố cần lưu ý gì'], 'slug' => 'bai-'.uniqid(), 'content' => ['vi' => $content], 'is_active' => true,
            'seo_keys' => $keyword, 'seo_title' => $seoTitle ? ['vi' => $seoTitle] : null,
        ], fn ($value) => $value !== null));
    }
}
