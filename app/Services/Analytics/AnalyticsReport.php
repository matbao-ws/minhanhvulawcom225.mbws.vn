<?php

namespace App\Services\Analytics;

use App\Exceptions\AnalyticsUnavailable;
use App\Models\Order;
use App\Models\ProjectSetting;
use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The dashboard's traffic report for this shop's GA4 property.
 *
 * GA4 is read by the MatBaoAI-SEO gateway, which holds the Google credential;
 * this website only has its project token, so a leaked .env exposes this shop's
 * figures and nobody else's. The website still owns what only it knows: the
 * presets resolved in the shop's timezone, and orders for the conversion rate.
 */
class AnalyticsReport
{
    public const SETTING_KEY = 'google_analytics';

    /**
     * The presets of Google Analytics' own date picker. Like GA, "last N days"
     * ends yesterday, so the figures match what the shop sees in GA itself.
     */
    public const PRESETS = ['today', 'yesterday', 'last_7', 'last_28', 'last_30', 'last_90', 'this_month', 'last_month', 'this_year'];

    public const DEFAULT_PRESET = 'last_7';

    /** The next report() asks the gateway to bypass its cache. */
    private bool $refreshNext = false;

    public function __construct(private readonly GatewayClient $gateway) {}

    /**
     * @return array{measurement_id: string|null, property_id: string|null}
     */
    public function settings(): array
    {
        $value = Cache::remember('analytics.settings', 300, fn () => ProjectSetting::query()
            ->where('setting_key', self::SETTING_KEY)->first()?->setting_value);

        return [
            'measurement_id' => filled($value['measurement_id'] ?? null) ? (string) $value['measurement_id'] : null,
            'property_id' => filled($value['property_id'] ?? null) ? (string) $value['property_id'] : null,
        ];
    }

