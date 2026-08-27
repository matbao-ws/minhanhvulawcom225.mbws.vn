<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MailSettings::flush();

        // Stand in for what a fresh install ships in .env.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.mailgun.org',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'env-user@example.com',
            'mail.mailers.smtp.password' => 'env-password',
            'mail.from.address' => 'env-from@example.com',
            'mail.from.name' => 'Env Sender',
            'mail.seller' => 'env-seller@example.com',
        ]);
    }

    public function test_env_is_used_while_the_admin_screen_is_untouched(): void
    {
        $settings = app(MailSettings::class);

        $this->assertFalse($settings->usesAdminSettings());
        $this->assertNull($settings->overrides());

        $settings->apply();

        $this->assertSame('smtp.mailgun.org', config('mail.mailers.smtp.host'));
        $this->assertSame('env-from@example.com', config('mail.from.address'));
    }

    public function test_admin_configuration_outranks_env(): void
    {
        $this->storeSmtp();

        app(MailSettings::class)->apply();

        $this->assertSame('smtp.shop.vn', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('shop-user', config('mail.mailers.smtp.username'));
        $this->assertSame('shop-password', config('mail.mailers.smtp.password'));
        $this->assertSame('shop@shop.vn', config('mail.from.address'));
        $this->assertSame('Shop', config('mail.from.name'));
        // A MAIL_URL in .env would otherwise outrank host/port.
        $this->assertNull(config('mail.mailers.smtp.url'));
    }

    public function test_ssl_and_port_465_map_to_the_smtps_scheme(): void
    {
        // Laravel builds the transport from `scheme`; `encryption` is never read,
        // and MAIL_SCHEME=smtp from .env suppresses the port-465 fallback.
        $this->storeSmtp(['encryption' => 'ssl', 'port' => 465]);
        app(MailSettings::class)->apply();
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));

        MailSettings::flush();
        $this->storeSmtp(['encryption' => 'none', 'port' => 465]);
        app(MailSettings::class)->apply();
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));

        MailSettings::flush();
        $this->storeSmtp(['encryption' => 'tls', 'port' => 587]);
        app(MailSettings::class)->apply();
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    public function test_incomplete_admin_configuration_falls_back_to_env(): void
    {
        foreach (['host', 'port', 'username', 'password', 'from_email'] as $field) {
            MailSettings::flush();
            $this->storeSmtp([$field => $field === 'port' ? null : '']);

            $settings = app(MailSettings::class);
            $this->assertFalse($settings->usesAdminSettings(), "trống {$field} vẫn được coi là đã cấu hình");

            $settings->apply();
            $this->assertSame('smtp.mailgun.org', config('mail.mailers.smtp.host'));
        }
    }

    public function test_disabled_admin_configuration_falls_back_to_env(): void
    {
        $this->storeSmtp(['enabled' => false]);

        app(MailSettings::class)->apply();

        $this->assertSame('smtp.mailgun.org', config('mail.mailers.smtp.host'));
        $this->assertSame('env-from@example.com', config('mail.from.address'));
    }

    public function test_owner_email_prefers_admin_then_falls_back_to_mail_seller(): void
    {
        $this->assertSame('env-seller@example.com', app(MailSettings::class)->ownerEmail());

        MailSettings::flush();
        $this->storeSmtp(['owner_email' => 'owner@shop.vn']);
        $this->assertSame('owner@shop.vn', app(MailSettings::class)->ownerEmail());
    }

    public function test_apply_survives_a_database_without_the_settings_table(): void
    {
        // A fresh install runs migrations before project_settings exists; failing to
        // read a setting must not stop the process that is creating it.
        MailSettings::flush();
        \Illuminate\Support\Facades\Schema::drop('project_settings');

        app(MailSettings::class)->apply();

        $this->assertSame('smtp.mailgun.org', config('mail.mailers.smtp.host'));
    }

    public function test_saving_the_admin_screen_invalidates_the_cached_configuration(): void
    {
        $this->storeSmtp();
        $this->assertTrue(app(MailSettings::class)->usesAdminSettings());

        // Written straight to the model, bypassing the controller: without the
        // explicit flush the stale value would survive the TTL.
        ProjectSetting::query()->where('setting_key', 'notification_settings')->update(['setting_value' => null]);
        $this->assertTrue(app(MailSettings::class)->usesAdminSettings(), 'giá trị cũ phải còn trong cache');

        MailSettings::flush();
        $this->assertFalse(app(MailSettings::class)->usesAdminSettings());
    }

    public function test_resolving_the_mail_manager_applies_the_admin_configuration(): void
    {
        // The wiring, not just the service: every mail in the system goes through the
        // mail manager, so hooking its resolution is what makes the admin screen win
        // for password resets and invoices too — not only the store notification.
        $this->storeSmtp();
        $this->app->forgetInstance('mail.manager');

        $this->app->make('mail.manager');

        $this->assertSame('smtp.shop.vn', config('mail.mailers.smtp.host'));
        $this->assertSame('shop@shop.vn', config('mail.from.address'));
    }

    private function storeSmtp(array $overrides = []): void
    {
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => 'notification_settings'],
            ['setting_value' => ['smtp' => array_merge([
                'enabled' => true,
                'host' => 'smtp.shop.vn',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'shop-user',
                'password' => 'shop-password',
                'from_email' => 'shop@shop.vn',
                'from_name' => 'Shop',
                'owner_email' => '',
            ], $overrides)]],
        );
    }
}
