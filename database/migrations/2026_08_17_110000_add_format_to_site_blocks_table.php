<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a storefront region a semantic wrapper chosen from the inline toolbar.
 *
 * Null means the tag authored in the Blade template still wins, which is the
 * whole point: a region only stops following the theme once an editor
 * deliberately makes it a heading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_blocks', function (Blueprint $table): void {
            $table->string('format', 8)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('site_blocks', function (Blueprint $table): void {
            $table->dropColumn('format');
        });
    }
};
