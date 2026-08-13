<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping configuration gains a feature code of its own.
 *
 * Without a row here, FeatureGate reads a missing setting as "disabled" and the
 * shipping configuration would silently vanish from every already-installed
 * site the moment this deploys. It is therefore seeded as enabled, which is the
 * behaviour those sites have today; support can switch it off afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_settings')) {
            return;
        }

        // Never overwrite a choice an operator already made for this code.
        if (DB::table('feature_settings')->where('feature_code', 'shipping')->exists()) {
            return;
        }

        DB::table('feature_settings')->insert([
            'feature_code' => 'shipping',
            'is_enabled' => true,
            'limit_value' => null,
            'config' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('feature_settings')) {
            DB::table('feature_settings')->where('feature_code', 'shipping')->delete();
        }
    }
};
