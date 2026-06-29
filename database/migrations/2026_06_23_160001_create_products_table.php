<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
