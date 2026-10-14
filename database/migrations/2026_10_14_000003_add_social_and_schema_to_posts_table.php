<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a post shows when shared (Facebook, Zalo, X) when it should differ from its Google
 * snippet, and the schema.org type it is published as (the post form's SEO box).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('og_title')->nullable()->after('seo_description');
            $table->json('og_description')->nullable()->after('og_title');
            $table->string('og_image', 2048)->nullable()->after('og_description');
            $table->string('schema_type', 30)->nullable()->after('og_image');
            $table->boolean('schema_faq')->default(true)->after('schema_type');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            foreach (['schema_faq', 'schema_type', 'og_image', 'og_description', 'og_title'] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
