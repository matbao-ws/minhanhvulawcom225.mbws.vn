<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('action', 32); // sale, refund, cancellation, adjustment
            $table->string('direction', 8); // in, out
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('product_stock_after')->nullable();
            $table->unsignedInteger('variant_stock_after')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
            $table->index(['order_id', 'created_at']);
            $table->index(['order_item_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
