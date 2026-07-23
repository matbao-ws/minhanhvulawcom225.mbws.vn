<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_status')->default('not_shipped')->after('tracking_number');
            $table->timestamp('shipping_status_updated_at')->nullable()->after('shipping_status');
        });

        DB::table('orders')
            ->whereNotNull('tracking_number')
            ->update([
                'shipping_status' => 'shipping_created',
                'shipping_status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_status', 'shipping_status_updated_at']);
        });
    }
};