    /**
     * Save locally (the Measurement ID feeds gtag on the storefront) and hand the
     * Property ID to the gateway, which is the side that reads GA4.
     *
     * @param  array{measurement_id?: string|null, property_id?: string|null}  $settings
     */
    public function saveSettings(array $settings): void
    {
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => self::SETTING_KEY],
            ['setting_value' => [
                'measurement_id' => filled($settings['measurement_id'] ?? null) ? strtoupper(trim($settings['measurement_id'])) : null,
                'property_id' => filled($settings['property_id'] ?? null) ? trim($settings['property_id']) : null,
            ]],
        );

        Cache::forget('analytics.settings');

        // Best effort: the settings form must save even when the gateway is down;
        // report() re-sends the ID the first time the gateway does not know it.
        try {
            $this->pushPropertyId();
        } catch (GatewayUnavailable $e) {
            Log::warning('Could not hand the GA4 property to the gateway.', ['reason' => $e->reason]);
        }
    }

    /**
     * Turn a preset, or a custom from/to, into concrete days in the shop's timezone.
     * A custom range reaching into the future is cut at today.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string} from, to, preset key ('custom' for from/to)
     *
     * @throws \InvalidArgumentException when the range is reversed or longer than allowed
     */
    public function range(?string $preset = null, ?string $from = null, ?string $to = null): array
    {
        $tz = (string) config('analytics.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $yesterday = $today->copy()->subDay();

        if (filled($from) && filled($to)) {
            $start = Carbon::createFromFormat('Y-m-d', $from, $tz)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $to, $tz)->startOfDay();
            $key = 'custom';
        } else {
            $key = in_array($preset, self::PRESETS, true) ? $preset : self::DEFAULT_PRESET;
            [$start, $end] = match ($key) {
                'today' => [$today, $today],
                'yesterday' => [$yesterday, $yesterday],
                'last_7' => [$today->copy()->subDays(7), $yesterday],
                'last_28' => [$today->copy()->subDays(28), $yesterday],
                'last_30' => [$today->copy()->subDays(30), $yesterday],
                'last_90' => [$today->copy()->subDays(90), $yesterday],
                'this_month' => [$today->copy()->startOfMonth(), $today],
                'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
                'this_year' => [$today->copy()->startOfYear(), $today],
            };
        }

        if ($end->gt($today)) {
            $end = $today;
        }

        if ($start->gt($end) || $start->diffInDays($end) + 1 > (int) config('analytics.max_days')) {
            throw new \InvalidArgumentException('Invalid analytics range.');
        }

        return [$start, $end, $key];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AnalyticsUnavailable
     */
    public function report(Carbon $from, Carbon $to, string $preset = 'custom'): array
    {
        $this->requirePropertyId();

        $query = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        $refresh = $this->refreshNext;
        $this->refreshNext = false;

        $report = $this->call(fn () => $refresh
            ? $this->gateway->post('v1/analytics/refresh', $query)
            : $this->gateway->get('v1/analytics/report', $query))['report'];

        // Orders live here, sessions in GA: the one figure neither side has alone.
        $orders = Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [
                $from->copy()->startOfDay()->setTimezone(config('app.timezone')),
                $to->copy()->endOfDay()->setTimezone(config('app.timezone')),
            ])
            ->count();
        $sessions = (int) ($report['totals']['sessions'] ?? 0);

        $report['range']['preset'] = $preset;
        $report['orders'] = $orders;
        $report['conversion_rate'] = $sessions > 0 ? round($orders / $sessions * 100, 2) : null;

        return $report;
    }

    /**
     * The property's name, fetched live: this is the "test connection" button.
     *
     * @throws AnalyticsUnavailable
     */
    public function checkConnection(): string
    {
        $this->requirePropertyId();

        return (string) $this->call(fn () => $this->gateway->get('v1/analytics/check'))['property_name'];
    }

    /** Make the next report bypass the gateway's cache (the refresh button). */
    public function flush(): void
    {
        $this->refreshNext = true;
    }

    /**
     * The address customers add as Viewer in GA4 / Search Console, as the gateway
     * reports it. Null when the gateway is not configured or unreachable.
     */
    public function readerEmail(): ?string
    {
        return $this->gateway->project()['reader_email'] ?? null;
    }

    /**
     * Run a gateway call; when the gateway does not know this site's property yet
     * (first use, or a save while it was down), send it and try once more.
     *
     * @param  callable(): array<string, mixed>  $call
     * @return array<string, mixed>
     *
     * @throws AnalyticsUnavailable
     */
    private function call(callable $call): array
    {
        try {
            try {
                return $call();
            } catch (GatewayUnavailable $e) {
                if ($e->reason !== 'not_connected') {
                    throw $e;
                }

                $this->pushPropertyId();

                return $call();
            }
        } catch (GatewayUnavailable $e) {
            throw new AnalyticsUnavailable(self::reasonFor($e->reason));
        }
    }

    /**
     * @throws GatewayUnavailable
     */
    private function pushPropertyId(): void
    {
        if (! $this->gateway->configured()) {
            return;
        }

        $this->gateway->put('v1/project/connections', ['ga4_property_id' => $this->settings()['property_id']]);
        GatewayClient::forgetProject();
    }

    /**
     * @throws AnalyticsUnavailable
     */
    private function requirePropertyId(): void
    {
        if ($this->settings()['property_id'] === null) {
            throw new AnalyticsUnavailable(AnalyticsUnavailable::NOT_CONFIGURED);
        }
    }

    /** Gateway reason codes → what this admin can tell the shop. */
    private static function reasonFor(string $gatewayReason): string
    {
        return match ($gatewayReason) {
            GatewayUnavailable::NOT_CONFIGURED, 'unauthorized', 'project_suspended' => AnalyticsUnavailable::NO_KEY,
            'not_connected' => AnalyticsUnavailable::NOT_CONFIGURED,
            'no_access' => AnalyticsUnavailable::NO_ACCESS,
            'bad_key' => AnalyticsUnavailable::BAD_KEY,
            default => AnalyticsUnavailable::GOOGLE_ERROR,
        };
    }
}
