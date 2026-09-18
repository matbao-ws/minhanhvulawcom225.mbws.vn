<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\CloudinaryCredentials;
use App\Services\CloudinaryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class MediaCloudinaryConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
        Storage::fake('public');
    }

    public function test_authorized_admin_can_connect_and_credentials_are_encrypted_at_rest(): void
    {
        $this->actingWith(['media.view', 'settings.view', 'settings.update']);
        Http::fake([
            'https://api.cloudinary.com/v1_1/shop-cloud/ping' => Http::response(['status' => 'ok']),
        ]);

        $secret = 'cloudinary-secret-value';
        $response = $this->from('/vi/admin/media')->post('/vi/admin/media/cloudinary/connect', [
            'cloud_name' => 'shop-cloud',
            'api_key' => '123456789',
            'api_secret' => $secret,
            'current_password' => 'password',
        ]);

        $response->assertRedirect('/vi/admin/media')->assertSessionHas('success');
        $this->assertNull(session()->getOldInput('cloud_name'));
        $this->assertNull(session()->getOldInput('api_key'));
        $this->assertNull(session()->getOldInput('api_secret'));
        $this->assertStringNotContainsString($secret, serialize(session()->all()));

        $raw = (string) DB::table('project_settings')
            ->where('setting_key', CloudinaryCredentials::SETTING_KEY)
            ->value('setting_value');
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertStringNotContainsString('123456789', $raw);

        $stored = ProjectSetting::query()
            ->where('setting_key', CloudinaryCredentials::SETTING_KEY)
            ->firstOrFail()
            ->setting_value;
        $this->assertSame('shop-cloud', $stored['cloud_name']);
        $this->assertSame('123456789', $stored['api_key']);
        $this->assertSame($secret, $stored['api_secret']);

        $activity = (string) DB::table('admin_activity_logs')->latest('id')->value('changes');
        $this->assertStringNotContainsString($secret, $activity);
        $this->get('/vi/admin/settings')
            ->assertOk()
            ->assertDontSee($secret)
            ->assertDontSee('123456789');
        $this->getJson('/api/public/settings')
            ->assertOk()
            ->assertDontSee($secret)
            ->assertDontSee('123456789');
    }

    public function test_connection_is_verified_before_any_credentials_are_saved(): void
    {
        $this->actingWith(['media.view', 'settings.update']);
        Http::fake([
            'https://api.cloudinary.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        ]);

        $response = $this->from('/vi/admin/media')->post('/vi/admin/media/cloudinary/connect', [
            'cloud_name' => 'shop-cloud',
            'api_key' => '123456789',
            'api_secret' => 'wrong-secret',
            'current_password' => 'password',
        ]);

        $response->assertRedirect('/vi/admin/media')
            ->assertSessionHasErrors('api_secret', null, 'cloudinary');
        $this->assertNull(session()->getOldInput('cloud_name'));
        $this->assertNull(session()->getOldInput('api_key'));
        $this->assertNull(session()->getOldInput('api_secret'));
        $this->assertDatabaseMissing('project_settings', [
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
        ]);
    }

    public function test_connection_requires_the_current_password_and_settings_permission(): void
    {
        Http::fake();
        $this->actingWith(['media.view', 'settings.update']);

        $this->from('/vi/admin/media')->post('/vi/admin/media/cloudinary/connect', [
            'cloud_name' => 'shop-cloud',
            'api_key' => '123456789',
            'api_secret' => 'valid-secret',
            'current_password' => 'incorrect-password',
        ])->assertSessionHasErrors('current_password', null, 'cloudinary');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('project_settings', [
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
        ]);

        auth()->logout();
        $this->actingWith(['media.view']);
        $this->post('/vi/admin/media/cloudinary/connect', [
            'cloud_name' => 'shop-cloud',
            'api_key' => '123456789',
            'api_secret' => 'valid-secret',
            'current_password' => 'password',
        ])->assertForbidden();
    }

    public function test_guests_and_customers_cannot_change_cloudinary_credentials(): void
    {
        Http::fake();
        $payload = [
            'cloud_name' => 'shop-cloud',
            'api_key' => '123456789',
            'api_secret' => 'valid-secret',
            'current_password' => 'password',
        ];

        $this->post('/vi/admin/media/cloudinary/connect', $payload)
            ->assertRedirect('/vi/admin/login');

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)
            ->post('/vi/admin/media/cloudinary/connect', $payload)
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('project_settings', [
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
        ]);
    }

    public function test_disconnect_removes_saved_secrets_and_overrides_environment_credentials(): void
    {
        config()->set('services.cloudinary', [
            'cloud_name' => 'environment-cloud',
            'api_key' => 'environment-key',
            'api_secret' => 'environment-secret',
        ]);
        ProjectSetting::query()->create([
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
            'setting_value' => [
                'enabled' => true,
                'cloud_name' => 'database-cloud',
                'api_key' => 'database-key',
                'api_secret' => 'database-secret',
            ],
            'updated_at' => now(),
        ]);
        $this->actingWith(['media.view', 'settings.update']);

        $this->from('/vi/admin/media')->delete('/vi/admin/media/cloudinary/disconnect', [
            'current_password' => 'password',
        ])->assertRedirect('/vi/admin/media')->assertSessionHas('success');

        $raw = (string) DB::table('project_settings')
            ->where('setting_key', CloudinaryCredentials::SETTING_KEY)
            ->value('setting_value');
        $this->assertStringNotContainsString('database-secret', $raw);
        $this->assertSame(
            ['enabled' => false],
            ProjectSetting::query()->where('setting_key', CloudinaryCredentials::SETTING_KEY)->firstOrFail()->setting_value,
        );
        $this->assertNull(app(CloudinaryCredentials::class)->current());
    }

    public function test_connection_controls_are_only_rendered_for_settings_editors(): void
    {
        $this->actingWith(['media.view']);
        $this->get('/vi/admin/media')
            ->assertOk()
            ->assertDontSee('cloudinaryConnectionModal', false);

        auth()->logout();
        $this->actingWith(['media.view', 'settings.update']);
        $response = $this->get('/vi/admin/media')
            ->assertOk()
            ->assertSee('cloudinaryConnectionModal', false)
            ->assertSee('name="cloud_name"', false)
            ->assertSee('name="api_key"', false)
            ->assertSee('name="api_secret"', false)
            ->assertSee('cloudinaryCredentialGuideModal', false)
            ->assertSee('cloudinary-api-keys-guide.png', false);
        $this->assertSame(3, substr_count($response->getContent(), 'data-cloudinary-guide-open data-guide-field='));
    }

    public function test_invalid_credential_fields_are_rejected_without_being_flashed_or_sent(): void
    {
        Http::fake();
        $this->actingWith(['media.view', 'settings.update']);

        $this->from('/vi/admin/media')->post('/vi/admin/media/cloudinary/connect', [
            'cloud_name' => "bad\ncloud",
            'api_key' => 'bad key',
            'api_secret' => '<script>secret</script>',
            'current_password' => 'password',
        ])->assertRedirect('/vi/admin/media')
            ->assertSessionHasErrors(['cloud_name', 'api_key', 'api_secret'], null, 'cloudinary');

        Http::assertNothingSent();
        $this->assertNull(session()->getOldInput('cloud_name'));
        $this->assertNull(session()->getOldInput('api_key'));
        $this->assertNull(session()->getOldInput('api_secret'));
        $this->assertDatabaseMissing('project_settings', [
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
        ]);
    }

    public function test_connected_screen_displays_only_non_secret_connection_details(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
            'setting_value' => [
                'enabled' => true,
                'cloud_name' => 'visible-cloud-name',
                'api_key' => 'hidden-api-key',
                'api_secret' => 'hidden-api-secret',
            ],
            'updated_at' => now(),
        ]);
        $this->mock(CloudinaryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('listFolders')->andReturn(['general']);
            $mock->shouldReceive('listResources')->andReturn([]);
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('storageUsage')->andReturn([
                'used' => 1024,
                'limit' => 10240,
                'credits_used' => 1.5,
                'credits_limit' => 25.0,
            ]);
        });
        $this->actingWith(['media.view', 'settings.update']);

        // A masked hint ("hidd****-key") is shown so an admin can recognise the
        // saved credential, but the full raw value must never reach the page.
        $this->get('/vi/admin/media')
            ->assertOk()
            ->assertSee('visible-cloud-name')
            ->assertSee('1.5')
            ->assertSee('hidd****-key', false)
            ->assertSee('hidd****cret', false)
            ->assertDontSee('hidden-api-key')
            ->assertDontSee('hidden-api-secret');

        $this->actingWith(['media.view']);

        $this->get('/vi/admin/media')
            ->assertOk()
            ->assertDontSee('hidden-api-key')
            ->assertDontSee('hidden-api-secret');
    }

    public function test_summary_masks_short_credentials_completely_instead_of_partially(): void
    {
        // Showing 4+4 characters of an 8-character value would leave almost
        // nothing hidden, so anything that short is masked in full instead.
        ProjectSetting::query()->create([
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
            'setting_value' => [
                'enabled' => true,
                'cloud_name' => 'shop-cloud',
                'api_key' => '12345678',
                'api_secret' => 'abc123',
            ],
            'updated_at' => now(),
        ]);

        $summary = app(CloudinaryCredentials::class)->summary();

        $this->assertSame('********', $summary['api_key']);
        $this->assertSame('******', $summary['api_secret']);
    }

    private function actingWith(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Cloudinary '.md5(implode(',', $permissions).microtime(true)),
            'permissions' => $permissions,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);

        return $user;
    }
}
