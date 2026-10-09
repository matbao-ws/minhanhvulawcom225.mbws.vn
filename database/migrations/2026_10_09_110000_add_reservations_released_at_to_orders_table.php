<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * When a cancelled order handed back its voucher use and promotion quota
 * (OrderCancellationService). Carrier webhooks repeat, so the release has to
 * know it already ran; stock has its own idempotent ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reservations_released_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reservations_released_at');
        });
    }
};
