<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Carrier address codes chosen at checkout, e.g. {"ghn": {"province_id": 202, "district_id": 1442,
 * "ward_code": "20109"}}: a GHN shipment needs them, and the free-text address cannot give them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('shipping_location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_location');
        });
    }
};
