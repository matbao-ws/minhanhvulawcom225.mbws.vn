<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bulk AI job started from this admin. `items` keeps, per record, what the
 * meta held when the job started: applying a result checks it is still so.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_bulk_jobs')) {
            return;
        }

        Schema::create('ai_bulk_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gateway_job_id')->unique();
            $table->string('type', 40);
            $table->string('locale', 10);
            // queued | running | completed | stopped, as the gateway last said
            $table->string('status', 20);
            $table->string('stop_reason', 40)->nullable();
            $table->unsignedInteger('items_total');
            $table->unsignedInteger('credits_estimated');
            $table->json('items');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Results copied into ai_suggestions, ready to review.
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bulk_jobs');
    }
};
