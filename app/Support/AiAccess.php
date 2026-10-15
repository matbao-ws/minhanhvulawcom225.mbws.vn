<?php

namespace App\Support;

use App\Models\FeatureSetting;
use App\Models\User;
use App\Services\Packages\PackageSync;

/**
 * Whether a user may use an AI feature now, and if not why — so AI buttons, menu items and pages
 * are always shown and a locked one explains itself instead of disappearing.
 *
 * Reasons, in the order they are checked:
 * - plan: the project's package does not include the feature (feature_settings.package_enabled
 *   is false and no superadmin override); the message names the first plan that has it;
 * - off: the feature is switched off on this site (manual mode, or a superadmin turned it off);
 * - permission: the user's role lacks ai.use, or the record permission the tool writes with.
 *
 * Research (ai_research) also needs AI SEO. A superadmin is never locked out, as everywhere else;
 * `bypassed` then tells what the customer sees.
 */
final class AiAccess
{
    public const PLAN = 'plan';

    public const OFF = 'off';

    public const PERMISSION = 'permission';

    /** Where "Liên hệ nâng gói" goes, as in the package-limit dialog. */
    public const SUPPORT_URL = 'https://support.matbao.ws/submitticket.php';

    /** Features each AI feature needs before its own. */
    private const NEEDS = ['ai_research' => ['ai_seo']];

    public function __construct(
        private readonly FeatureGate $features,
        private readonly PackageSync $packages,
    ) {}

    /**
     * @return array{allowed: bool, reason: ?string, bypassed: ?string, feature: string, title: string, message: string, action_label: ?string, action_url: ?string}
     */
    public function check(?User $user, string $feature = 'ai_seo', ?string $permission = null): array
    {
        $locked = null;
        foreach ([...(self::NEEDS[$feature] ?? []), $feature] as $code) {
            if (! $this->features->enabled($code)) {
                $locked = [$this->packageLacks($code) ? self::PLAN : self::OFF, $code];
                break;
            }
        }

        if ($user?->isSuperAdmin()) {
            return $this->state(true, null, $feature) + ['bypassed' => $locked[0] ?? null];
        }
        if ($locked === null && (! $user?->can('ai.use') || ($permission !== null && ! $user->can($permission)))) {
            $locked = [self::PERMISSION, $feature];
        }

        return $locked === null
            ? $this->state(true, null, $feature) + ['bypassed' => null]
            : $this->state(false, $locked[0], $locked[1]) + ['bypassed' => null];
    }

    /**
     * The attributes that turn a button or link into a locked one: a click opens the dialog of
     * admin.shared.ai-locked-assets (why, and what to do) instead of the tool.
     *
     * @param  array{reason: ?string, title: string, message: string, action_label: ?string, action_url: ?string}  $access
     */
    public static function lockAttributes(array $access): string
    {
        return ' data-ai-locked="'.e(self::lockPayload($access)).'" aria-disabled="true"';
    }

    /**
     * What the locked dialog shows, as the JSON of data-ai-locked (for components that set attributes).
     *
     * @param  array{reason: ?string, title: string, message: string, action_label: ?string, action_url: ?string}  $access
     */
    public static function lockPayload(array $access): string
    {
        $payload = array_intersect_key($access, array_flip(['reason', 'title', 'message', 'action_label', 'action_url']));

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The plan a feature first comes with ("Hero 4"), from config/packages.php; null when none has it. */
    public function minimumPlan(string $feature): ?string
    {
        foreach (array_keys((array) config('packages.plans')) as $code) {
            if (in_array($feature, $this->packages->featuresFor((string) $code) ?? [], true)) {
                return self::planName((string) $code);
            }
        }

        return null;
    }

    public static function planName(string $code): string
    {
        return match (true) {
            strtoupper($code) === 'COBAN' => __('ai.locked.plan_basic'),
            (bool) preg_match('/^HERO(\d+)$/i', $code, $m) => 'Hero '.$m[1],
            default => $code,
        };
    }

    /** Sold without it: the package says no and nobody switched it on by hand. */
    private function packageLacks(string $feature): bool
    {
        $setting = FeatureSetting::query()->where('feature_code', $feature)->first();

        return $setting !== null && $setting->package_enabled === false && ! $setting->is_overridden;
    }

    /** @return array{allowed: bool, reason: ?string, feature: string, title: string, message: string, action_label: ?string, action_url: ?string} */
    private function state(bool $allowed, ?string $reason, string $feature): array
    {
        $name = __("ai.locked.features.$feature");
        $plan = $reason === self::PLAN ? $this->minimumPlan($feature) : null;

        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'feature' => $feature,
            'title' => $reason ? __("ai.locked.{$reason}_title") : '',
            'message' => match ($reason) {
                self::PLAN => $plan ? __('ai.locked.plan', ['feature' => $name, 'plan' => $plan]) : __('ai.locked.plan_none', ['feature' => $name]),
                self::OFF => __('ai.locked.off', ['feature' => $name]),
                self::PERMISSION => __('ai.locked.permission', ['feature' => $name]),
                default => '',
            },
            'action_label' => match ($reason) {
                self::PLAN => __('ai.locked.upgrade'),
                self::OFF => __('ai.locked.support'),
                default => null,
            },
            'action_url' => in_array($reason, [self::PLAN, self::OFF], true) ? self::SUPPORT_URL : null,
        ];
    }
}
