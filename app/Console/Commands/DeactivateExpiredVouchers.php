<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Illuminate\Console\Command;

class DeactivateExpiredVouchers extends Command
{
    protected $signature = 'vouchers:expire {--dry-run : Report expired vouchers without changing them}';

    protected $description = 'Deactivate vouchers whose end date has passed';

    public function handle(): int
    {
        $expiredVouchers = Voucher::query()
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now());

        $count = $expiredVouchers->count();

        if ($this->option('dry-run')) {
            $this->components->info("{$count} expired voucher(s) would be deactivated.");

            return self::SUCCESS;
        }

        $expiredVouchers->update(['is_active' => false]);
        $this->components->info("Deactivated {$count} expired voucher(s).");

        return self::SUCCESS;
    }
}
