<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Text alignment an editor chose for a storefront region from the inline toolbar, stored as a
 * token (left, center, right, justify) the client stylesheet maps to a class. Null keeps the
 * alignment the theme authored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_blocks', function (Blueprint $table): void {
            $table->string('align', 8)->nullable()->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('site_blocks', function (Blueprint $table): void {
            $table->dropColumn('align');
        });
    }
};
