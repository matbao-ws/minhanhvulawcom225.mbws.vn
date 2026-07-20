<?php

namespace Tests\Feature;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherMaintenanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_vouchers_are_deactivated_and_dry_run_does_not_write(): void
    {
        $expired = Voucher::query()->create([
            'code' => 'EXPIRED',
            'name' => ['vi' => 'Đã hết hạn'],
            'type' => 'fixed',
            'value' => 10000,
            'end_date' => now()->subMinute(),
            'is_active' => true,
        ]);
        $active = Voucher::query()->create([
            'code' => 'ACTIVE',
            'name' => ['vi' => 'Còn hiệu lực'],
            'type' => 'fixed',
            'value' => 10000,
            'end_date' => now()->addMinute(),
            'is_active' => true,
        ]);

        $this->artisan('vouchers:expire --dry-run')
            ->expectsOutputToContain('1 expired voucher(s) would be deactivated.')
            ->assertExitCode(0);
        $this->assertTrue($expired->fresh()->is_active);

        $this->artisan('vouchers:expire')
            ->expectsOutputToContain('Deactivated 1 expired voucher(s).')
            ->assertExitCode(0);

        $this->assertFalse($expired->fresh()->is_active);
        $this->assertTrue($active->fresh()->is_active);
    }
}
