<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('kind', 30)->default('automatic'); // automatic, flash_sale
            $table->string('applies_to', 30)->default('selected'); // all_products, selected
            $table->string('discount_type', 30); // percentage, fixed_amount, fixed_price
            $table->decimal('value', 15, 2);
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_stackable')->default(false);
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_at', 'end_at']);
            $table->index(['kind', 'priority']);
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamps();

            $table->index(['promotion_id', 'product_id']);
            $table->index('product_variant_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('promotion_discount', 15, 2)->default(0)->after('discount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
            $table->string('promotion_name')->nullable()->after('variant_name');
            $table->decimal('original_price', 15, 2)->nullable()->after('price');
            $table->decimal('promotion_discount', 15, 2)->default(0)->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_name', 'original_price', 'promotion_discount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('promotion_discount');
        });

        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
