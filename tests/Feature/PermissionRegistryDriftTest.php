<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Keeps the permission catalogue and its enforcement from drifting apart.
 *
 * `manage_roles` and `manage_languages` sat in the catalogue for releases while
 * no route ever asked for them: the role form offered switches that granted
 * nothing. These assertions make that failure mode impossible to reintroduce
 * silently, in either direction.
 */
class PermissionRegistryDriftTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Abilities demanded by `can:` middleware on admin routes.
     *
     * @return array<int, string>
     */
    private function abilitiesRequiredByRoutes(): array
    {
        $abilities = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'admin.')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                    continue;
                }

                foreach (explode(',', substr($middleware, 4)) as $ability) {
                    $abilities[] = trim($ability);
                }
            }
        }

        return array_values(array_unique($abilities));
    }

    public function test_every_ability_demanded_by_a_route_exists_in_the_registry(): void
    {
        $unknown = array_diff($this->abilitiesRequiredByRoutes(), PermissionRegistry::codes());

        $this->assertSame([], array_values($unknown), 'Route yêu cầu quyền chưa khai báo trong PermissionRegistry: '.implode(', ', $unknown));
    }

    public function test_every_registered_permission_is_enforced_by_a_route(): void
    {
        $unenforced = array_diff(PermissionRegistry::codes(), $this->abilitiesRequiredByRoutes());

        $this->assertSame([], array_values($unenforced), 'Quyền khai báo nhưng không route nào kiểm tra: '.implode(', ', $unenforced));
    }

    public function test_seeder_projects_the_registry_exactly(): void
    {
        (new \Database\Seeders\PermissionSeeder())->run();

        $this->assertEqualsCanonicalizing(
            PermissionRegistry::codes(),
            Permission::query()->pluck('code')->all(),
        );
    }

    public function test_sync_command_adds_missing_permissions_and_reports_stale_ones(): void
    {
        Permission::query()->create([
            'code' => 'ghosts.haunt',
            'name' => 'Quyền đã bị gỡ khỏi registry',
            'group' => 'system',
        ]);

        $this->artisan('permissions:sync')
            ->expectsOutputToContain('ghosts.haunt')
            ->assertSuccessful();

        // Reported but retained: pruning is opt-in because it cascades onto
        // every role that still holds the code.
        $this->assertDatabaseHas('permissions', ['code' => 'ghosts.haunt']);
        $this->assertDatabaseHas('permissions', ['code' => 'products.view']);

        $this->artisan('permissions:sync', ['--prune' => true])->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['code' => 'ghosts.haunt']);
        $this->assertEqualsCanonicalizing(
            PermissionRegistry::codes(),
            Permission::query()->pluck('code')->all(),
        );
    }

    public function test_legacy_codes_expand_only_to_codes_that_still_exist(): void
    {
        foreach (PermissionRegistry::legacyMap() as $legacy => $granular) {
            $this->assertNotSame([], $granular, "Legacy code {$legacy} không ánh xạ tới quyền nào.");

            foreach ($granular as $code) {
                $this->assertTrue(
                    PermissionRegistry::has($code),
                    "Legacy code {$legacy} ánh xạ tới quyền không tồn tại: {$code}.",
                );
            }
        }
    }
}
