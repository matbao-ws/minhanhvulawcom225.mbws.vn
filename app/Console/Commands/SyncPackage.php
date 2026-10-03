<?php

namespace App\Console\Commands;

use App\Services\Packages\PackageInfo;
use App\Services\Packages\PackageLimits;
use App\Services\Packages\PackageSync;
use Illuminate\Console\Command;

class SyncPackage extends Command
{
    protected $signature = 'package:sync';

    protected $description = 'Read the sold package from the internal tool and apply its features and limits';

    public function handle(PackageInfo $info, PackageSync $sync, PackageLimits $limits): int
    {
        if (! $info->configured()) {
            $this->components->warn('PACKAGE_INFO_DOMAIN is not set; features stay under manual control.');

            return self::SUCCESS;
        }

        $result = $sync->sync();

        if ($result['status'] === PackageInfo::NOT_FOUND) {
            $this->components->error('The internal tool does not know '.config('packages.domain').'. Nothing was changed.');

            return self::FAILURE;
        }

        if ($result['status'] === PackageInfo::UNREACHABLE) {
            $this->components->error('The internal tool could not be reached. The last synced package stays in effect.');

            return self::FAILURE;
        }

        $package = $result['package'];
        $this->components->twoColumnDetail('Package', $package['code'].' — '.$package['name']);

        foreach ($limits->summary() as $metric => $row) {
            $this->components->twoColumnDetail(
                'Limit '.$metric,
                ($row['limit'] === null ? 'unlimited' : number_format($row['limit'])).' (used '.number_format($row['usage']).')',
            );
        }

        if (! $package['plan_known']) {
            $this->components->warn('Package '.$package['code'].' has no entry in config/packages.php; features were left unchanged.');
        }

        foreach ($result['changes'] as $code => $enabled) {
            $this->components->twoColumnDetail('Feature '.$code, $enabled ? 'enabled' : 'disabled');
        }

        return self::SUCCESS;
    }
}
