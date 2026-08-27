<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_creates_only_core_data_and_not_demo_catalog_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('feature_settings', [
            'feature_code' => 'catalog',
            'is_enabled' => true,
        ]);
        $this->assertSame(0, Product::query()->count());
        $this->assertTrue(FeatureSetting::query()->where('feature_code', 'inventory_log')->value('is_enabled'));
    }

    public function test_seed_creates_an_active_superadmin_on_a_fresh_install(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = $this->seededAdmin();
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->role->is_superadmin);
        $this->assertNotEmpty($admin->password);
    }

    public function test_reseeding_never_touches_an_existing_superadmin(): void
    {
        // Re-running the seed on a live shop must not hand the account back to
        // whatever ADMIN_PASSWORD currently holds — usually a stale value from some
        // developer's .env — because that locks the operator out of their own admin.
        $this->seed(DatabaseSeeder::class);

        $admin = $this->seededAdmin();
        $admin->update(['password' => 'changed-by-the-owner', 'name' => 'Nguyen Van A']);

        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('changed-by-the-owner', $admin->password));
        $this->assertSame('Nguyen Van A', $admin->name);
        $this->assertSame(1, User::query()->where('email', $admin->email)->count());
    }

    public function test_reseeding_does_not_reactivate_a_deactivated_superadmin(): void
    {
        // Deactivating the default account after creating another superadmin is a
        // deliberate act; a repair seed silently switching it back on would hand a
        // dormant login back to whoever still knows its password.
        $this->seed(DatabaseSeeder::class);

        $admin = $this->seededAdmin();
        $admin->update(['is_active' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse($admin->refresh()->is_active);
    }

    private function seededAdmin(): User
    {
        // The seeder reads ADMIN_EMAIL through env(), which Laravel resolves
        // immutably from .env at boot — so the test follows the same source rather
        // than trying to override it.
        return User::query()
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->firstOrFail();
    }
}
