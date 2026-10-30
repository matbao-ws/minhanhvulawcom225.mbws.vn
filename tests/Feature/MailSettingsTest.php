<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\AiGateway\GatewayClient;
use App\Services\MailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Exception\TransportException;
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

    public function test_several_owner_emails_are_split_and_the_first_is_the_reply_to(): void
    {
        $this->storeSmtp(['owner_email' => " owner@shop.vn, sales@shop.vn;\nOWNER@shop.vn "]);
        $settings = app(MailSettings::class);

        $this->assertSame(['owner@shop.vn', 'sales@shop.vn'], $settings->ownerEmails());
        $this->assertSame('owner@shop.vn', $settings->ownerEmail());
    }

    public function test_apply_survives_a_database_without_the_settings_table(): void
    {
        // A fresh install runs migrations before project_settings exists; failing to
        // read a setting must not stop the process that is creating it.
        MailSettings::flush();
        Schema::drop('project_settings');

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

    public function test_relay_is_used_while_the_admin_screen_is_untouched(): void
    {
        $this->useRelay();
        $this->storeSmtp(['enabled' => false, 'owner_email' => 'owner@shop.vn']);

        $settings = app(MailSettings::class);
        $settings->apply();

        $this->assertSame(MailSettings::SOURCE_REMOTE, $settings->source());
        $this->assertSame(MailSettings::GATEWAY_MAILER, config('mail.default'));
        // Replies to the relay's noreply sender must still reach the shop.
        $this->assertSame('owner@shop.vn', config('mail.reply_to.address'));
        // No shared credential is ever written into this site's mail config.
        $this->assertSame('env-password', config('mail.mailers.smtp.password'));
    }

    public function test_admin_configuration_outranks_the_relay(): void
    {
        $this->useRelay();
        $this->storeSmtp();

        $settings = app(MailSettings::class);
        $settings->apply();

        $this->assertSame(MailSettings::SOURCE_ADMIN, $settings->source());
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.shop.vn', config('mail.mailers.smtp.host'));
        $this->assertNull(config('mail.reply_to'));
    }

    public function test_without_a_gateway_token_mail_stays_on_env(): void
    {
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => null]);

        app(MailSettings::class)->apply();

        $this->assertSame(MailSettings::SOURCE_ENV, app(MailSettings::class)->source());
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.mailgun.org', config('mail.mailers.smtp.host'));
    }

    public function test_mail_through_the_relay_goes_to_the_gateway_with_the_project_token(): void
    {
        $this->useRelay();
        $this->storeSmtp(['enabled' => false, 'owner_email' => 'owner@shop.vn']);
        $this->resolveMailer();

        Mail::html('<p>Cảm ơn bạn</p>', function ($message) {
            $message->to('khach@example.com')->cc('ke-toan@shop.vn')->subject('Xác nhận đơn hàng')
                ->attachData('%PDF-1.4 fake', 'hoa-don.pdf', ['mime' => 'application/pdf']);
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gw.test/v1/mail/send'
                && $request->hasHeader('Authorization', 'Bearer mbp_relay_token')
                && $request['to'] === ['khach@example.com']
                && $request['cc'] === ['ke-toan@shop.vn']
                && $request['subject'] === 'Xác nhận đơn hàng'
                && str_contains($request['html'], 'Cảm ơn bạn')
                && $request['reply_to'] === 'owner@shop.vn'
                && $request['from_name'] === 'Env Sender'
                // The website never picks the From address; the relay does.
                && ! isset($request['from'])
                && $request['attachments'] === [['filename' => 'hoa-don.pdf', 'content' => base64_encode('%PDF-1.4 fake')]];
        });
    }

    public function test_an_embedded_logo_reaches_the_relay_with_its_content_id(): void
    {
        $this->useRelay();
        $this->resolveMailer();

        Mail::send([], [], function ($message) {
            $cid = $message->embedData('png-bytes', 'logo.png', 'image/png');
            $message->to('khach@example.com')->subject('Logo')->html('<img src="'.$cid.'">');
        });

        Http::assertSent(function (Request $request) {
            $logo = $request['attachments'][0] ?? [];

            return ($logo['filename'] ?? null) === 'logo.png'
                && filled($logo['content_id'] ?? null)
                && str_contains($request['html'], 'cid:'.$logo['content_id']);
        });
    }

    public function test_a_relay_refusal_is_a_transport_failure_so_the_queue_retries(): void
    {
        $this->useRelay(Http::response(['ok' => false, 'reason' => 'provider_unavailable'], 503));
        $this->resolveMailer();

        $this->expectException(TransportException::class);

        Mail::raw('Xin chào', fn ($message) => $message->to('khach@example.com')->subject('Test'));
    }

    public function test_password_reset_mail_is_queued(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $user->sendPasswordResetNotification('token-123');

        Notification::assertSentTo($user, QueuedResetPassword::class);
        $this->assertInstanceOf(ShouldQueue::class, new QueuedResetPassword('t'));
    }

    public function test_refresh_command_shows_the_relay_without_printing_the_token(): void
    {
        $this->useRelay();

        $this->artisan('mail:refresh-smtp')
            ->expectsOutputToContain('https://gw.test')
            ->expectsOutputToContain('noreply@mail.matbaows.com')
            ->doesntExpectOutputToContain('mbp_relay_token')
            ->assertSuccessful();
    }

    private function useRelay($sendResponse = null): void
    {
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_relay_token']);
        GatewayClient::forgetProject();

        Http::fake([
            'gw.test/v1/mail/send' => $sendResponse ?? Http::response(['ok' => true, 'id' => 'msg_1', 'used' => 1, 'limit' => 200]),
            'gw.test/v1/project' => Http::response(['ok' => true, 'project' => ['mail_from' => 'noreply@mail.matbaows.com']]),
        ]);
    }

    /** The mailer is resolved fresh so MailSettings and the relay transport apply. */
    private function resolveMailer(): void
    {
        $this->app->forgetInstance('mail.manager');
        $this->app->forgetInstance('mailer');
        Mail::clearResolvedInstances();
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
