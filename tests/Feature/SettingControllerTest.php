<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        // Seed default settings
        ProjectSetting::query()->create([
            'setting_key' => 'shop_name',
            'setting_value' => 'Test Shop Name',
        ]);

        ProjectSetting::query()->create([
            'setting_key' => 'contact',
            'setting_value' => [
                'phone' => '123456789',
                'email' => 'contact@test.com',
                'address' => 'Test Address',
            ],
        ]);
    }

    public function test_guests_cannot_access_settings(): void
    {
        $response = $this->get('/vi/admin/settings');
        $response->assertRedirect('/vi/admin/login');
    }

    public function test_non_admins_cannot_access_settings(): void
    {
        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer);

        $response = $this->get('/vi/admin/settings');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_settings(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $response = $this->get('/vi/admin/settings');
        $response->assertOk();
        $response->assertSee('Test Shop Name');
        $response->assertSee('123456789');
        $response->assertSee('contact@test.com');
        $response->assertDontSee('Cấu hình Giao diện');
        $response->assertDontSee('Cấu hình đa ngôn ngữ');
        $response->assertDontSee('Cấu hình Email (SMTP)');
    }

    public function test_settings_page_restores_the_requested_tab(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get('/vi/admin/settings?tab=contact')
            ->assertOk()
            ->assertViewHas('activeTab', 'contact')
            ->assertSee('data-settings-tab="contact"', false);
    }

    public function test_settings_update_redirects_back_to_the_active_tab(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->post('/vi/admin/settings', [
                'shop_name' => 'Updated Shop Name',
                'active_tab' => 'analytics',
            ])
            ->assertRedirect('/vi/admin/settings?tab=analytics');
    }

    public function test_admin_layout_uses_the_configured_favicon(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => 'favicon_url',
            'setting_value' => 'https://cdn.example.test/settings/site-icon.ico',
        ]);

        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertSee('https://cdn.example.test/settings/site-icon.ico');
    }

    public function test_updating_website_settings_does_not_modify_notification_smtp_settings(): void
    {
        $smtpSettings = ProjectSetting::query()->create([
            'setting_key' => 'notification_settings',
            'setting_value' => [
                'smtp' => [
                    'enabled' => true,
                    'host' => 'smtp.example.test',
                ],
            ],
        ]);
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->post('/vi/admin/settings', ['shop_name' => 'Updated Shop Name'])
            ->assertRedirect('/vi/admin/settings');

        $this->assertSame('smtp.example.test', $smtpSettings->fresh()->setting_value['smtp']['host']);
        $this->assertTrue($smtpSettings->fresh()->setting_value['smtp']['enabled']);
    }

    /** Embed code is raw script that runs in a superadmin's session: only a superadmin sets it. */
    public function test_only_a_superadmin_sets_embed_code(): void
    {
        ProjectSetting::query()->create(['setting_key' => 'embed_footer', 'setting_value' => '<script>chat()</script>']);
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)->post('/vi/admin/settings', [
            'shop_name' => 'Test Shop Name',
            'embed_header' => '<script>steal()</script>',
        ])->assertRedirect('/vi/admin/settings');

        $this->assertNull(ProjectSetting::query()->where('setting_key', 'embed_header')->value('setting_value'));
        $this->assertSame('<script>chat()</script>', ProjectSetting::query()->where('setting_key', 'embed_footer')->value('setting_value'), 'left as it was, not wiped');
        $this->get('/vi/admin/settings')->assertSee(__('admin.settings.embed.superadmin_only'));

        $superadmin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true])->id]);
        $this->actingAs($superadmin)->post('/vi/admin/settings', [
            'shop_name' => 'Test Shop Name',
            'embed_header' => '<meta name="verify" content="x">',
            // A superadmin's form also carries the multilingual section.
            'multilingual' => ['enabled' => false, 'mode' => 'gtranslate', 'gtranslate' => [
                'target_locales' => ['en'], 'widget_look' => 'float', 'position' => 'bottom_right',
                'detect_browser_language' => false, 'native_language_names' => true,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('<meta name="verify" content="x">', ProjectSetting::query()->where('setting_key', 'embed_header')->value('setting_value'));
    }

    public function test_admin_can_update_settings(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->actingAs($admin);

        $response = $this->post('/vi/admin/settings', [
            'shop_name' => 'Updated Shop Name',
            'contact' => [
                'phone' => '987654321',
                'email' => 'updated@test.com',
                'address' => 'Updated Address',
            ],
            'seo' => [
                'title' => 'Updated SEO Title',
                'description' => 'Updated SEO Description',
            ],
            'social_links' => [
                'facebook' => 'https://facebook.com/updatedpage',
            ],
            'logo' => UploadedFile::fake()->image('logo.png'),
            'favicon' => UploadedFile::fake()->image('favicon.png'),
        ]);

        $response->assertRedirect('/vi/admin/settings');
        $response->assertSessionHas('success');

        // Assert database values
        $this->assertEquals('Updated Shop Name', ProjectSetting::where('setting_key', 'shop_name')->first()->setting_value);

        $contact = ProjectSetting::where('setting_key', 'contact')->first()->setting_value;
        $this->assertEquals('987654321', $contact['phone']);
        $this->assertEquals('updated@test.com', $contact['email']);

        $logoUrl = ProjectSetting::where('setting_key', 'logo_url')->first()->setting_value;
        $this->assertNotNull($logoUrl);

        $faviconUrl = ProjectSetting::where('setting_key', 'favicon_url')->first()->setting_value;
        $this->assertNotNull($faviconUrl);
    }

    public function test_admin_can_select_existing_media_for_site_branding(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)->post('/vi/admin/settings', [
            'shop_name' => 'Library Settings',
            'logo_url' => 'https://cdn.example.test/settings/logo.png',
            'favicon_url' => 'https://cdn.example.test/settings/favicon.png',
        ])->assertRedirect('/vi/admin/settings');

        $this->assertSame('https://cdn.example.test/settings/logo.png', ProjectSetting::query()->where('setting_key', 'logo_url')->value('setting_value'));
        $this->assertSame('https://cdn.example.test/settings/favicon.png', ProjectSetting::query()->where('setting_key', 'favicon_url')->value('setting_value'));
    }
}
