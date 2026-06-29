<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_carrier')->nullable()->after('payment_method');
            $table->decimal('shipping_fee', 15, 2)->default(0)->after('shipping_carrier');
            $table->string('tracking_number')->nullable()->after('shipping_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_carrier', 'shipping_fee', 'tracking_number']);
        });
    }
};
