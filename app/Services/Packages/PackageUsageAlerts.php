<?php

namespace App\Services\Packages;

use App\Services\MailSettings;

class PackageUsageAlerts
{
    public const WARNING_PERCENT = 80;

    public function __construct(
        private readonly PackageLimits $limits,
        private readonly MailSettings $mailSettings,
    ) {}

    /** @return list<array{level: string, title: string, message: string}> */
    public function forAdmin(): array
    {
        if ($this->limits->packageMissing()) {
            return [[
                'level' => 'danger',
                'title' => __('admin.package.alert.missing_title'),
                'message' => __('admin.package.alert.missing_message'),
            ]];
        }

        $alerts = [];

        foreach ($this->limits->summary() as $metric => $summary) {
            if ($summary['limit'] === null) {
                continue;
            }

            if ($metric === PackageLimits::EMAIL_MONTHLY && $this->mailSettings->source() !== MailSettings::SOURCE_REMOTE) {
                continue;
            }

            $percent = (int) round($summary['usage'] / max(1, $summary['limit']) * 100);
            if ($percent < self::WARNING_PERCENT) {
                continue;
            }

            $replacements = [
                'metric' => __('admin.package.metric.'.$metric),
                'used' => number_format($summary['usage']),
                'limit' => number_format($summary['limit']),
                'percent' => min(100, $percent),
            ];

            $exhausted = $summary['usage'] >= $summary['limit'];
            $alerts[] = [
                'level' => $exhausted ? 'danger' : 'warning',
                'title' => __('admin.package.alert.'.($exhausted ? 'exhausted_title' : 'near_title'), $replacements),
                'message' => __('admin.package.alert.'.($exhausted ? 'exhausted_message' : 'near_message'), $replacements),
            ];
        }

        return $alerts;
    }
}
