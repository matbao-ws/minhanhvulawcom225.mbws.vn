<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_settings', function (Blueprint $table) {
            $table->id();
            $table->string('feature_code')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->string('limit_value')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_settings');
    }
};
