<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The Viettel Post logo is now a PNG and the SVG is gone from
 * public/admin-assets/images/logo-vanchuyen. Rows still pointing at the SVG
 * would render no logo; a logo an admin changed is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipping_partners')
            ->where('partner_code', 'DTGH000016')
            ->where('logo_url', 'Viettel_Post_logo.svg')
            ->update(['logo_url' => 'Viettel_Post_logo.png']);
    }

    public function down(): void
    {
        DB::table('shipping_partners')
            ->where('partner_code', 'DTGH000016')
            ->where('logo_url', 'Viettel_Post_logo.png')
            ->update(['logo_url' => 'Viettel_Post_logo.svg']);
    }
};
