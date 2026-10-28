<?php

namespace Tests\Feature;

use App\Mail\MailSettingsTestMail;
use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['settings.view', 'settings.update', 'shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete', 'payments.view', 'payments.create', 'payments.update', 'payments.delete'],
        ]);

        $guestRole = Role::query()->create([
            'name' => 'Guest',
            'permissions' => [],
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->guestUser = User::factory()->create([
            'role_id' => $guestRole->id,
        ]);

        FeatureSetting::query()->create([
            'feature_code' => 'zalo_oa',
            'is_enabled' => true,
        ]);
    }

    public function test_guests_cannot_access_notification_settings(): void
    {
        $response = $this->get('/vi/admin/notification-settings');
        $response->assertRedirect('/vi/admin/login');
    }

    public function test_unauthorized_users_cannot_access_notification_settings(): void
    {
        $response = $this->actingAs($this->guestUser)->get('/vi/admin/notification-settings');
        $response->assertStatus(403);
    }

    /** The HTTP client's error names the URL, and the Zalo bot token is part of that URL. */
    public function test_a_failed_zalo_lookup_does_not_echo_the_bot_token(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out for https://bot-api.zaloplatforms.com/botSECRET-BOT-TOKEN-123/getUpdates',
        ));

        $this->actingAs($this->adminUser)
            ->postJson('/vi/admin/notification-settings/get-zalo-chat-id', ['bot_token' => 'SECRET-BOT-TOKEN-123'])
            ->assertStatus(500)
            ->assertDontSee('SECRET-BOT-TOKEN-123');
    }

    public function test_admin_can_view_notification_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notification-settings');

        $response->assertOk();
        $response->assertViewIs('admin.notification_settings.index');
    }

    public function test_settings_page_tells_the_admin_that_mail_uses_the_shared_smtp(): void
    {
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_secret_token']);
        Http::fake(['gw.test/v1/project' => Http::response(['ok' => true, 'project' => [
            'mail_from' => 'noreply@mail.relay.test',
        ]])]);

        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notification-settings');

        $response->assertOk();
        $response->assertViewHas('mailSource', MailSettings::SOURCE_REMOTE);
        $response->assertSee('mail.relay.test');
        $response->assertDontSee('mbp_secret_token');
    }

    public function test_admin_can_update_notification_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '1',
                'app_id' => '12345678',
                'secret_key' => 'secret_val',
                'access_token' => 'access_val',
                'refresh_token' => 'refresh_val',
                'template_id' => '99999',
            ],
            'zalo_personal' => [
                'enabled' => '1',
                'bot_token' => 'bot_token_val',
                'chat_id' => 'chat_id_val',
            ],
            'smtp' => [
                'enabled' => '1',
                'host' => 'smtp.test.com',
                'port' => '587',
                'encryption' => 'tls',
                'username' => 'testuser',
                'password' => 'testpass',
                'from_email' => 'shop@test.com',
                'from_name' => 'Shop Name',
                'owner_email' => 'boss@test.com',
            ],
        ]);

        $response->assertRedirect('/vi/admin/notification-settings');
        $response->assertSessionHas('success');

        $setting = ProjectSetting::where('setting_key', 'notification_settings')->first();
        $this->assertNotNull($setting);

        $value = $setting->setting_value;
        $this->assertTrue($value['zalo_oa']['enabled']);
        $this->assertEquals('12345678', $value['zalo_oa']['app_id']);
        $this->assertEquals('secret_val', $value['zalo_oa']['secret_key']);
        $this->assertEquals('access_val', $value['zalo_oa']['access_token']);
        $this->assertEquals('refresh_val', $value['zalo_oa']['refresh_token']);
        $this->assertEquals('99999', $value['zalo_oa']['template_id']);
        $this->assertTrue($value['zalo_personal']['enabled']);
        $this->assertEquals('bot_token_val', $value['zalo_personal']['bot_token']);
        $this->assertEquals('chat_id_val', $value['zalo_personal']['chat_id']);
        $this->assertTrue($value['smtp']['enabled']);
        $this->assertEquals('smtp.test.com', $value['smtp']['host']);
        $this->assertEquals(587, $value['smtp']['port']);
        $this->assertEquals('tls', $value['smtp']['encryption']);
        $this->assertEquals('testuser', $value['smtp']['username']);
        $this->assertEquals('testpass', $value['smtp']['password']);
        $this->assertEquals('shop@test.com', $value['smtp']['from_email']);
        $this->assertEquals('Shop Name', $value['smtp']['from_name']);
        $this->assertEquals('boss@test.com', $value['smtp']['owner_email']);
    }

    public function test_several_owner_emails_are_saved_as_one_clean_list(): void
    {
        $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'smtp' => $this->smtpFields(['owner_email' => "boss@test.com;  sales@test.com\nboss@test.com"]),
        ])->assertRedirect('/vi/admin/notification-settings')->assertSessionHas('success');

        $value = ProjectSetting::where('setting_key', 'notification_settings')->first()->setting_value;
        $this->assertSame('boss@test.com, sales@test.com', $value['smtp']['owner_email']);
    }

    public function test_owner_emails_are_edited_as_chips_over_one_hidden_field(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => 'notification_settings',
            'setting_value' => ['smtp' => ['owner_email' => 'boss@test.com, sales@test.com']],
        ]);

        $this->actingAs($this->adminUser)->get('/vi/admin/notification-settings')
            ->assertOk()
            ->assertSee('id="smtp_owner_email_box" data-max="5"', false)
            ->assertSee('id="smtp_owner_email_entry"', false)
            ->assertSee('<input type="hidden" id="smtp_owner_email" name="smtp[owner_email]" data-focus="smtp_owner_email_entry"', false)
            ->assertSee('value="boss@test.com, sales@test.com"', false);
    }

    public function test_an_invalid_or_excess_owner_email_is_refused(): void
    {
        $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'smtp' => $this->smtpFields(['owner_email' => 'boss@test.com, not-an-email']),
        ])->assertSessionHasErrors(['smtp.owner_email' => 'Email nhận thông báo "not-an-email" không hợp lệ.']);

        $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'smtp' => $this->smtpFields(['owner_email' => 'a@t.vn, b@t.vn, c@t.vn, d@t.vn, e@t.vn, f@t.vn']),
        ])->assertSessionHasErrors(['smtp.owner_email' => 'Chỉ nhập tối đa 5 email nhận thông báo.']);

        $this->assertDatabaseMissing('project_settings', ['setting_key' => 'notification_settings']);
    }

    public function test_a_test_email_goes_to_every_recipient_on_the_form_without_saving(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/test-email', [
            'smtp' => ['enabled' => '0', 'owner_email' => 'boss@test.com; sales@test.com'],
        ])->assertOk()->assertJson(['success' => true, 'message' => 'Đã gửi email thử tới boss@test.com, sales@test.com. Kiểm tra cả hộp thư Spam nếu chưa thấy.']);

        Mail::assertSent(MailSettingsTestMail::class, fn ($mail) => $mail->hasTo('boss@test.com') && $mail->hasTo('sales@test.com'));
        $this->assertDatabaseMissing('project_settings', ['setting_key' => 'notification_settings']);
    }

    public function test_a_test_email_without_recipients_goes_to_the_admin_themself(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/test-email', ['smtp' => ['enabled' => '0']])->assertOk();

        Mail::assertSent(MailSettingsTestMail::class, fn ($mail) => $mail->hasTo($this->adminUser->email));
    }

    public function test_a_test_email_is_refused_when_the_server_only_logs_mail(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/test-email', [
            'smtp' => ['enabled' => '0', 'owner_email' => 'boss@test.com'],
        ])->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('message', fn ($message) => str_contains($message, 'chỉ ghi email vào log'));
    }

    public function test_a_failing_smtp_account_reports_the_error(): void
    {
        // Nothing listens on port 1: the connection is refused at once.
        $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/test-email', [
            'smtp' => $this->smtpFields(['host' => '127.0.0.1', 'port' => '1', 'encryption' => 'none', 'owner_email' => 'boss@test.com']),
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($message) => str_starts_with($message, 'Gửi email thử không thành công: ') && ! str_contains($message, 'testpass'));
    }

    public function test_the_test_email_checks_the_form_and_the_permission(): void
    {
        $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/test-email', [
            'smtp' => $this->smtpFields(['host' => '', 'owner_email' => 'boss@test.com']),
        ])->assertStatus(422)->assertJsonValidationErrors('smtp.host');

        $this->actingAs($this->guestUser)->postJson('/vi/admin/notification-settings/test-email', [
            'smtp' => ['enabled' => '0', 'owner_email' => 'boss@test.com'],
        ])->assertForbidden();
    }

    private function smtpFields(array $overrides): array
    {
        return array_merge([
            'enabled' => '1',
            'host' => 'smtp.test.com',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'testuser',
            'password' => 'testpass',
            'from_email' => 'shop@test.com',
            'from_name' => 'Shop Name',
        ], $overrides);
    }

    public function test_validation_fails_when_enabled_channel_has_missing_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '1',
                'app_id' => '', // missing app_id
                'template_id' => '99999',
            ],
            'zalo_personal' => [
                'enabled' => '1',
                'bot_token' => '', // missing bot_token
                'chat_id' => 'chat_id_val',
            ],
        ]);

        $response->assertStatus(302); // Redirects back with validation errors
        $response->assertSessionHasErrors([
            'zalo_oa.app_id',
            'zalo_oa.secret_key',
            'zalo_oa.access_token',
            'zalo_oa.refresh_token',
            'zalo_personal.bot_token',
        ]);
    }

    public function test_get_zalo_chat_id_requires_authentication(): void
    {
        $response = $this->postJson('/vi/admin/notification-settings/get-zalo-chat-id', [
            'bot_token' => 'dummy_token',
        ]);
        $response->assertStatus(401); // Unauthorized
    }

    public function test_get_zalo_chat_id_requires_bot_token(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/get-zalo-chat-id', [
            'bot_token' => '',
        ]);
        $response->assertStatus(422); // Unprocessable Entity
        $response->assertJsonValidationErrors(['bot_token']);
    }

    public function test_get_zalo_chat_id_success(): void
    {
        Http::fake([
            'https://bot-api.zaloplatforms.com/*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 123,
                        'message' => [
                            'message_id' => 456,
                            'from' => [
                                'id' => 'zalo_user_1',
                                'display_name' => 'Nguyễn Văn A',
                            ],
                            'chat' => [
                                'id' => 'chat_1',
                            ],
                            'text' => 'hello',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/get-zalo-chat-id', [
            'bot_token' => 'valid_bot_token',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'chats' => [
                [
                    'chat_id' => 'chat_1',
                    'display_name' => 'Nguyễn Văn A',
                ],
            ],
        ]);
    }

    public function test_get_zalo_chat_id_failure(): void
    {
        Http::fake([
            'https://bot-api.zaloplatforms.com/*' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings/get-zalo-chat-id', [
            'bot_token' => 'invalid_bot_token',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => __('admin.notification_settings.zalo_personal.get_chat_id_error'),
        ]);
    }

    public function test_admin_cannot_enable_notifications_without_active_subscription(): void
    {
        // Disable zalo_oa feature setting
        FeatureSetting::query()->where('feature_code', 'zalo_oa')->update(['is_enabled' => false]);

        $response = $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '1',
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', __('admin.notification_settings.no_package'));
    }

    public function test_admin_cannot_enable_notifications_without_active_subscription_ajax(): void
    {
        // Disable zalo_oa feature setting
        FeatureSetting::query()->where('feature_code', 'zalo_oa')->update(['is_enabled' => false]);

        $response = $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '1',
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => __('admin.notification_settings.no_package'),
        ]);
    }

    public function test_admin_can_enable_smtp_without_active_subscription(): void
    {
        // Disable zalo_oa feature setting
        FeatureSetting::query()->where('feature_code', 'zalo_oa')->update(['is_enabled' => false]);

        $response = $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '0',
            ],
            'zalo_personal' => [
                'enabled' => '0',
            ],
            'smtp' => [
                'enabled' => '1',
                'host' => 'smtp.test.com',
                'port' => '587',
                'encryption' => 'tls',
                'username' => 'testuser',
                'password' => 'testpass',
                'from_email' => 'shop@test.com',
                'from_name' => 'Shop Name',
                'owner_email' => 'boss@test.com',
            ],
        ]);

        $response->assertRedirect('/vi/admin/notification-settings');
        $response->assertSessionHas('success');

        $setting = ProjectSetting::where('setting_key', 'notification_settings')->first();
        $this->assertNotNull($setting);
        $this->assertTrue($setting->setting_value['smtp']['enabled']);
        $this->assertFalse($setting->setting_value['zalo_oa']['enabled']);
        $this->assertFalse($setting->setting_value['zalo_personal']['enabled']);
    }

    public function test_admin_can_enable_zalo_when_the_feature_is_enabled(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '1',
                'app_id' => '12345678',
                'secret_key' => 'secret_val',
                'access_token' => 'access_val',
                'refresh_token' => 'refresh_val',
                'template_id' => '99999',
            ],
            'zalo_personal' => ['enabled' => '0'],
        ]);

        $response->assertRedirect('/vi/admin/notification-settings');
        $this->assertTrue(ProjectSetting::query()->firstOrFail()->setting_value['zalo_oa']['enabled']);
    }
}
