<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping weight of a product, in grams. A product without variants had nowhere to keep one (only
 * `product_variants.weight_grams` existed). On a product that has variants it is the default of all of
 * them: a variant with its own weight keeps it, the others inherit this one (see ShippingProfileResolver).
 * Nullable: null means "not entered", so carriers fall back to the shop's default parcel weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'weight_grams')) {
                $table->unsignedInteger('weight_grams')->nullable()->after('cost_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'weight_grams')) {
                $table->dropColumn('weight_grams');
            }
        });
    }
};
