<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The constraints added by add_missing_foreign_keys must actually exist and bite.
 *
 * A green suite is not evidence on its own: SQLite cannot ALTER a table to add a
 * constraint, and Laravel's SQLite grammar compiles a plain `foreign()` into nothing
 * unless the table-rebuild path runs. These tests fail if the migration silently
 * becomes a no-op.
 */
class SchemaForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function expectedForeignKeys(): array
    {
        return [
            ['order_items', 'order_id', 'orders', 'cascade'],
            ['order_items', 'product_id', 'products', 'set null'],
            ['order_items', 'product_variant_id', 'product_variants', 'set null'],
            ['product_variants', 'product_id', 'products', 'cascade'],
            ['user_addresses', 'user_id', 'users', 'cascade'],
            ['reviews', 'product_id', 'products', 'cascade'],
            ['reviews', 'user_id', 'users', 'set null'],
            ['products', 'category_id', 'categories', 'restrict'],
            ['products', 'brand_id', 'brands', 'set null'],
            ['posts', 'category_id', 'post_categories', 'set null'],
            ['post_categories', 'parent_id', 'post_categories', 'set null'],
            ['users', 'role_id', 'roles', 'restrict'],
        ];
    }

    #[DataProvider('expectedForeignKeys')]
    public function test_every_declared_foreign_key_exists(string $table, string $column, string $references, string $onDelete): void
    {
        $match = null;
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                $match = $foreignKey;
                break;
            }
        }

        $this->assertNotNull($match, "{$table}.{$column} chưa có khoá ngoại");
        $this->assertSame($references, $match['foreign_table']);
        $this->assertSame(['id'], $match['foreign_columns']);
        $this->assertSame($onDelete, strtolower((string) $match['on_delete']));
    }

    public function test_foreign_key_enforcement_is_switched_on(): void
    {
        // On SQLite the constraints are inert unless the pragma is set; without this
        // the behaviour tests below would pass for the wrong reason.
        $this->assertTrue((bool) config('database.connections.sqlite.foreign_key_constraints'));
        $this->assertSame(1, (int) DB::scalar('pragma foreign_keys'));
    }

    public function test_deleting_an_order_removes_its_items(): void
    {
        $order = $this->makeOrder();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Test Product',
            'sku' => 'SKU-1',
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        $order->delete();

        $this->assertSame(0, OrderItem::query()->count());
    }

    public function test_deleting_a_customer_removes_their_addresses(): void
    {
        $user = User::factory()->create();
        UserAddress::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'Nguyen Van A',
            'customer_phone' => '0900000000',
            'address' => '1 Le Loi, Quan 1',
            'is_default' => true,
        ]);

        $user->delete();

        $this->assertSame(0, UserAddress::query()->count());
    }

    public function test_deleting_a_brand_only_unlinks_its_products(): void
    {
        $brand = Brand::query()->create(['name' => 'Apple', 'slug' => 'apple', 'is_active' => true]);
        $product = $this->makeProduct(['brand_id' => $brand->id]);

        $brand->delete();

        $product->refresh();
        $this->assertNull($product->brand_id);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'sku' => 'FK-TEST']);
    }

    public function test_deleting_a_category_that_still_holds_products_is_refused(): void
    {
        $category = Category::query()->create(['name' => 'Phones', 'slug' => 'phones', 'is_active' => true]);
        $this->makeProduct(['category_id' => $category->id]);

        $this->expectException(QueryException::class);
        $category->delete();
    }

    public function test_deleting_a_role_that_still_has_users_is_refused(): void
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => []]);
        User::factory()->create(['role_id' => $role->id]);

        $this->expectException(QueryException::class);
        $role->delete();
    }

    private function makeOrder(): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-FKTEST0001',
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'fk@example.com',
            'customer_phone' => '0900000000',
            'shipping_address' => 'Ho Chi Minh City',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal' => 1000,
            'discount' => 0,
            'grand_total' => 1000,
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => ['vi' => 'Test Product', 'en' => 'Test Product'],
            'slug' => 'test-product',
            'sku' => 'FK-TEST',
            'price' => 1000,
            'stock_quantity' => 1,
            'manage_stock' => true,
            'is_active' => true,
        ], $overrides));
    }
}
