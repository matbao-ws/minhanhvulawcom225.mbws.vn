<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory_log feature switched nothing: stock movements are always recorded and no screen
 * shows them. Its rows go, so the features page and package sync no longer offer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feature_settings')) {
            DB::table('feature_settings')->where('feature_code', 'inventory_log')->delete();
        }
        if (Schema::hasTable('features')) {
            DB::table('features')->where('code', 'inventory_log')->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('features') && ! DB::table('features')->where('code', 'inventory_log')->exists()) {
            DB::table('features')->insert(['code' => 'inventory_log', 'name' => 'Inventory Log', 'value_type' => 'boolean', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (Schema::hasTable('feature_settings') && ! DB::table('feature_settings')->where('feature_code', 'inventory_log')->exists()) {
            DB::table('feature_settings')->insert(['feature_code' => 'inventory_log', 'is_enabled' => false, 'limit_value' => null, 'config' => null, 'updated_at' => now()]);
        }
    }
};
