<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per checkout sent with an Idempotency-Key. A retried checkout (double
 * click, network timeout) finds its row and gets the same order back instead of
 * a second one. Scope keeps a signed-in customer's keys apart from guests'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 100);
            $table->string('idempotency_key', 64);
            $table->char('fingerprint', 64);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key'], 'checkout_requests_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_requests');
    }
};
