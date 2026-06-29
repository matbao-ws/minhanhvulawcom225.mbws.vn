<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AddonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'permissions' => ['manage_settings'],
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

        (new AddonSeeder)->run();
        Addon::query()->update(['is_purchased' => true]);

        $package = \App\Models\Package::query()->create([
            'code' => 'basic_2m',
            'name' => 'Basic 2M',
            'price' => 2000000.00,
            'is_active' => true
        ]);

        \App\Models\ProjectSubscription::query()->create([
            'package_id' => $package->id,
            'status' => 'active',
            'started_at' => now(),
            'expired_at' => null,
        ]);

        \App\Models\FeatureSetting::query()->create([
            'feature_code' => 'zalo_oa',
            'is_enabled' => true,
        ]);
    }

    public function test_guests_cannot_access_notification_settings(): void
    {
        $response = $this->get('/vi/admin/notification-settings');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_users_cannot_access_notification_settings(): void
    {
        $response = $this->actingAs($this->guestUser)->get('/vi/admin/notification-settings');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_notification_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/vi/admin/notification-settings');

        $response->assertOk();
        $response->assertViewIs('admin.notification_settings.index');
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
        \App\Models\FeatureSetting::query()->where('feature_code', 'zalo_oa')->update(['is_enabled' => false]);

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
        \App\Models\FeatureSetting::query()->where('feature_code', 'zalo_oa')->update(['is_enabled' => false]);

        $response = $this->actingAs($this->adminUser)->postJson('/vi/admin/notification-settings', [
            'zalo_oa' => [
                'enabled' => '1',
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => __('admin.notification_settings.no_package')
        ]);
    }

    public function test_admin_can_enable_smtp_without_active_subscription(): void
    {
        // Disable zalo_oa feature setting
        \App\Models\FeatureSetting::query()->where('feature_code', 'zalo_oa')->update(['is_enabled' => false]);

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
}
