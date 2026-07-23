<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('carrier_shipping_fee', 15, 2)->nullable()->after('shipping_fee');
        });

        Schema::create('shipping_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('fingerprint', 64)->unique();
            $table->json('payload');
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_events');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('carrier_shipping_fee');
        });
    }
};
