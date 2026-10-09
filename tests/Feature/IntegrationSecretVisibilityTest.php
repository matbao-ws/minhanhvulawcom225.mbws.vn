<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\PaymentMethod;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\ShippingPartner;
use App\Models\User;
use App\Support\SecretMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings pages are open to read-only staff, but a webhook token forges
 * payments and deliveries. Only staff who may change a secret see it.
 */
class IntegrationSecretVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const SEPAY_TOKEN = 'sepay-webhook-token-0123456789';

    private const GHN_TOKEN = 'ghn-webhook-token-0123456789';

    private const SMTP_PASSWORD = 'smtp-password-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['online_payment', 'shipping', 'cod_order'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        PaymentMethod::query()->updateOrCreate(['method_code' => 'sepay'], [
            'name' => 'SePay',
            'type' => 'connected',
            'status' => 'active',
            'settings' => ['bank_code' => 'MBBank', 'account_number' => '0123456789', 'account_holder' => 'A', 'webhook_token' => self::SEPAY_TOKEN, 'webhook_secret' => '', 'api_key' => ''],
        ]);
        ShippingPartner::query()->where('partner_code', 'DTGH000013')->firstOrFail()
            ->update(['settings' => ['api_token' => 'ghn-api-token-0123456789', 'shop_id' => '1', 'webhook_token' => self::GHN_TOKEN]]);
        ProjectSetting::query()->updateOrCreate(['setting_key' => 'notification_settings'], [
            'setting_value' => ['smtp' => ['enabled' => true, 'password' => self::SMTP_PASSWORD]],
        ]);
    }

    public function test_view_only_staff_see_placeholders_instead_of_secrets(): void
    {
        $this->actingAs($this->staff(['payments.view', 'shipping.view', 'settings.view']));

        $this->get($this->sepayUrl())->assertOk()->assertDontSee(self::SEPAY_TOKEN)->assertSee(SecretMask::PLACEHOLDER);
        $this->get($this->ghnUrl())->assertOk()->assertDontSee(self::GHN_TOKEN)->assertDontSee('ghn-api-token-0123456789');
        $this->get('/vi/admin/notification-settings')->assertOk()->assertDontSee(self::SMTP_PASSWORD);
    }

    public function test_staff_who_may_change_a_secret_still_see_it(): void
    {
        $this->actingAs($this->staff(['payments.view', 'payments.update', 'shipping.view', 'shipping.update', 'settings.view', 'settings.update']));

        $this->get($this->sepayUrl())->assertOk()->assertSee(self::SEPAY_TOKEN);
        $this->get($this->ghnUrl())->assertOk()->assertSee(self::GHN_TOKEN);
        $this->get('/vi/admin/notification-settings')->assertOk()->assertSee(self::SMTP_PASSWORD);
    }

    public function test_a_self_chosen_webhook_token_must_be_long_enough(): void
    {
        $this->actingAs($this->staff(['shipping.view', 'shipping.update']));
        $ghn = ShippingPartner::query()->where('partner_code', 'DTGH000013')->firstOrFail();

        $this->post($this->ghnUrl(), [
            'api_token' => 'ghn-api-token-0123456789',
            'shop_id' => '1',
            'webhook_token' => 'short',
        ])->assertSessionHasErrors('webhook_token');

        $this->assertSame(self::GHN_TOKEN, data_get($ghn->fresh()->settings, 'webhook_token'));
    }

    private function sepayUrl(): string
    {
        return '/vi/admin/payment-methods/'.PaymentMethod::query()->where('method_code', 'sepay')->value('id').'/settings';
    }

    private function ghnUrl(): string
    {
        return '/vi/admin/shipping-partners/'.ShippingPartner::query()->where('partner_code', 'DTGH000013')->value('id').'/settings';
    }

    private function staff(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Staff '.uniqid(), 'permissions' => $permissions])->id]);
    }
}
