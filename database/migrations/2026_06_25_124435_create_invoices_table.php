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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('package_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending'); // paid, pending, unpaid
            $table->date('billing_date');
            $table->date('due_date');
            $table->string('payment_method')->nullable();
            $table->string('addon_code')->nullable();
            $table->string('sepay_transaction_id')->nullable();
            $table->timestamps();
        });

        // Seed some initial mock billing invoices for the client!
        DB::table('invoices')->insert([
            [
                'invoice_number' => 'INV-2026-001',
                'package_name' => 'Premium E-commerce Plan',
                'amount' => 500000.00,
                'status' => 'paid',
                'billing_date' => '2026-04-22',
                'due_date' => '2026-05-22',
                'payment_method' => 'bank_transfer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'invoice_number' => 'INV-2026-002',
                'package_name' => 'Premium E-commerce Plan',
                'amount' => 500000.00,
                'status' => 'paid',
                'billing_date' => '2026-05-22',
                'due_date' => '2026-06-22',
                'payment_method' => 'bank_transfer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'invoice_number' => 'INV-2026-003',
                'package_name' => 'Premium E-commerce Plan',
                'amount' => 500000.00,
                'status' => 'pending',
                'billing_date' => '2026-06-22',
                'due_date' => '2026-07-22',
                'payment_method' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
