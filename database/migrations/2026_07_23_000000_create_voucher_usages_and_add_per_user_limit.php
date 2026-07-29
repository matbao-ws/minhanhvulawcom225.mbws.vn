<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vouchers', 'per_user_limit')) {
            Schema::table('vouchers', function (Blueprint $table) {
                // null = không giới hạn số lần dùng trên mỗi khách hàng
                $table->unsignedInteger('per_user_limit')->nullable()->after('quantity');
            });
        }

        if (! Schema::hasTable('voucher_usages')) {
            Schema::create('voucher_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('customer_email')->nullable();
                $table->timestamp('used_at')->useCurrent();
                $table->timestamps();

                $table->index(['voucher_id', 'user_id']);
                $table->index(['voucher_id', 'customer_email']);
            });
        } elseif (! Schema::hasColumn('voucher_usages', 'used_at')) {
            Schema::table('voucher_usages', function (Blueprint $table) {
                $table->timestamp('used_at')->nullable()->after('customer_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usages');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('per_user_limit');
        });
    }
};
