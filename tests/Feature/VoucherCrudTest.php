<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->create([
            'feature_code' => 'voucher',
            'is_enabled' => true,
        ]);
    }

    public function test_voucher_calculations_work_properly(): void
    {
        $percentageVoucher = Voucher::create([
            'code' => 'TEST10',
            'name' => ['vi' => 'Giảm 10%'],
            'type' => 'percentage',
            'value' => 10.00,
            'min_order_amount' => 100000.00,
            'max_discount_amount' => 30000.00,
            'quantity' => 5,
            'used_count' => 0,
            'is_active' => true,
        ]);

        // Below minimum order amount
        $this->assertFalse($percentageVoucher->isValidForOrder(50000.00));
        $this->assertEquals(0.00, $percentageVoucher->calculateDiscount(50000.00));

        // Valid, discount is 10% of 200k = 20k
        $this->assertTrue($percentageVoucher->isValidForOrder(200000.00));
        $this->assertEquals(20000.00, $percentageVoucher->calculateDiscount(200000.00));

        // Valid, discount is 10% of 400k = 40k, capped at max_discount_amount = 30k
        $this->assertTrue($percentageVoucher->isValidForOrder(400000.00));
        $this->assertEquals(30000.00, $percentageVoucher->calculateDiscount(400000.00));

        // Inactive voucher
        $percentageVoucher->update(['is_active' => false]);
        $this->assertFalse($percentageVoucher->isValidForOrder(200000.00));
        $this->assertEquals(0.00, $percentageVoucher->calculateDiscount(200000.00));
    }

    public function test_admin_can_browse_vouchers(): void
    {
        $this->actingAs(User::factory()->create());

        Voucher::create([
            'code' => 'WINTER',
            'name' => ['vi' => 'Mùa đông'],
            'type' => 'fixed',
            'value' => 10000,
            'is_active' => true,
        ]);

        $response = $this->get('/vi/admin/vouchers');
        $response->assertOk();
        $response->assertSee('WINTER');
        $response->assertSee('Mùa đông');
    }

    public function test_admin_can_create_voucher_via_form(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post('/vi/admin/vouchers', [
            'code' => 'NEWYEAR20',
            'name' => 'Tết 2026',
            'description' => 'Giảm giá dịp tết',
            'type' => 'percentage',
            'value' => 20.00,
            'min_order_amount' => 100000,
            'max_discount_amount' => 50000,
            'quantity' => 100,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/vi/admin/vouchers');
        
        $this->assertDatabaseHas('vouchers', [
            'code' => 'NEWYEAR20',
            'type' => 'percentage',
            'value' => 20.00,
        ]);

        $voucher = Voucher::where('code', 'NEWYEAR20')->first();
        $this->assertEquals('Tết 2026', $voucher->getTranslation('name', 'vi'));
    }

    public function test_admin_can_update_voucher(): void
    {
        $this->actingAs(User::factory()->create());

        $voucher = Voucher::create([
            'code' => 'SALE50',
            'name' => ['vi' => 'Giảm nửa giá'],
            'type' => 'percentage',
            'value' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->put("/vi/admin/vouchers/{$voucher->id}", [
            'code' => 'SALE60',
            'name' => 'Giảm 60%',
            'type' => 'percentage',
            'value' => 60.00,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/vi/admin/vouchers');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'code' => 'SALE60',
            'value' => 60.00,
        ]);
    }

    public function test_admin_can_delete_voucher(): void
    {
        $this->actingAs(User::factory()->create());

        $voucher = Voucher::create([
            'code' => 'TO_DELETE',
            'name' => ['vi' => 'Xoá tôi đi'],
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
        ]);

        $response = $this->delete("/vi/admin/vouchers/{$voucher->id}");
        $response->assertRedirect('/vi/admin/vouchers');

        $this->assertDatabaseMissing('vouchers', [
            'id' => $voucher->id,
        ]);
    }
}
