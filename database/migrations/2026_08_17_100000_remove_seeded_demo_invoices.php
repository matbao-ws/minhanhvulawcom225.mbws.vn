<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the sample invoices that create_invoices_table seeded inside its up().
 *
 * `invoices` bills the vendor's own packages, not customer orders, so those three
 * rows appear in a brand new shop's admin as real money the customer supposedly
 * owes: "INV-2026-001 · Premium E-commerce Plan · 500.000 ₫ · paid". A deployed
 * migration is never edited in place, so the rows are removed going forward.
 *
 * Matched by the exact seeded invoice numbers: anything the shop created itself
 * carries a different number and must survive.
 */
return new class extends Migration
{
    private const SEEDED_INVOICE_NUMBERS = [
        'INV-2026-001',
        'INV-2026-002',
        'INV-2026-003',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        DB::table('invoices')
            ->whereIn('invoice_number', self::SEEDED_INVOICE_NUMBERS)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally empty. These rows were demo data that never described a real
        // transaction; recreating them would put fabricated billing back in front of
        // a live shop.
    }
};
