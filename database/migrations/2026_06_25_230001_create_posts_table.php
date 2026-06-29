<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('summary')->nullable();
            $table->json('content');
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->string('seo_keys')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
