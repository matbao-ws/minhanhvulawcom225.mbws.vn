<?php

namespace Tests\Feature;

use App\Models\AiSuggestion;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\AnalyticsReport;
use App\Support\AiAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** "Let the AI fix it" on this week's SEO work, and the before/after results of fixed tasks. */
class AiSeoTaskFixTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    /** @var array<string, array<string, mixed>> last input per gateway task */
    private array $inputs = [];

    /** @var list<array<string, mixed>> */
    private array $marks = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);
        foreach (['ai_seo', 'cms_page', 'catalog', 'analytics'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        app(AnalyticsReport::class)->saveSettings(['gsc_site_url' => 'sc-domain:spico.vn']);

        $this->post = Post::query()->create([
            'title' => ['vi' => 'Cách chọn ống nhựa'],
            'slug' => 'cach-chon-ong',
            'content' => ['vi' => '<p>Ống nhựa PPR dùng cho nước nóng.</p>'],
            'seo_title' => ['vi' => 'Ống'],
            'is_active' => true,
        ]);

        $entity = ['type' => 'post', 'id' => $this->post->id, 'locale' => 'vi', 'title' => 'Cách chọn ống nhựa', 'meta_title' => 'Ống', 'meta_description' => null, 'focus_keyword' => null];

        Http::fake([
            'gw.test/v1/project/connections' => Http::response(['ok' => true]),
            'gw.test/v1/project' => Http::response(['ok' => true, 'project' => []]),
            'gw.test/v1/suggestions/*/events' => Http::response(['ok' => true]),
            'gw.test/v1/seo-tasks/outcomes' => Http::response(['ok' => true, 'synced_through' => '2026-10-20',
                'summary' => ['waiting' => 1, 'measured' => 1, 'better' => 1, 'flat' => 0, 'worse' => 0, 'too_little_data' => 0, 'extra_clicks' => 21],
                'outcomes' => [
                    ['task_id' => 'eeeeeeeeeeeeeeee', 'task_type' => 'low_ctr', 'page' => 'https://spico.vn/blog/ong-ppr', 'entity' => ['type' => 'post', 'id' => 5, 'locale' => 'vi', 'title' => 'Ống PPR là gì'],
                        'applied_by' => 'ai', 'tools' => ['meta.suggest'], 'fixed_on' => '2026-10-10', 'ready_on' => '2026-11-07', 'days_left' => 18, 'status' => 'waiting',
                        'before' => ['clicks' => 8, 'impressions' => 900, 'ctr' => 0.0089, 'position' => 4.2], 'after' => null,
                        'site_change' => null, 'page_change' => null, 'net_change' => null, 'extra_clicks' => null, 'verdict' => null],
                    ['task_id' => 'ffffffffffffffff', 'task_type' => 'striking_distance', 'page' => 'https://spico.vn/blog/ong-hdpe', 'entity' => ['type' => 'post', 'id' => 6, 'locale' => 'vi', 'title' => 'Ống HDPE giá bao nhiêu'],
                        'applied_by' => 'owner', 'tools' => [], 'fixed_on' => '2026-08-01', 'ready_on' => '2026-08-29', 'days_left' => 0, 'status' => 'measured',
                        'before' => ['clicks' => 8, 'impressions' => 400, 'ctr' => 0.02, 'position' => 12.1], 'after' => ['clicks' => 30, 'impressions' => 700, 'ctr' => 0.0429, 'position' => 7.4],
                        'site_change' => 0.1, 'page_change' => 2.75, 'net_change' => 2.65, 'extra_clicks' => 21, 'verdict' => 'better'],
                ]]),
            'gw.test/v1/seo-tasks/*/status' => function (Request $request) {
                $this->marks[] = $request->data();

                return Http::response(['ok' => true]);
            },
            'gw.test/v1/seo-tasks' => Http::response(['ok' => true, 'status' => 'ok', 'synced_through' => '2026-10-20',
                'totals' => ['days' => 28, 'current' => ['clicks' => 263, 'impressions' => 3456, 'ctr' => 0.0761, 'position' => 6.3],
                    'previous' => ['clicks' => 288, 'impressions' => 3676, 'ctr' => 0.0783, 'position' => 8.0],
                    'daily' => array_map(fn (int $day) => ['date' => sprintf('2026-09-%02d', $day), 'clicks' => 6 + $day % 5, 'impressions' => 110 + $day * 2,
                        'ctr' => 0.07, 'position' => $day === 3 ? null : 7 - $day / 10], range(1, 28))],
                'tasks' => [
                    ['id' => 'aaaaaaaaaaaaaaaa', 'type' => 'low_ctr', 'priority' => 'important', 'gain' => 12, 'page' => 'https://spico.vn/blog/cach-chon-ong',
                        'entity' => $entity, 'metrics' => ['clicks' => 13, 'impressions' => 621, 'ctr' => 0.0209, 'position' => 5.4, 'previous_clicks' => 5],
                        'issues' => [], 'queries' => [['query' => 'cách chọn ống nhựa', 'clicks' => 3, 'impressions' => 300, 'position' => 5.1]]],
                    ['id' => 'bbbbbbbbbbbbbbbb', 'type' => 'striking_distance', 'priority' => 'important', 'gain' => 9, 'page' => 'https://spico.vn/blog/cach-chon-ong',
                        'entity' => $entity, 'metrics' => ['clicks' => 2, 'impressions' => 400, 'ctr' => 0.005, 'position' => 12.3, 'previous_clicks' => 2],
                        'issues' => [], 'queries' => [
                            ['query' => 'ống nhựa ppr', 'clicks' => 1, 'impressions' => 250, 'position' => 11.0],
                            ['query' => 'ống ppr chịu nhiệt bao nhiêu độ', 'clicks' => 1, 'impressions' => 150, 'position' => 13.0],
                        ]],
                    ['id' => 'cccccccccccccccc', 'type' => 'declining', 'priority' => 'important', 'gain' => 6, 'page' => 'https://spico.vn/',
                        'entity' => null, 'metrics' => ['clicks' => 4, 'impressions' => 16, 'ctr' => 0.25, 'position' => 6.7, 'previous_clicks' => 10],
                        'issues' => [], 'queries' => []],
                ],
            ]),
            'gw.test/v1/tasks/meta.suggest' => function (Request $request) {
                $this->inputs['meta.suggest'] = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => 11, 'result' => ['focus_keyword' => 'cách chọn ống nhựa', 'options' => [
                    ['title' => 'Cách chọn ống nhựa đúng loại cho nhà bạn', 'description' => 'Hướng dẫn cách chọn ống nhựa PPR, PVC, HDPE theo nhu cầu nước nóng, nước lạnh và áp lực.', 'angle' => 'hướng dẫn'],
                    ['title' => 'Chọn ống nhựa nào? So sánh PPR, PVC, HDPE', 'description' => 'So sánh nhanh các loại ống nhựa phổ biến để chọn đúng cho công trình của bạn.', 'angle' => 'so sánh'],
                    ['title' => 'Ống nhựa nào bền nhất cho nước nóng?', 'description' => 'Ống PPR chịu nhiệt tốt, phù hợp nước nóng. Xem cách chọn ống nhựa đúng nhu cầu.', 'angle' => 'câu hỏi'],
                ]], 'usage' => ['credits_charged' => 1]]);
            },
            'gw.test/v1/tasks/content.query_gap' => function (Request $request) {
                $this->inputs['content.query_gap'] = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => 12, 'result' => [
                    'heading' => 'Ống PPR chịu được nhiệt độ bao nhiêu?',
                    'paragraphs' => ['Ống PPR thường dùng cho nước nóng sinh hoạt.'],
                    'bullets' => ['Phù hợp bình nóng lạnh'],
                    'covers' => ['ống ppr chịu nhiệt bao nhiêu độ'],
                ], 'usage' => ['credits_charged' => 2]]);
            },
            'gw.test/v1/tasks/content.faq' => function (Request $request) {
                $this->inputs['content.faq'] = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => 13, 'result' => ['items' => [
                    ['question' => 'Ống PPR chịu nhiệt bao nhiêu độ?', 'answer' => 'Ống PPR dùng tốt cho nước nóng sinh hoạt.'],
                ]], 'usage' => ['credits_charged' => 1]]);
            },
        ]);
    }

    public function test_each_task_says_what_the_ai_would_do_or_why_it_cannot(): void
    {
        $this->actingAs($this->user(['ai.use', 'ai.apply', 'posts.update']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()
            // Google Ads style: the optimisation score (263 clicks, +27 possible) and grouped recommendations.
            ->assertSee(__('ai.tasks.score.title'))
            ->assertSee('--score: 91', false)
            ->assertSee(__('ai.tasks.banner_button'))
            ->assertSee('class="seo-spark"', false)
            ->assertSee(__('ai.tasks.rank_up', ['count' => '1.7']))
            ->assertSee(__('ai.tasks.action.low_ctr'))
            ->assertSee(__('ai.tasks.evidence.low_ctr', ['impressions' => '621', 'clicks' => '13', 'ctr' => '2.1', 'position' => '5']))
            ->assertSee('data-seo-filter="snippet"', false)
            ->assertSee('data-seo-filter="decide"', false)
            ->assertDontSee('data-seo-filter="basics"', false)
            ->assertSee(__('ai.tasks.fix.steps.meta'))
            ->assertSee(__('ai.tasks.fix.steps.section'))
            ->assertSee(__('ai.tasks.fix.steps.faq'))
            ->assertSee(__('ai.tasks.fix.not_fixable'))
            ->assertSee('data-seo-fix', false)
            ->assertSee(route('admin.ai.seo-tasks.fix.apply', ['task' => 'aaaaaaaaaaaaaaaa']), false)
            ->assertSee('admin-assets/js/seo-tasks.js', false);

        // Without the right to apply AI changes the button is replaced by the reason.
        $this->actingAs($this->user(['ai.use', 'posts.update']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()
            ->assertDontSee('data-seo-fix>', false)
            ->assertSee(__('ai.tasks.fix.no_permission'));
    }

    public function test_each_figure_explains_its_own_number(): void
    {
        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()
            // A task's figures (low_ctr: 13 clicks, 621 impressions, position 5.4).
            ->assertSee(__('ai.tasks.stat_explain.ctr', ['value' => '2.1', 'clicks' => '13', 'impressions' => '621']))
            ->assertSee(__('ai.tasks.stat_explain.position', ['value' => 5.4, 'where' => __('ai.tasks.where.page1')]))
            // Striking distance at 12.3 is on page 2.
            ->assertSee(__('ai.tasks.stat_explain.position', ['value' => 12.3, 'where' => __('ai.tasks.where.page2')]))
            // A declining page compares with the 28 days before.
            ->assertSee(__('ai.tasks.stat_explain.clicks_declining', ['value' => '4', 'previous' => '10']))
            // The whole website, against the 28 days before.
            ->assertSee(__('ai.tasks.site_explain.clicks', ['value' => '263', 'previous' => '288']));
    }

    public function test_a_task_the_ai_cannot_fix_says_why_in_plain_words_and_what_to_do(): void
    {
        $task = fn (array $overrides) => array_merge(['id' => 'abababababababab', 'type' => 'low_ctr', 'priority' => 'important', 'gain' => 21,
            'page' => 'https://spico.vn/gioi-thieu', 'entity' => null, 'edit_url' => null, 'record_gone' => false, 'fix' => null,
            'metrics' => ['clicks' => 14, 'impressions' => 615, 'ctr' => 0.0228, 'position' => 4.5, 'previous_clicks' => 10],
            'issues' => [], 'queries' => [['query' => 'bệnh viện tiên yên', 'impressions' => 53]]], $overrides);
        // Rendered on its own, outside a request: the routes need the locale a request would carry.
        app()->setLocale('vi');
        URL::defaults(['locale' => 'vi']);
        $render = fn (array $overrides) => view('admin.ai.seo-tasks._task', ['task' => $task($overrides), 'canApply' => true])->render();

        // A theme page (no record behind it): Mat Bao fixes it, and the main button says so.
        $theme = $render([]);
        $this->assertStringContainsString(e(__('ai.tasks.fix.no_editor')), $theme);
        $this->assertStringContainsString('href="'.AiAccess::SUPPORT_URL.'"', $theme);
        $this->assertStringContainsString(e(__('ai.tasks.fix.ask_support')), $theme);
        $this->assertStringContainsString(e(__('ai.tasks.evidence.low_ctr', ['impressions' => '615', 'clicks' => '14', 'ctr' => '2.3', 'position' => '5'])), $theme);

        // A record this admin may not edit, and one deleted since: each says its own reason, no support button.
        $entity = ['type' => 'post', 'id' => 5, 'locale' => 'vi', 'title' => 'Giới thiệu'];
        $noAccess = $render(['entity' => $entity]);
        $this->assertStringContainsString(e(__('ai.tasks.fix.no_edit_permission')), $noAccess);
        $this->assertStringNotContainsString(AiAccess::SUPPORT_URL, $noAccess);
        $this->assertStringContainsString(e(__('ai.tasks.fix.record_gone')), $render(['entity' => $entity, 'record_gone' => true]));

        // Falling clicks are the owner's call even on a theme page.
        $this->assertStringContainsString(e(__('ai.tasks.fix.not_fixable')), $render(['type' => 'declining']));
    }

    public function test_low_ctr_rewrites_the_snippet_from_the_real_searches_and_starts_the_measurement(): void
    {
        $user = $this->user(['ai.use', 'ai.apply', 'posts.update']);

        $step = $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/fix/meta')->assertOk()
            ->assertJsonPath('data.step', 'meta')
            ->assertJsonPath('data.current.title', 'Ống')
            ->assertJsonCount(3, 'data.options');
        $this->assertSame(['cách chọn ống nhựa'], $this->inputs['meta.suggest']['search_queries']);

        // A step that is not in the task's plan does not run.
        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/fix/faq')->assertNotFound();

        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/fix', ['changes' => [[
            'step' => 'meta', 'suggestion_id' => $step->json('data.suggestion_id'), 'option' => 1,
            'title' => 'Chọn ống nhựa nào? So sánh PPR, PVC, HDPE', 'description' => 'Mô tả đã sửa tay.',
        ]]])->assertOk()->assertJsonPath('data.applied', ['meta'])->assertJsonPath('data.marked', true);

        $this->post->refresh();
        $this->assertSame('Chọn ống nhựa nào? So sánh PPR, PVC, HDPE', $this->post->getTranslation('seo_title', 'vi'));
        $this->assertSame('Mô tả đã sửa tay.', $this->post->getTranslation('seo_description', 'vi'));
        $this->assertSame([['status' => 'done', 'applied_by' => 'ai', 'tools' => ['meta.suggest']]], $this->marks);
    }

    public function test_striking_distance_adds_a_section_then_an_faq_from_the_question_searches(): void
    {
        $user = $this->user(['ai.use', 'ai.apply', 'posts.update']);

        $section = $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/bbbbbbbbbbbbbbbb/fix/section')->assertOk()
            ->assertJsonPath('data.section.covers', ['ống ppr chịu nhiệt bao nhiêu độ']);
        $faq = $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/bbbbbbbbbbbbbbbb/fix/faq')->assertOk();

        $this->assertSame(['ống nhựa ppr', 'ống ppr chịu nhiệt bao nhiêu độ'], $this->inputs['content.query_gap']['queries']);
        // Only the searches phrased as questions go to the FAQ.
        $this->assertSame(['ống ppr chịu nhiệt bao nhiêu độ'], $this->inputs['content.faq']['questions']);

        // Both were written for the same body; the FAQ follows the section applied just before it.
        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/bbbbbbbbbbbbbbbb/fix', ['changes' => [
            ['step' => 'faq', 'suggestion_id' => $faq->json('data.suggestion_id'), 'items' => [['question' => 'Ống PPR chịu nhiệt bao nhiêu độ?', 'answer' => 'Dùng tốt cho nước nóng.']]],
            ['step' => 'section', 'suggestion_id' => $section->json('data.suggestion_id'), 'section' => [
                'heading' => 'Ống PPR chịu được nhiệt độ bao nhiêu? <script>x</script>', 'paragraphs' => ['Ống PPR thường dùng cho nước nóng sinh hoạt.'], 'bullets' => [],
            ]],
        ]])->assertOk()->assertJsonPath('data.applied', ['section', 'faq']);

        $body = (string) $this->post->refresh()->getTranslation('content', 'vi');
        $this->assertStringStartsWith('<p>Ống nhựa PPR dùng cho nước nóng.</p><h2>Ống PPR chịu được nhiệt độ bao nhiêu? x</h2>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertLessThan(strpos($body, __('ai.faq.heading')), strpos($body, 'Ống PPR chịu được nhiệt độ'));
        $this->assertStringContainsString('<h3>Ống PPR chịu nhiệt bao nhiêu độ?</h3>', $body);
        $this->assertSame(['content.query_gap', 'content.faq'], $this->marks[0]['tools']);
    }

    public function test_a_change_for_another_record_or_without_permission_is_refused(): void
    {
        $user = $this->user(['ai.use', 'ai.apply', 'posts.update']);
        $other = AiSuggestion::query()->create(['gateway_suggestion_id' => 99, 'task' => 'meta.suggest', 'entity_type' => 'post', 'entity_id' => 999,
            'locale' => 'vi', 'old_values' => ['title' => '', 'description' => ''], 'options' => [['title' => 'x', 'description' => 'y']], 'status' => AiSuggestion::PROPOSED, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/fix', ['changes' => [
            ['step' => 'meta', 'suggestion_id' => $other->id, 'option' => 0, 'title' => 'x', 'description' => 'y'],
        ]])->assertStatus(422)->assertJsonPath('data.applied', []);
        $this->assertSame([], $this->marks);

        // The declining task has no AI fix; and applying needs ai.apply.
        $this->actingAs($user)->postJson('/vi/admin/ai/seo-tasks/cccccccccccccccc/fix/meta')->assertStatus(422);
        $this->actingAs($this->user(['ai.use', 'posts.update']))->postJson('/vi/admin/ai/seo-tasks/aaaaaaaaaaaaaaaa/fix', ['changes' => [['step' => 'meta']]])->assertForbidden();
    }

    public function test_waiting_and_measured_results_are_shown_in_plain_words(): void
    {
        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/seo-tasks')
            ->assertOk()
            ->assertSee(__('ai.tasks.tabs.waiting'))
            ->assertSee('Ống PPR là gì')
            ->assertSee(__('ai.tasks.results.days_left', ['count' => 18]))
            ->assertSee(__('ai.tasks.did', ['list' => 'tiêu đề & mô tả']))
            ->assertSee('Ống HDPE giá bao nhiêu')
            ->assertSee(__('ai.tasks.results.verdict.better'))
            ->assertSee(__('ai.tasks.results.before_after', ['before' => 8, 'after' => 30]))
            ->assertSee('+265%')
            ->assertSee(__('ai.tasks.results.how'));
    }

    private function user(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions])->id]);
    }
}
