<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature settings learn where their value came from.
 *
 * `package_enabled` is what the sold package says; `is_overridden` marks a
 * superadmin decision that the next package sync must leave alone.
 * `is_enabled` stays the only column FeatureGate reads.
 *
 * The three codes introduced here are seeded as enabled, the behaviour every
 * installed site has today; without a row FeatureGate would read them as off
 * and variants, VNPAY and multilingual would vanish on deploy.
 */
return new class extends Migration
{
    private const NEW_CODES = ['product_variant', 'payment_vnpay', 'multilingual'];

    public function up(): void
    {
        if (! Schema::hasTable('feature_settings')) {
            return;
        }

        Schema::table('feature_settings', function (Blueprint $table) {
            $table->boolean('package_enabled')->nullable()->after('is_enabled');
            $table->boolean('is_overridden')->default(false)->after('package_enabled');
        });

        foreach (self::NEW_CODES as $code) {
            if (DB::table('feature_settings')->where('feature_code', $code)->exists()) {
                continue;
            }

            DB::table('feature_settings')->insert([
                'feature_code' => $code,
                'is_enabled' => true,
                'limit_value' => null,
                'config' => null,
                'updated_at' => now(),
            ]);
        }

        Schema::create('package_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 50);
            // YYYY-MM for monthly metrics.
            $table->string('period', 20);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_usage_counters');

        if (! Schema::hasTable('feature_settings')) {
            return;
        }

        DB::table('feature_settings')->whereIn('feature_code', self::NEW_CODES)->delete();

        Schema::table('feature_settings', function (Blueprint $table) {
            $table->dropColumn(['package_enabled', 'is_overridden']);
        });
    }
};
