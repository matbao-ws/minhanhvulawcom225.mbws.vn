<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The refund form's one-time key. A double click used to record the same
 * partial refund twice and restore its stock twice; the second submit of a
 * key now changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->string('request_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropColumn('request_key');
        });
    }
};
