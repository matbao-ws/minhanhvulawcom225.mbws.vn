<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\FeatureSetting;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue(Addon::query()->exists());
        $this->assertSame(0, Product::query()->count());
        $this->assertTrue(FeatureSetting::query()->where('feature_code', 'inventory_log')->value('is_enabled'));
    }
}
