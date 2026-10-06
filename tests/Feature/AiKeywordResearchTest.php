<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "Keywords & competitors": the gateway holds the data and the paid provider; the website shows and asks. */
class AiKeywordResearchTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Request> */
    private array $sent = [];

    private array $overview;

    private ?array $topicInput = null;

    /** A reason the gateway answers every call with (Http::fake keeps the first stub). */
    private ?string $refuse = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);
        foreach (['ai_seo', 'ai_research', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        $this->overview = [
            'domain' => 'spico.vn',
            'limits' => ['competitors' => 3, 'tracked_keywords' => 20, 'serp_depth' => 30],
            'competitors' => [
                ['domain' => 'rival.vn', 'status' => 'ok', 'error' => null, 'refreshed_at' => '2026-10-19T09:00:00+00:00', 'keywords' => 3],
            ],
            'gaps' => [
                ['keyword' => 'ống nhựa upvc giá rẻ', 'search_volume' => 1900, 'difficulty' => 35, 'cpc' => 0.4, 'intent' => 'transactional', 'tracked' => true,
                    'competitors' => [['domain' => 'rival.vn', 'rank' => 2, 'url' => 'https://rival.vn/a']]],
                ['keyword' => 'cách chọn ống nước', 'search_volume' => 880, 'difficulty' => 12, 'cpc' => null, 'intent' => 'informational', 'tracked' => false,
                    'competitors' => [['domain' => 'rival.vn', 'rank' => 5, 'url' => 'https://rival.vn/b']]],
                ['keyword' => 'ống upvc là gì', 'search_volume' => 320, 'difficulty' => 70, 'cpc' => null, 'intent' => null, 'tracked' => false,
                    'competitors' => [['domain' => 'rival.vn', 'rank' => 8, 'url' => null]]],
            ],
            'tracked' => [
                ['id' => 7, 'keyword' => 'ống nhựa upvc giá rẻ', 'position' => 4, 'previous_position' => 9, 'url' => 'https://spico.vn/p/ong-upvc',
                    'checked_at' => '2026-10-19T05:00:00+00:00', 'error' => null, 'history' => [['date' => '2026-10-19', 'position' => 4], ['date' => '2026-10-12', 'position' => 9]]],
                ['id' => 8, 'keyword' => 'ống nước', 'position' => null, 'previous_position' => null, 'url' => null,
                    'checked_at' => '2026-10-19T05:00:00+00:00', 'error' => null, 'history' => [['date' => '2026-10-19', 'position' => null]]],
            ],
        ];

        Http::fake(function (Request $request) {
            $this->sent[] = $request;
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $this->refuse !== null => Http::response(['ok' => false, 'reason' => $this->refuse], 403),
                // The layout reads the mail relay's usage on every admin page.
                $path === '/v1/mail/usage' => Http::response(['ok' => true, 'usage' => ['used' => 0, 'limit' => 200]]),
                $path === '/v1/research' => Http::response(['ok' => true, 'research' => $this->overview]),
                $path === '/v1/research/competitors' && $request['domain'] === 'spico.vn' => Http::response(['ok' => false, 'reason' => 'invalid_input'], 422),
                $path === '/v1/research/competitors' => Http::response(['ok' => true, 'competitor' => ['domain' => 'other.vn', 'status' => 'queued']], 201),
                $path === '/v1/research/competitors/suggest' => Http::response(['ok' => true, 'competitors' => [['domain' => 'nhuabinhminh.vn', 'common_keywords' => 412, 'avg_position' => 7.1, 'traffic' => 9000]]]),
                $path === '/v1/research/keywords' => Http::response(['ok' => true, 'added' => count($request['keywords']), 'existing' => 0], 201),
                $path === '/v1/tasks/research.topics' => $this->topics($request),
                default => Http::response(['ok' => true]),
            };
        });
    }

    public function test_the_page_shows_competitors_gaps_and_rankings(): void
    {
        $this->actingAs($this->user(['ai.use', 'posts.create']))->get('/vi/admin/ai/research')
            ->assertOk()
            ->assertSee('rival.vn')
            ->assertSee('cách chọn ống nước')
            ->assertSee(__('ai.research.intents.informational'))
            // Tracked from the gap list already; the others can be.
            ->assertSee(__('ai.research.tracked'))
            ->assertSee(__('ai.research.up', ['count' => 5]))
            ->assertSee(__('ai.research.not_ranked', ['depth' => 30]))
            ->assertSee(route('admin.ai.research.topics'), false);
    }

    public function test_competitors_and_keywords_are_sent_to_the_gateway(): void
    {
        $user = $this->user(['ai.use']);

        $this->actingAs($user)->from('/vi/admin/ai/research')->post('/vi/admin/ai/research/competitors', ['domain' => 'other.vn'])
            ->assertRedirect('/vi/admin/ai/research')->assertSessionHas('success', __('ai.research.competitor_added'));

        // The gateway's reason becomes a sentence; the code never reaches the page.
        $this->actingAs($user)->from('/vi/admin/ai/research')->post('/vi/admin/ai/research/competitors', ['domain' => 'spico.vn'])
            ->assertSessionHas('error', __('ai.research.errors.invalid_input'));

        $this->actingAs($user)->from('/vi/admin/ai/research')->post('/vi/admin/ai/research/keywords', ['keywords' => "ống nhựa\n\n ống nước \n"])
            ->assertSessionHas('success', __('ai.research.keywords_added', ['count' => 2]));
        $keywords = collect($this->sent)->first(fn (Request $request) => str_ends_with($request->url(), '/v1/research/keywords'));
        $this->assertSame(['ống nhựa', 'ống nước'], $keywords['keywords']);

        $this->actingAs($user)->from('/vi/admin/ai/research')->delete('/vi/admin/ai/research/keywords/7')->assertSessionHas('success');
        $this->actingAs($user)->from('/vi/admin/ai/research')->delete('/vi/admin/ai/research/competitors/rival.vn')->assertSessionHas('success');
        $deletes = collect($this->sent)->filter(fn (Request $request) => $request->method() === 'DELETE')->map->url()->values()->all();
        $this->assertSame(['https://gw.test/v1/research/keywords/7', 'https://gw.test/v1/research/competitors/rival.vn'], $deletes);
    }

    public function test_competitor_suggestions_can_be_added_from_the_page(): void
    {
        $user = $this->user(['ai.use']);

        $this->actingAs($user)->from('/vi/admin/ai/research')->post('/vi/admin/ai/research/competitors/suggest')->assertRedirect('/vi/admin/ai/research');
        $this->actingAs($user)->get('/vi/admin/ai/research')->assertOk()
            ->assertSee('nhuabinhminh.vn')
            ->assertSee(__('ai.research.suggestion_line', ['count' => '412']));
    }

    public function test_topics_come_from_the_gateways_keywords_and_link_to_the_draft_screen(): void
    {
        ProjectSetting::query()->create(['setting_key' => 'ai_business_info', 'setting_value' => 'Cửa hàng ống nhựa ở Hà Nội']);
        $user = $this->user(['ai.use', 'posts.create']);

        $this->actingAs($user)->from('/vi/admin/ai/research')->post('/vi/admin/ai/research/topics')->assertRedirect('/vi/admin/ai/research');

        // Keywords from the gateway's list, never from the browser.
        $this->assertSame(['ống nhựa upvc giá rẻ', 'cách chọn ống nước', 'ống upvc là gì'], array_column($this->topicInput['keywords'], 'keyword'));
        $this->assertSame('Cửa hàng ống nhựa ở Hà Nội', $this->topicInput['business_info']);

        $draftUrl = route('admin.ai.post-drafts.create', ['keyword' => 'cách chọn ống nước']);
        $this->actingAs($user)->get('/vi/admin/ai/research')->assertOk()
            ->assertSee('Cách chọn ống nước cho nhà phố')
            ->assertSee($draftUrl, false);

        // The draft screen starts with the topic's keyword.
        $this->actingAs($user)->get($draftUrl)->assertOk()->assertSee('value="cách chọn ống nước"', false);
    }

    public function test_it_needs_the_feature_and_the_permission(): void
    {
        $this->actingAs($this->user(['posts.view']))->get('/vi/admin/ai/research')->assertForbidden();

        FeatureSetting::query()->where('feature_code', 'ai_research')->update(['is_enabled' => false]);
        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/research')->assertRedirect();
        $this->actingAs($this->user(['ai.use']))->postJson('/vi/admin/ai/research/topics')->assertForbidden();
        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/seo-tasks')->assertOk()->assertDontSee(route('admin.ai.research.index'), false);
        $this->assertSame([], collect($this->sent)->filter(fn (Request $request) => str_contains($request->url(), '/v1/research'))->all());
    }

    public function test_a_package_without_research_gets_a_plain_sentence(): void
    {
        $this->refuse = 'not_enabled';

        $this->actingAs($this->user(['ai.use']))->get('/vi/admin/ai/research')->assertOk()
            ->assertSee(__('ai.research.errors.not_enabled'))
            ->assertDontSee('not_enabled');

        // A problem with Mắt Bão's data account: a sentence, never the generic "AI busy".
        $this->refuse = 'bad_key';
        $this->actingAs($this->user(['ai.use']))->from('/vi/admin/ai/research')->post('/vi/admin/ai/research/competitors/suggest')
            ->assertSessionHas('error', __('ai.research.errors.bad_key'));
    }

    private function topics(Request $request)
    {
        $this->topicInput = $request->data();

        return Http::response(['ok' => true, 'suggestion_id' => 3, 'result' => ['topics' => [
            ['title' => 'Cách chọn ống nước cho nhà phố', 'primary_keyword' => 'cách chọn ống nước', 'keywords' => ['cách chọn ống nước', 'ống upvc là gì'], 'reason' => '880 lượt tìm mỗi tháng.'],
        ]], 'usage' => ['credits_charged' => 2, 'credits_used' => 2, 'credits_limit' => 100]]);
    }

    private function user(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions])->id]);
    }
}
