<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traffic statistics are part of every package; seeded enabled so installed
 * sites get the dashboard section on deploy (a missing row reads as "off").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_settings') || DB::table('feature_settings')->where('feature_code', 'analytics')->exists()) {
            return;
        }

        DB::table('feature_settings')->insert([
            'feature_code' => 'analytics',
            'is_enabled' => true,
            'limit_value' => null,
            'config' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('feature_settings')) {
            DB::table('feature_settings')->where('feature_code', 'analytics')->delete();
        }
    }
};
