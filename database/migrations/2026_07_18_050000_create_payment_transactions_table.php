<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payment_method', 100);
            $table->string('gateway', 100);
            $table->string('transaction_reference', 100);
            $table->string('gateway_transaction_id', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 32); // pending, paid, failed, refunded
            $table->string('response_code', 50)->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['gateway', 'gateway_transaction_id']);
            $table->index(['gateway', 'transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
