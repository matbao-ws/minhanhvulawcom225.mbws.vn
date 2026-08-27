<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the twelve foreign keys the schema was relying on the application layer for.
 *
 * Only 27 of 59 tables carried referential constraints, so deleting a parent row
 * anywhere else left orphans with nothing to stop it. Every column below is already
 * indexed and its nullability already matches the rule applied here, so no column
 * definition changes — this adds constraints only.
 *
 * Portability: SQLite (used by the test suite) cannot ALTER a table to add a
 * constraint, so Laravel rebuilds the table instead. That path requires dropping
 * foreign keys by column array rather than by constraint name, which is why down()
 * passes arrays.
 */
return new class extends Migration
{
    /**
     * table => [[column, referenced table, on-delete rule], ...]
     */
    private const FOREIGN_KEYS = [
        // Orphaned children are unreachable data: an order line with no order, a
        // variant with no product, an address with no owner, a review with no product.
        'order_items' => [
            ['order_id', 'orders', 'cascade'],
            // The line already snapshots product_name, sku and price, so it stays
            // readable after the catalogue moves on — only the link is cut.
            ['product_id', 'products', 'set null'],
            ['product_variant_id', 'product_variants', 'set null'],
        ],
        'product_variants' => [
            ['product_id', 'products', 'cascade'],
        ],
        'user_addresses' => [
            ['user_id', 'users', 'cascade'],
        ],
        'reviews' => [
            ['product_id', 'products', 'cascade'],
            // Guest reviews already store null here.
            ['user_id', 'users', 'set null'],
        ],
        'products' => [
            // Deleting a category that still holds products is a mistake, not an
            // instruction; the install ships an "Uncategorized" category to move them to.
            ['category_id', 'categories', 'restrict'],
            ['brand_id', 'brands', 'set null'],
        ],
        'posts' => [
            ['category_id', 'post_categories', 'set null'],
        ],
        'post_categories' => [
            // Mirrors categories.parent_id, which already has this constraint.
            ['parent_id', 'post_categories', 'set null'],
        ],
        'users' => [
            // Losing a role would silently strip every permission the account had.
            ['role_id', 'roles', 'restrict'],
        ],
    ];

    public function up(): void
    {
        $this->deleteOrphans();

        foreach (self::FOREIGN_KEYS as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($definitions) {
                foreach ($definitions as [$column, $references, $onDelete]) {
                    $blueprint->foreign($column)
                        ->references('id')
                        ->on($references)
                        ->onDelete($onDelete);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::FOREIGN_KEYS, true) as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($definitions) {
                foreach ($definitions as [$column]) {
                    // By column, not by name: SQLite refuses to drop a foreign key by
                    // constraint name, and the array form works on both drivers.
                    $blueprint->dropForeign([$column]);
                }
            });
        }
    }

    /**
     * A constraint cannot be added while a row already violates it.
     *
     * Rows whose parent is gone are cleaned first, and the choice is driven by the
     * column's own nullability — never by the delete rule. A nullable column is
     * blanked, which keeps the row: a product pointing at a deleted category is still
     * a product, and a user pointing at a deleted role is still an account. Only a
     * NOT NULL column leaves no option but to drop the row, and there the row was
     * already unusable — an address nobody owns cannot be shown, edited or shipped to.
     */
    private function deleteOrphans(): void
    {
        foreach (self::FOREIGN_KEYS as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $nullableColumns = [];
            foreach (Schema::getColumns($table) as $column) {
                $nullableColumns[$column['name']] = (bool) $column['nullable'];
            }

            foreach ($definitions as [$column, $references]) {
                if (! Schema::hasTable($references)) {
                    continue;
                }

                $orphans = DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotIn($column, fn ($query) => $query->select('id')->from($references));

                $nullable = $nullableColumns[$column] ?? false;

                $affected = $nullable
                    ? (clone $orphans)->update([$column => null])
                    : (clone $orphans)->delete();

                if ($affected > 0) {
                    $action = $nullable ? 'blanked' : 'deleted';
                    echo "  {$table}.{$column}: {$action} {$affected} orphaned row(s)\n";
                }
            }
        }
    }
};
