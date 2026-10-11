<?php

namespace Tests\Feature;

use App\Exceptions\AnalyticsUnavailable;
use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\AiGateway\GatewayClient;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GA4 goes through the MatBaoAI-SEO gateway: this website holds a project token
 * and never a Google key. The gateway is faked here; its own repository tests
 * the Google side.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY = 'https://gw.test';

    private const TOKEN = 'mbp_test_project_token';

    /** What the fake gateway knows about this project. */
    private ?string $gatewayProperty = null;

    /** Override for the next report/check answers: [status, body]. */
    private ?array $gatewayFailure = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_gateway.url' => self::GATEWAY, 'ai_gateway.token' => self::TOKEN]);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'analytics'], ['is_enabled' => true]);

        Http::fake([
            'gw.test/v1/project/connections' => function (Request $request) {
                $this->gatewayProperty = $request['ga4_property_id'];

                return Http::response(['ok' => true, 'connections' => ['ga4_property_id' => $this->gatewayProperty]]);
            },
            'gw.test/v1/project' => Http::response(['ok' => true, 'project' => [
                'tool_domain' => 'shop.mbws.vn',
                'reader_email' => 'core-reader@matbaows-ga.iam.gserviceaccount.com',
            ]]),
            'gw.test/v1/analytics/check' => fn () => $this->gatewayAnswer(['ok' => true, 'property_name' => 'Shop Demo - GA4']),
            'gw.test/v1/analytics/*' => fn (Request $request) => $this->gatewayAnswer(['ok' => true, 'report' => $this->reportFor($request)]),
        ]);
    }

    public function test_report_comes_from_the_gateway_with_the_project_token_and_adds_orders(): void
    {
        $this->configure();
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Ho_Chi_Minh'));
        foreach ([['ORD-1', 'pending'], ['ORD-2', 'completed'], ['ORD-3', 'cancelled']] as [$number, $status]) {
            Order::query()->create($this->order($number, $status))->forceFill(['created_at' => now()->subDay()])->save();
        }

        $report = $this->fetch('last_7');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::GATEWAY.'/v1/analytics/report?')
            && $request['from'] === '2026-10-08' && $request['to'] === '2026-10-14'
            && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN));

        $this->assertSame(200, $report['totals']['sessions']);
        $this->assertSame('last_7', $report['range']['preset']);
        // Two non-cancelled orders over 200 sessions.
        $this->assertSame(2, $report['orders']);
        $this->assertSame(1.0, $report['conversion_rate']);
    }

    public function test_the_website_never_talks_to_google_or_reads_a_key(): void
    {
        $this->configure();

        $this->fetch();
        app(AnalyticsReport::class)->checkConnection();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis.com'));
        $this->assertFileDoesNotExist(app_path('Services/Analytics/GoogleServiceAccount.php'));
        $this->assertFileDoesNotExist(app_path('Services/Analytics/Ga4Client.php'));
    }

    public function test_saving_settings_hands_the_property_to_the_gateway(): void
    {
        $this->actingAs($this->admin())->postJson('/vi/admin/settings', $this->settingsPayload(['measurement_id' => 'g-ab12cd34', 'property_id' => '345678901']))
            ->assertOk();

        $this->assertSame(['measurement_id' => 'G-AB12CD34', 'property_id' => '345678901', 'gsc_site_url' => null], app(AnalyticsReport::class)->settings());
        $this->assertSame('345678901', $this->gatewayProperty);
    }

    public function test_settings_still_save_when_the_gateway_is_down(): void
    {
        Http::fake(['gw.test/*' => Http::response('down', 502)]);

        $this->actingAs($this->admin())->postJson('/vi/admin/settings', $this->settingsPayload(['measurement_id' => 'G-AB12CD34', 'property_id' => '345678901']))
            ->assertOk();

        $this->assertSame('345678901', app(AnalyticsReport::class)->settings()['property_id']);
    }

    public function test_a_property_the_gateway_does_not_know_yet_is_sent_and_retried_once(): void
    {
        // Saved locally while the gateway was down: the gateway has no property.
        app(AnalyticsReport::class)->saveSettings(['measurement_id' => 'G-TEST123', 'property_id' => '345678901']);
        $this->gatewayProperty = null;

        $report = $this->fetch();

        $this->assertSame(200, $report['totals']['sessions']);
        $this->assertSame('345678901', $this->gatewayProperty);
    }

    public function test_refresh_asks_the_gateway_to_bypass_its_cache(): void
    {
        $this->configure();

        $this->actingAs($this->admin())->postJson('/vi/admin/dashboard/analytics/refresh?range=last_7')->assertOk()->assertJsonPath('success', true);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_starts_with($request->url(), self::GATEWAY.'/v1/analytics/refresh?'));
    }

    public function test_gateway_refusals_become_plain_reasons(): void
    {
        $this->configure();
        $admin = $this->admin();

        foreach ([
            [409, 'no_access', AnalyticsUnavailable::NO_ACCESS],
            [503, 'bad_key', AnalyticsUnavailable::BAD_KEY],
            [503, 'provider_unavailable', AnalyticsUnavailable::GOOGLE_ERROR],
            [401, 'unauthorized', AnalyticsUnavailable::NO_KEY],
            [403, 'project_suspended', AnalyticsUnavailable::NO_KEY],
        ] as [$status, $gatewayReason, $reason]) {
            $this->gatewayFailure = [$status, ['ok' => false, 'reason' => $gatewayReason]];

            $this->actingAs($admin)->getJson('/vi/admin/dashboard/analytics?range=last_7')
                ->assertOk()
                ->assertJson(['success' => false, 'reason' => $reason, 'message' => __('admin.analytics.error.'.$reason)]);
        }

        // A gateway that answers with something that is not its JSON.
        $this->gatewayFailure = [502, '<html>Bad gateway</html>'];
        $this->actingAs($admin)->getJson('/vi/admin/dashboard/analytics?range=last_7')
            ->assertOk()
            ->assertJson(['success' => false, 'reason' => AnalyticsUnavailable::GOOGLE_ERROR])
            ->assertDontSee('Bad gateway');
    }

    public function test_without_a_gateway_token_the_dashboard_says_so(): void
    {
        $this->configure();
        config(['ai_gateway.token' => null]);

        $this->actingAs($this->admin())->getJson('/vi/admin/dashboard/analytics')
            ->assertOk()
            ->assertJson(['success' => false, 'reason' => AnalyticsUnavailable::NO_KEY]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/analytics/'));
    }

    public function test_without_a_property_nothing_is_sent(): void
    {
        $this->actingAs($this->admin())->getJson('/vi/admin/dashboard/analytics')
            ->assertOk()
            ->assertJson(['success' => false, 'reason' => AnalyticsUnavailable::NOT_CONFIGURED]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/analytics/'));
    }

    public function test_statistics_need_the_permission_and_the_feature(): void
    {
        $this->configure();
        $noAnalytics = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Sales', 'permissions' => ['orders.view']])->id]);

        $this->actingAs($noAnalytics)->getJson('/vi/admin/dashboard/analytics')->assertForbidden();
        $this->actingAs($noAnalytics)->get('/vi/admin')->assertOk()->assertDontSee('data-analytics-url', false);

        FeatureSetting::query()->where('feature_code', 'analytics')->update(['is_enabled' => false]);
        $this->actingAs($this->admin())->getJson('/vi/admin/dashboard/analytics')->assertForbidden();
    }

    public function test_dashboard_shows_the_section_to_permitted_admins(): void
    {
        $this->actingAs($this->admin())->get('/vi/admin')
            ->assertOk()
            ->assertSee(route('admin.dashboard.analytics'), false);
    }

    public function test_settings_page_shows_the_reader_email_from_the_gateway_and_check_names_the_property(): void
    {
        $this->configure();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/vi/admin/settings', $this->settingsPayload(['measurement_id' => 'UA-123', 'property_id' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['google_analytics.measurement_id', 'google_analytics.property_id']);

        $this->actingAs($admin)->get('/vi/admin/settings')
            ->assertOk()
            ->assertSee('core-reader@matbaows-ga.iam.gserviceaccount.com')
            ->assertDontSee(self::TOKEN);

        $this->actingAs($admin)->postJson('/vi/admin/settings/analytics/check')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => __('admin.analytics.connected', ['name' => 'Shop Demo - GA4'])]);
    }

    public function test_storefront_tracks_visitors_but_not_staff_previews_or_disabled_shops(): void
    {
        $this->configure();
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        Page::query()->create([
            'title' => ['vi' => 'Giới thiệu'], 'slug' => 'gioi-thieu', 'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);
        $tag = 'googletagmanager.com/gtag/js?id=G-TEST123';

        $this->get('/vi/chinh-sach/gioi-thieu')->assertOk()->assertSee($tag, false);
        $this->get('/vi/chinh-sach/gioi-thieu?client_chrome=off')->assertOk()->assertDontSee($tag, false);
        $this->actingAs($this->admin())->get('/vi/chinh-sach/gioi-thieu')->assertOk()->assertDontSee($tag, false);

        auth()->logout();
        FeatureSetting::query()->where('feature_code', 'analytics')->update(['is_enabled' => false]);
        $this->get('/vi/chinh-sach/gioi-thieu')->assertOk()->assertDontSee($tag, false);
    }

    public function test_check_command_never_prints_the_token(): void
    {
        $this->configure();

        $this->artisan('analytics:check')
            ->expectsOutputToContain(self::GATEWAY)
            ->expectsOutputToContain('Shop Demo - GA4')
            ->doesntExpectOutputToContain(self::TOKEN)
            ->assertSuccessful();
    }

    public function test_presets_resolve_like_google_analytics_in_the_shop_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Ho_Chi_Minh'));
        $report = app(AnalyticsReport::class);
        $span = fn (string $preset) => array_map(fn ($day) => $day->toDateString(), array_slice($report->range($preset), 0, 2));

        $this->assertSame(['2026-10-15', '2026-10-15'], $span('today'));
        $this->assertSame(['2026-10-14', '2026-10-14'], $span('yesterday'));
        // Like GA, "last N days" ends yesterday.
        $this->assertSame(['2026-10-08', '2026-10-14'], $span('last_7'));
        $this->assertSame(['2026-09-17', '2026-10-14'], $span('last_28'));
        $this->assertSame(['2026-09-15', '2026-10-14'], $span('last_30'));
        $this->assertSame(['2026-07-17', '2026-10-14'], $span('last_90'));
        $this->assertSame(['2026-10-01', '2026-10-15'], $span('this_month'));
        $this->assertSame(['2026-09-01', '2026-09-30'], $span('last_month'));
        $this->assertSame(['2026-01-01', '2026-10-15'], $span('this_year'));
        $this->assertSame('last_7', $report->range('nonsense')[2], 'Mốc lạ rơi về mặc định');
    }

    public function test_today_follows_the_shop_timezone_not_utc(): void
    {
        // 20:00 UTC on the 14th is already 03:00 on the 15th in Vietnam.
        $this->travelTo(Carbon::parse('2026-10-14 20:00:00', 'UTC'));

        [$from, $to] = app(AnalyticsReport::class)->range('today');

        $this->assertSame('2026-10-15', $from->toDateString());
        $this->assertSame('2026-10-15', $to->toDateString());
    }

    public function test_custom_and_too_long_ranges_are_validated_before_the_gateway(): void
    {
        $this->configure();
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Ho_Chi_Minh'));
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/vi/admin/dashboard/analytics?from=2026-09-01&to=2026-09-10')
            ->assertOk()
            ->assertJsonPath('report.range.preset', 'custom');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/analytics/report?') && $request['from'] === '2026-09-01' && $request['to'] === '2026-09-10');

        $this->actingAs($admin)->getJson('/vi/admin/dashboard/analytics?from=2026-09-10&to=2026-09-01')->assertStatus(422)->assertJson(['reason' => 'invalid_range']);
        $this->actingAs($admin)->getJson('/vi/admin/dashboard/analytics?from=2024-01-01&to=2026-10-15')->assertStatus(422);
        $this->actingAs($admin)->getJson('/vi/admin/dashboard/analytics?range=forever')->assertStatus(422);
    }

    public function test_conversion_counts_orders_by_the_shop_day(): void
    {
        $this->configure();
        $this->travelTo(Carbon::parse('2026-10-16 09:00:00', 'Asia/Ho_Chi_Minh'));

        // 18:00 UTC on the 14th is 01:00 on the 15th in Vietnam.
        Order::query()->create($this->order('ORD-TZ'))->forceFill(['created_at' => Carbon::parse('2026-10-14 18:00:00', 'UTC')])->save();
        $service = app(AnalyticsReport::class);

        [$from, $to, $preset] = $service->range(null, '2026-10-15', '2026-10-15');
        $this->assertSame(1, $service->report($from, $to, $preset)['orders']);

        [$from, $to, $preset] = $service->range(null, '2026-10-14', '2026-10-14');
        $this->assertSame(0, $service->report($from, $to, $preset)['orders']);
    }

    public function test_dashboard_offers_every_preset_and_a_custom_range(): void
    {
        $html = $this->actingAs($this->admin())->get('/vi/admin')->assertOk()->getContent();

        foreach (AnalyticsReport::PRESETS as $preset) {
            $this->assertStringContainsString('data-analytics-preset="'.$preset.'"', $html);
        }
        $this->assertStringContainsString('data-analytics-from', $html);
        $this->assertStringContainsString('data-analytics-apply', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $preset = 'last_7'): array
    {
        $service = app(AnalyticsReport::class);
        [$from, $to, $key] = $service->range($preset);

        return $service->report($from, $to, $key);
    }

    private function configure(): void
    {
        app(AnalyticsReport::class)->saveSettings(['measurement_id' => 'G-TEST123', 'property_id' => '345678901']);
        GatewayClient::forgetProject();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin '.uniqid(), 'permissions' => ['*']])->id]);
    }

    /**
     * @param  array<string, mixed>  $ok
     */
    private function gatewayAnswer(array $ok)
    {
        if ($this->gatewayFailure !== null) {
            [$status, $body] = $this->gatewayFailure;

            return Http::response($body, $status);
        }

        if ($this->gatewayProperty === null) {
            return Http::response(['ok' => false, 'reason' => 'not_connected'], 409);
        }

        return Http::response($ok);
    }

    /**
     * What the gateway sends back: GA4 figures only, no orders.
     *
     * @return array<string, mixed>
     */
    private function reportFor(Request $request): array
    {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $from = $query['from'] ?? '2026-10-08';
        $to = $query['to'] ?? '2026-10-14';

        return [
            'range' => ['days' => Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1, 'from' => $from, 'to' => $to, 'granularity' => 'day'],
            'totals' => ['users' => 120, 'sessions' => 200, 'pageviews' => 540, 'avg_session_seconds' => 95],
            'daily' => ['dates' => [], 'users' => [], 'sessions' => [], 'pageviews' => []],
            'top_pages' => [['path' => '/san-pham/ao-thun', 'title' => 'Áo thun', 'views' => 300]],
            'channels' => [['name' => 'Organic Search', 'sessions' => 150]],
            'realtime' => 4,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function order(string $number, string $status = 'pending'): array
    {
        return [
            'order_number' => $number,
            'customer_name' => 'Khách',
            'customer_email' => strtolower($number).'@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'HCM',
            'status' => $status,
            'subtotal' => 100000,
            'grand_total' => 100000,
            'payment_method' => 'cod',
        ];
    }

    /**
     * @param  array<string, string>  $ga
     * @return array<string, mixed>
     */
    private function settingsPayload(array $ga): array
    {
        return ['shop_name' => 'Shop', 'google_analytics' => $ga];
    }
}
