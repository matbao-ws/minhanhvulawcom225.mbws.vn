<?php

namespace App\Support;

use App\Models\FeatureSetting;

class FeatureGate
{
    public function enabled(string $featureCode): bool
    {
        $setting = FeatureSetting::query()
            ->where('feature_code', $featureCode)
            ->first();

        return $setting && (bool) $setting->is_enabled;
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
            abort(403, 'Tinh nang nay khong kha dung trong goi hien tai.');
        }
    }
}
