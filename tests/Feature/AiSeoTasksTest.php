<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "This week's SEO work": the gateway ranks, the website links its editors and asks for advice. */
class AiSeoTasksTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    private int $listCalls = 0;

    private int $adviceCalls = 0;

    /** @var list<array{0: string, 1: string}> */
    private array $marks = [];

    /** @var array<string, mixed>|null */
    private ?array $adviceInput = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);
        foreach (['ai_seo', 'cms_page', 'catalog', 'analytics'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        app(AnalyticsReport::class)->saveSettings(['gsc_site_url' => 'sc-domain:spico.vn']);

        $this->post = Post::query()->create(['title' => ['vi' => 'Cách chọn ống nhựa'], 'slug' => 'cach-chon-ong', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);

        Http::fake([
            'gw.test/v1/project/connections' => Http::response(['ok' => true]),
            'gw.test/v1/project' => Http::response(['ok' => true, 'project' => []]),
            'gw.test/v1/seo-tasks/*/status' => function (Request $request) {
                $this->marks[] = [$request->url(), $request['status']];

                return Http::response(['ok' => true]);
            },
            'gw.test/v1/seo-tasks' => function () {
                $this->listCalls++;

                return Http::response(['ok' => true, 'status' => 'ok', 'synced_through' => '2026-09-29',
                    'totals' => ['days' => 28, 'current' => ['clicks' => 263, 'impressions' => 3456, 'ctr' => 0.0761, 'position' => 6.3],
                        'previous' => ['clicks' => 288, 'impressions' => 3676, 'ctr' => 0.0783, 'position' => 8.0]],
                    'tasks' => [
                        ['id' => 'aaaaaaaaaaaaaaaa', 'type' => 'low_ctr', 'priority' => 'important', 'gain' => 12, 'page' => 'https://spico.vn/blog/cach-chon-ong',
                            'entity' => ['type' => 'post', 'id' => $this->post->id, 'locale' => 'vi', 'title' => 'Cách chọn ống nhựa', 'meta_title' => 'Ống', 'meta_description' => null, 'focus_keyword' => 'chọn ống'],
                            'metrics' => ['clicks' => 13, 'impressions' => 621, 'ctr' => 0.0209, 'position' => 5.4, 'previous_clicks' => 5],
                            'issues' => [], 'queries' => [['query' => 'cách chọn ống nhựa', 'clicks' => 3, 'impressions' => 300, 'position' => 5.1]]],
                        ['id' => 'bbbbbbbbbbbbbbbb', 'type' => 'declining', 'priority' => 'important', 'gain' => 6, 'page' => 'https://spico.vn/',
                            'entity' => null, 'metrics' => ['clicks' => 4, 'impressions' => 16, 'ctr' => 0.25, 'position' => 6.7, 'previous_clicks' => 10],
                            'issues' => [], 'queries' => []],
                        // A record deleted on the website since the last index push.
                        ['id' => 'cccccccccccccccc', 'type' => 'on_page', 'gain' => 1, 'page' => null,
                            'entity' => ['type' => 'post', 'id' => 99999, 'locale' => 'vi', 'title' => 'Bài đã xoá'],
                            'metrics' => ['impressions' => 0], 'issues' => ['missing_meta_title'], 'queries' => []],
                    ],
                ]);
            },
            'gw.test/v1/tasks/seo.task_advice' => function (Request $request) {
                $this->adviceCalls++;
                $this->adviceInput = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => 9,
                    'result' => ['summary' => 'Trang ở vị trí 5 nhưng chỉ 2% người tìm bấm vào.', 'steps' => ['Viết lại tiêu đề SEO.', 'Thêm mô tả SEO.']],
                    'usage' => ['credits_charged' => 1, 'credits_used' => 4, 'credits_limit' => 100]]);
            },
        ]);
    }

    public function test_the_page_lists_tasks_with_editor_links_only_where_the_admin_can_edit(): void
    {
        $this->actingAs($this->user(['ai.use', 'posts.update']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()
            ->assertSee(__('ai.tasks.types.low_ctr'))
            ->assertSee('cách chọn ống nhựa')
            ->assertSee(route('admin.posts.edit', ['post' => $this->post->id]), false)
            ->assertDontSee('/admin/posts/99999/edit', false)
            ->assertSee(__('ai.tasks.no_editor'));

        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()
            ->assertDontSee(route('admin.posts.edit', ['post' => $this->post->id]), false);
    }

    public function test_the_dashboard_card_gets_totals_and_the_top_tasks(): void
    {
        $user = $this->user(['ai.use', 'posts.update']);

        $this->actingAs($user)->get('/vi/admin')->assertOk()->assertSee(route('admin.ai.seo-tasks.summary'), false);

        $this->actingAs($user)->getJson('/vi/admin/ai/seo-tasks/summary')->assertOk()
            ->assertJsonPath('data.totals.current.clicks', 263)
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.tasks.0.title', 'Cách chọn ống nhựa')
            ->assertJsonPath('data.tasks.1.title', '/')
            ->assertJsonPath('data.tasks.1.edit_url', null);

        // Cached: the dashboard does not ask the gateway on every load.
        $this->actingAs($user)->getJson('/vi/admin/ai/seo-tasks/summary')->assertOk();
        $this->assertSame(1, $this->listCalls);
    }

    public function test_advice_is_built_from_the_list_and_asked_once_per_sync(): void
    {
        $user = $this->user(['ai.use']);

        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/advice')->assertOk()
            ->assertJsonPath('data.advice.steps.0', 'Viết lại tiêu đề SEO.')
            ->assertJsonPath('data.usage.credits_used', 4);

        $this->assertSame('low_ctr', $this->adviceInput['task_type']);
        $this->assertSame(['cách chọn ống nhựa'], $this->adviceInput['queries']);
        $this->assertSame('chọn ống', $this->adviceInput['focus_keyword']);

        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/advice')->assertOk();
        $this->assertSame(1, $this->adviceCalls);

        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/dddddddddddddddd/advice')->assertNotFound();
    }

    public function test_tasks_are_grouped_and_can_be_marked_done(): void
    {
        $user = $this->user(['ai.use']);

        $this->actingAs($user)->get('/vi/admin/ai/seo-tasks')->assertOk()
            ->assertSee(__('ai.tasks.priorities.important'))
            ->assertSee(route('admin.ai.seo-tasks.status', ['task' => 'aaaaaaaaaaaaaaaa']), false);

        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/status', ['status' => 'done'])
            ->assertOk()->assertJsonPath('message', __('ai.tasks.marked.done'));
        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/status', ['status' => 'deleted'])->assertStatus(422);

        $this->assertSame([['https://gw.test/v1/seo-tasks/aaaaaaaaaaaaaaaa/status', 'done']], $this->marks);
        // The list is asked again after a mark, not served from the old cache.
        $this->actingAs($user)->getJson('/vi/admin/ai/seo-tasks/summary')->assertOk()->assertJsonPath('data.tasks.0.priority', 'important');
        $this->assertSame(2, $this->listCalls);
    }

    public function test_without_search_console_the_page_says_how_to_connect(): void
    {
        app(AnalyticsReport::class)->saveSettings([]);

        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()->assertSee(__('ai.tasks.not_connected'));
        $this->assertSame(0, $this->listCalls);
    }

    public function test_it_needs_the_feature_and_the_permission(): void
    {
        $this->actingAs($this->user(['posts.update']))->getJson('/vi/admin/ai/seo-tasks/summary')->assertForbidden();

        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false]);
        $this->actingAs($this->user(['ai.use']))->getJson('/vi/admin/ai/seo-tasks/summary')->assertForbidden();
        $this->actingAs($this->user(['ai.use']))->get('/vi/admin')->assertOk()->assertDontSee(route('admin.ai.seo-tasks.summary'), false);
    }

    private function user(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions])->id]);
    }
}
