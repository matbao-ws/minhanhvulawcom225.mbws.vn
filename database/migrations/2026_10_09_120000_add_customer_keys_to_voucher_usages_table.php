<?php

use App\Models\Voucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Per-customer voucher limits were keyed on the raw email a guest typed, so
 * a+1@gmail.com, a+2@gmail.com… each counted as a new customer, and a member
 * could drop their token to check out as a "new" guest. Usages now carry the
 * normalised email and phone (Voucher::emailKey / phoneKey); existing rows are
 * backfilled from their own email and their order's phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_usages', function (Blueprint $table) {
            $table->string('customer_email_key')->nullable()->index();
            $table->string('customer_phone_key', 32)->nullable()->index();
        });

        DB::table('voucher_usages')
            ->leftJoin('orders', 'orders.id', '=', 'voucher_usages.order_id')
            ->select(['voucher_usages.id', 'voucher_usages.customer_email', 'orders.customer_phone'])
            ->orderBy('voucher_usages.id')
            ->each(function (object $row): void {
                DB::table('voucher_usages')->where('id', $row->id)->update([
                    'customer_email_key' => Voucher::emailKey($row->customer_email),
                    'customer_phone_key' => Voucher::phoneKey($row->customer_phone),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('voucher_usages', function (Blueprint $table) {
            $table->dropIndex(['customer_email_key']);
            $table->dropIndex(['customer_phone_key']);
            $table->dropColumn(['customer_email_key', 'customer_phone_key']);
        });
    }
};
