<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI suggestions as this website received them. The gateway only proposes;
     * a suggestion changes the site when an admin applies it, and `old_values`
     * is what the record held when it was proposed, so a change made since then
     * is detected instead of overwritten.
     */
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gateway_suggestion_id');
            $table->string('task', 50);
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->string('locale', 10);
            $table->json('old_values');
            $table->json('options');
            $table->string('status', 20);
            $table->unsignedTinyInteger('applied_option')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
    }
};
