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
