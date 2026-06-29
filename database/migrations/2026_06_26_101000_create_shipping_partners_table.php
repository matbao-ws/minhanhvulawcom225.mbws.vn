<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_partners', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code')->unique();
            $table->string('name');
            $table->string('account_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('type')->default('custom'); // 'connected' or 'custom'
            $table->string('status')->default('inactive'); // 'active' or 'inactive'
            $table->json('settings')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        // Migrate existing shipping configurations
        try {
            $oldSetting = DB::table('project_settings')
                ->where('setting_key', 'shipping')
                ->first();

            $oldValue = $oldSetting ? json_decode($oldSetting->setting_value, true) : null;
        } catch (\Exception $e) {
            $oldValue = null;
        }

        $ghtkEnabled = data_get($oldValue, 'ghtk.enabled', false);
        $ghtkApiToken = data_get($oldValue, 'ghtk.api_token', '');
        $ghtkApiUrl = data_get($oldValue, 'ghtk.api_url', 'https://services.giaohangtietkiem.vn');
        $ghtkName = data_get($oldValue, 'ghtk.name', 'Giao Hàng Tiết Kiệm (GHTK)');
        $ghtkWebhookToken = data_get($oldValue, 'ghtk.webhook_token', Str::random(32));

        $flatRateEnabled = data_get($oldValue, 'flat_rate.enabled', false);
        $flatRateFee = data_get($oldValue, 'flat_rate.fee', 30000);
        $flatRateName = data_get($oldValue, 'flat_rate.name', 'Giao hàng nhanh đồng giá');

        // Insert Giao Hàng Tiết Kiệm (GHTK)
        DB::table('shipping_partners')->insert([
            'partner_code' => 'DTGH000012',
            'name' => $ghtkName,
            'account_name' => $ghtkApiToken ? 'GHTK Account' : null,
            'phone' => null,
            'type' => 'connected',
            'status' => $ghtkEnabled ? 'active' : 'inactive',
            'settings' => json_encode([
                'api_token' => $ghtkApiToken,
                'api_url' => $ghtkApiUrl,
                'webhook_token' => $ghtkWebhookToken,
            ]),
            'logo_url' => 'Logo-GHTK.webp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert Giao Hàng Nhanh (GHN)
        DB::table('shipping_partners')->insert([
            'partner_code' => 'DTGH000013',
            'name' => 'Giao Hàng Nhanh (GHN)',
            'account_name' => null,
            'phone' => null,
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => json_encode([
                'api_token' => '',
                'api_url' => 'https://dev-online-gateway.ghn.vn',
                'client_id' => '',
                'shop_id' => '',
            ]),
            'logo_url' => 'logo-giao-hang-nhanh.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert J&T Express
        DB::table('shipping_partners')->insert([
            'partner_code' => 'DTGH000014',
            'name' => 'J&T Express',
            'account_name' => null,
            'phone' => null,
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => json_encode([
                'eccompanyid' => '',
                'key' => '',
                'customerid' => '',
            ]),
            'logo_url' => 'J&TExpress.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert SPX Express
        DB::table('shipping_partners')->insert([
            'partner_code' => 'DTGH000015',
            'name' => 'SPX Express',
            'account_name' => null,
            'phone' => null,
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => json_encode([
                'api_token' => '',
                'api_url' => 'https://api.spx.vn',
                'partner_id' => '',
            ]),
            'logo_url' => 'SPXEXPRESS.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert Viettel Post
        DB::table('shipping_partners')->insert([
            'partner_code' => 'DTGH000016',
            'name' => 'Viettel Post',
            'account_name' => null,
            'phone' => null,
            'type' => 'connected',
            'status' => 'inactive',
            'settings' => json_encode([
                'api_token' => '',
                'api_url' => 'https://partner.viettelpost.vn',
                'username' => '',
            ]),
            'logo_url' => 'Viettel_Post_logo.svg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert Tự giao / Phí cố định
        DB::table('shipping_partners')->insert([
            'partner_code' => 'DTGHTUGIAO',
            'name' => $flatRateName,
            'account_name' => null,
            'phone' => null,
            'type' => 'custom',
            'status' => $flatRateEnabled ? 'active' : 'inactive',
            'settings' => json_encode([
                'fee' => $flatRateFee,
            ]),
            'logo_url' => 'self_delivery.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_partners');
    }
};
