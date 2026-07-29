<?php

namespace App\Support;

use App\Models\FeatureSetting;
use App\Models\User;

class FeatureGate
{
    public const SUPPORT_MESSAGE = 'Tính năng này chưa được bật. Vui lòng liên hệ bộ phận hỗ trợ để được kích hoạt hoặc cấu hình.';

    public function enabled(string $featureCode): bool
    {
        $setting = FeatureSetting::query()
            ->where('feature_code', $featureCode)
            ->first();

        return $setting && (bool) $setting->is_enabled;
    }

    public function availableTo(?User $user, string $featureCode): bool
    {
        return (bool) ($user?->isSuperAdmin() || $this->enabled($featureCode));
    }

    public function limit(string $featureCode): ?int
    {
        $setting = FeatureSetting::query()
            ->where('feature_code', $featureCode)
            ->first();

        if (! $setting || $setting->limit_value === null) {
            return null;
        }

        return (int) $setting->limit_value;
    }

    public function require(string $featureCode): void
    {
        if (! $this->enabled($featureCode)) {
            abort(403, self::SUPPORT_MESSAGE);
        }
    }

    public function unavailableMessage(): string
    {
        return self::SUPPORT_MESSAGE;
    }
}
