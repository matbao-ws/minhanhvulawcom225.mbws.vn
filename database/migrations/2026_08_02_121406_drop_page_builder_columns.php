<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['schema_version', 'builder_data', 'published_css']);
        });

        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropColumn(['schema_version', 'builder_data', 'published_css']);
        });
    }

    /**
     * Restores the column structure only — the underlying builder/CSS data
     * from before the drop is not recoverable.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedInteger('schema_version')->default(1)->after('slug');
            $table->json('builder_data')->nullable()->after('schema_version');
            $table->json('published_css')->nullable()->after('published_html');
        });

        Schema::table('page_revisions', function (Blueprint $table) {
            $table->unsignedInteger('schema_version')->default(1)->after('created_by');
            $table->json('builder_data')->nullable()->after('schema_version');
            $table->json('published_css')->nullable()->after('published_html');
        });
    }
};
