<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('post_categories', 'parent_id')) {
            Schema::table('post_categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('post_categories')->nullOnDelete();
                
                $table->index(['parent_id', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
