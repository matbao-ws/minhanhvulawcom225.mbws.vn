<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            $table->index('sort_order');
        });

        // Seed the manual order from the current admin ordering (newest first) so
        // switching the listing to sort_order keeps the rows where admins expect them.
        $position = 0;
        DB::table('products')
            ->select('id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->chunk(200, function ($rows) use (&$position): void {
                foreach ($rows as $row) {
                    DB::table('products')->where('id', $row->id)->update(['sort_order' => $position++]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
