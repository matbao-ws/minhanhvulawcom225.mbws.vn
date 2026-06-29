<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('method_code')->unique();
            $table->string('name');
            $table->string('account_name')->nullable();
            $table->string('type')->default('custom'); // 'connected' or 'custom'
            $table->string('status')->default('inactive'); // 'active' or 'inactive'
            $table->json('settings')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        // Seed default COD payment method
        DB::table('payment_methods')->insert([
            'method_code' => 'cod',
            'name' => 'Thanh toán khi nhận hàng (COD)',
            'account_name' => 'Cash on Delivery',
            'type' => 'custom',
            'status' => 'active',
            'settings' => json_encode([
                'description' => 'Thanh toán bằng tiền mặt khi nhận hàng.'
            ]),
            'logo_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed default Bank Transfer payment method
        DB::table('payment_methods')->insert([
            'method_code' => 'bank_transfer',
            'name' => 'Chuyển khoản ngân hàng',
            'account_name' => null,
            'type' => 'custom',
            'status' => 'inactive',
            'settings' => json_encode([
                'bank_name' => '',
                'account_number' => '',
                'account_holder' => '',
                'instructions' => 'Vui lòng chuyển khoản đúng số tiền và ghi rõ mã đơn hàng làm nội dung chuyển khoản.'
            ]),
            'logo_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);



        // Seed Sepay
        DB::table('payment_methods')->insert([
            'method_code' => 'sepay',
            'name' => 'Cổng thanh toán tự động Sepay',
            'account_name' => null,
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => json_encode([
                'api_key' => '',
                'webhook_token' => ''
            ]),
            'logo_url' => 'sepay-logo.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed Stripe
        DB::table('payment_methods')->insert([
            'method_code' => 'stripe',
            'name' => 'Cổng thanh toán quốc tế Stripe',
            'account_name' => null,
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => json_encode([
                'publishable_key' => '',
                'secret_key' => '',
                'webhook_secret' => ''
            ]),
            'logo_url' => 'stripe-logo.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
