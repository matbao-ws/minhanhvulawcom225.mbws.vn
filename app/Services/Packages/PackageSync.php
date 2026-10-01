<?php

namespace App\Services\Packages;

use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns the sold package into `feature_settings`.
 *
 * The package only ever sets `package_enabled`, and copies it to `is_enabled`
 * for rows a superadmin has not overridden. FeatureGate keeps reading
 * `is_enabled`, so no gate anywhere knows packages exist.
 *
 * A failed lookup never changes anything: the last successful snapshot stays in
 * `project_settings`, so an outage of the tool cannot switch a shop's
 * features off.
 */
class PackageSync
{
    public const SETTING_KEY = 'package_info';

    private const SNAPSHOT_CACHE_KEY = 'packages.snapshot';

    private const CHECKED_CACHE_KEY = 'packages.checked';

    public function __construct(private readonly PackageInfo $info) {}

    /**
     * The last package successfully read from the tool, or null in manual mode.
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(): ?array
    {
        return Cache::remember(self::SNAPSHOT_CACHE_KEY, 300, function (): ?array {
            $value = ProjectSetting::query()->where('setting_key', self::SETTING_KEY)->first()?->setting_value;

            return is_array($value) && filled($value['code'] ?? null) ? $value : null;
        });
    }

    /**
     * @return array{status: string, package: array<string, mixed>|null, changes: array<string, bool>}
     */
    public function sync(): array
    {
        $result = $this->info->fetch();
        $this->markChecked($result['status'] === PackageInfo::OK);

        if ($result['status'] !== PackageInfo::OK) {
            return ['status' => $result['status'], 'package' => $this->snapshot(), 'changes' => []];
        }

        $package = $result['package'];
        $features = $this->featuresFor($package['code']);
        $package['plan_known'] = $features !== null;
        $package['synced_at'] = now()->toIso8601String();

        if ($features === null) {
            // Limits still apply; the feature set is left as it was so an unknown
            // code on the tool does not blank the shop.
            Log::warning('Package code from the internal tool has no feature map.', ['code' => $package['code']]);
        }

        $changes = DB::transaction(function () use ($package, $features): array {
            ProjectSetting::query()->updateOrCreate(
                ['setting_key' => self::SETTING_KEY],
                ['setting_value' => $package],
            );

            return $features === null ? [] : $this->applyFeatures($features);
        });

        $this->flush();

        return ['status' => PackageInfo::OK, 'package' => $package, 'changes' => $changes];
    }

    /**
     * Sync when the last check is older than the configured TTL. Cheap to call on
     * every admin request: it is a single cache read until the TTL runs out.
     */
    public function syncIfStale(): void
    {
        if (! $this->info->configured() || Cache::has(self::CHECKED_CACHE_KEY)) {
            return;
        }

        try {
            $this->sync();
        } catch (\Throwable $e) {
            // Never break the admin because of the tool; retry later.
            $this->markChecked(false);
            Log::warning('Package sync failed.', ['exception' => $e::class]);
        }
    }

    /**
     * Record a superadmin decision. It becomes an override only when it differs
     * from what the package says; agreeing with the package clears the override.
     */
    public function setByAdmin(FeatureSetting $setting, bool $enabled): void
    {
        $setting->update([
            'is_enabled' => $enabled,
            'is_overridden' => $setting->package_enabled !== null && $setting->package_enabled !== $enabled,
            'updated_at' => now(),
        ]);
    }

    /**
     * Drop overrides so the rows follow the package again.
     *
     * @param  list<string>|null  $codes  null for every feature
     */
    public function resetOverrides(?array $codes = null): int
    {
        return FeatureSetting::query()
            ->when($codes !== null, fn ($query) => $query->whereIn('feature_code', $codes))
            ->whereNotNull('package_enabled')
            ->get()
            ->each(fn (FeatureSetting $setting) => $setting->update([
                'is_enabled' => $setting->package_enabled,
                'is_overridden' => false,
                'updated_at' => now(),
            ]))
            ->count();
    }

    public function flush(): void
    {
        Cache::forget(self::SNAPSHOT_CACHE_KEY);
    }

    /**
     * @return list<string>|null null when the package code is not in the map
     */
    public function featuresFor(string $packageCode): ?array
    {
        $plan = config('packages.plans.'.strtoupper($packageCode));

        if (! is_array($plan) || ! array_key_exists('features', $plan)) {
            return null;
        }

        $all = config('features.codes', []);

        return $plan['features'] === '*'
            ? $all
            : array_values(array_intersect($all, (array) $plan['features']));
    }

    /**
     * @param  list<string>  $enabledCodes
     * @return array<string, bool> codes whose effective value changed
     */
    private function applyFeatures(array $enabledCodes): array
    {
        $changes = [];

        foreach (config('features.codes', []) as $code) {
            $packageEnabled = in_array($code, $enabledCodes, true);
            $setting = FeatureSetting::query()->firstOrNew(['feature_code' => $code]);
            $before = $setting->exists ? (bool) $setting->is_enabled : null;

            $setting->package_enabled = $packageEnabled;
            if (! $setting->exists || ! $setting->is_overridden) {
                $setting->is_enabled = $packageEnabled;
                $setting->is_overridden = false;
            }
            $setting->updated_at = now();
            $setting->save();

            if ($before !== (bool) $setting->is_enabled) {
                $changes[$code] = (bool) $setting->is_enabled;
            }
        }

        return $changes;
    }

    private function markChecked(bool $succeeded): void
    {
        Cache::put(
            self::CHECKED_CACHE_KEY,
            true,
            $succeeded ? (int) config('packages.sync_ttl', 3600) : (int) config('packages.retry_ttl', 300),
        );
    }

    public static function forgetChecked(): void
    {
        Cache::forget(self::CHECKED_CACHE_KEY);
    }
}
