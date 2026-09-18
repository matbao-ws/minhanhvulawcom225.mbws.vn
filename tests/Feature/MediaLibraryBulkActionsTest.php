<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\CloudinaryCredentials;
use App\Services\CloudinaryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class MediaLibraryBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
        Storage::fake('public');
    }

    public function test_media_library_shows_local_files_alongside_cloudinary_assets_once_connected(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
            'setting_value' => [
                'enabled' => true,
                'cloud_name' => 'demo-cloud',
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('general/legacy-local-photo.jpg', 'legacy content');

        $service = new class(app(CloudinaryCredentials::class)) extends CloudinaryService
        {
            protected function fetchCloudResources(string $folder): array
            {
                return [[
                    'secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/general/cloud-photo.jpg',
                    'public_id' => 'general/cloud-photo',
                    'bytes' => 4096,
                    'created_at' => '2026-01-01T00:00:00Z',
                    'format' => 'jpg',
                    'resource_type' => 'image',
                ]];
            }
        };
        $this->app->instance(CloudinaryService::class, $service);

        $this->actingWith(['media.view', 'media.delete']);

        $response = $this->get('/vi/admin/media?folder=general');

        $response->assertOk();
        $resources = $response->viewData('resources')->getCollection()->keyBy('public_id');
        $this->assertSame('cloudinary', $resources['general/cloud-photo']['source']);
        $this->assertSame('local', $resources['general/legacy-local-photo.jpg']['source']);

        $response->assertSee('general/cloud-photo');
        $response->assertSee('legacy-local-photo.jpg');
        $response->assertSee(__('catalog.media.source_local_badge'));
    }

    public function test_media_library_hides_cloudinary_assets_outside_the_managed_folders(): void
    {
        ProjectSetting::query()->create([
            'setting_key' => CloudinaryCredentials::SETTING_KEY,
            'setting_value' => [
                'enabled' => true,
                'cloud_name' => 'demo-cloud',
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'updated_at' => now(),
        ]);

        // Every Cloudinary account ships with root-level demo assets that
        // predate this app (no folder prefix) and can never be validated for
        // deletion here, so they must never reach the library grid.
        $service = new class(app(CloudinaryCredentials::class)) extends CloudinaryService
        {
            protected function fetchCloudResources(string $folder): array
            {
                return [
                    [
                        'secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/sample.jpg',
                        'public_id' => 'sample',
                        'bytes' => 1024,
                        'created_at' => '2026-01-02T00:00:00Z',
                        'format' => 'jpg',
                        'resource_type' => 'image',
                    ],
                    [
                        'secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/general/cloud-photo.jpg',
                        'public_id' => 'general/cloud-photo',
                        'bytes' => 4096,
                        'created_at' => '2026-01-01T00:00:00Z',
                        'format' => 'jpg',
                        'resource_type' => 'image',
                    ],
                ];
            }
        };
        $this->app->instance(CloudinaryService::class, $service);

        $this->actingWith(['media.view', 'media.delete']);

        $response = $this->get('/vi/admin/media?folder=all');

        $response->assertOk();
        $publicIds = $response->viewData('resources')->getCollection()->pluck('public_id');
        $this->assertTrue($publicIds->contains('general/cloud-photo'));
        $this->assertFalse($publicIds->contains('sample'));
        $response->assertDontSee('value="sample"', false);
    }

    public function test_bulk_delete_removes_multiple_local_files_in_one_request(): void
    {
        Storage::disk('public')->put('general/first.jpg', 'a');
        Storage::disk('public')->put('general/second.jpg', 'b');
        Storage::disk('public')->assertExists('general/first.jpg');
        Storage::disk('public')->assertExists('general/second.jpg');

        $this->actingWith(['media.view', 'media.delete']);

        $response = $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [
                ['public_id' => 'general/first.jpg', 'resource_type' => 'image', 'source' => 'local'],
                ['public_id' => 'general/second.jpg', 'resource_type' => 'image', 'source' => 'local'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', __('catalog.media.bulk_delete_success', ['count' => 2]));
        Storage::disk('public')->assertMissing('general/first.jpg');
        Storage::disk('public')->assertMissing('general/second.jpg');
    }

    public function test_bulk_delete_dispatches_the_correct_source_for_mixed_local_and_cloud_items(): void
    {
        $this->mock(CloudinaryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteResource')
                ->once()
                ->with('general/local-file.jpg', 'image', 'local')
                ->andReturnTrue();
            $mock->shouldReceive('deleteResource')
                ->once()
                ->with('general/cloud-file', 'video', 'cloudinary')
                ->andReturnTrue();
        });

        $this->actingWith(['media.view', 'media.delete']);

        $response = $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [
                ['public_id' => 'general/local-file.jpg', 'resource_type' => 'image', 'source' => 'local'],
                ['public_id' => 'general/cloud-file', 'resource_type' => 'video', 'source' => 'cloudinary'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', __('catalog.media.bulk_delete_success', ['count' => 2]));
    }

    public function test_bulk_delete_reports_partial_failure_when_some_items_fail(): void
    {
        $this->mock(CloudinaryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteResource')->once()->with('general/ok.jpg', 'image', 'local')->andReturnTrue();
            $mock->shouldReceive('deleteResource')->once()->with('general/broken.jpg', 'image', 'cloudinary')->andReturnFalse();
        });

        $this->actingWith(['media.view', 'media.delete']);

        $response = $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [
                ['public_id' => 'general/ok.jpg', 'resource_type' => 'image', 'source' => 'local'],
                ['public_id' => 'general/broken.jpg', 'resource_type' => 'image', 'source' => 'cloudinary'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', __('catalog.media.bulk_delete_partial', ['deleted' => 1, 'failed' => 1]));
    }

    public function test_bulk_delete_requires_at_least_one_item(): void
    {
        $this->actingWith(['media.view', 'media.delete']);

        $this->delete('/vi/admin/media/bulk-delete', ['items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_bulk_delete_rejects_an_invalid_source_value(): void
    {
        $this->actingWith(['media.view', 'media.delete']);

        $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [
                ['public_id' => 'general/file.jpg', 'resource_type' => 'image', 'source' => 'ftp'],
            ],
        ])->assertSessionHasErrors('items.0.source');
    }

    public function test_bulk_delete_rejects_a_public_id_outside_the_known_folders(): void
    {
        $this->actingWith(['media.view', 'media.delete']);

        $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [
                ['public_id' => '../../etc/passwd', 'resource_type' => 'image', 'source' => 'local'],
            ],
        ])->assertSessionHasErrors('items.0.public_id');
    }

    public function test_bulk_delete_requires_the_media_delete_permission(): void
    {
        $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [['public_id' => 'general/file.jpg', 'resource_type' => 'image', 'source' => 'local']],
        ])->assertRedirect('/vi/admin/login');

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)
            ->delete('/vi/admin/media/bulk-delete', [
                'items' => [['public_id' => 'general/file.jpg', 'resource_type' => 'image', 'source' => 'local']],
            ])
            ->assertForbidden();

        $this->actingWith(['media.view']);
        $this->delete('/vi/admin/media/bulk-delete', [
            'items' => [['public_id' => 'general/file.jpg', 'resource_type' => 'image', 'source' => 'local']],
        ])->assertForbidden();
    }

    public function test_bulk_delete_button_is_hidden_without_the_media_delete_permission(): void
    {
        Storage::disk('public')->put('general/view-only.jpg', 'content');
        $this->actingWith(['media.view']);

        $this->get('/vi/admin/media?folder=general')
            ->assertOk()
            ->assertDontSee('id="mediaBulkDeleteBtn"', false)
            ->assertDontSee('class="form-check-input media-select-checkbox"', false);
    }

    private function actingWith(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Media bulk '.md5(implode(',', $permissions).microtime(true)),
            'permissions' => $permissions,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);

        return $user;
    }
}
