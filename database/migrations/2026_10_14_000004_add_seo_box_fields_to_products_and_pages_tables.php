<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products and policy pages get the post form's SEO box: focus keywords and their score,
 * canonical and robots choices, what they show when shared, and FAQ structured data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('seo_keys', 255)->nullable()->after('meta_description');
            $table->unsignedTinyInteger('seo_score')->nullable()->after('seo_keys');
            $table->string('canonical_url', 2048)->nullable()->after('seo_score');
            $table->boolean('robots_index')->default(true)->after('canonical_url');
            $table->boolean('robots_follow')->default(true)->after('robots_index');
            $table->json('og_title')->nullable()->after('robots_follow');
            $table->json('og_description')->nullable()->after('og_title');
            $table->string('og_image', 2048)->nullable()->after('og_description');
            $table->boolean('schema_faq')->default(true)->after('og_image');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('seo_keys', 255)->nullable()->after('meta_description');
            $table->unsignedTinyInteger('seo_score')->nullable()->after('seo_keys');
            $table->string('canonical_url', 2048)->nullable()->after('seo_score');
            $table->boolean('robots_index')->default(true)->after('canonical_url');
            $table->boolean('robots_follow')->default(true)->after('robots_index');
            $table->json('og_title')->nullable()->after('robots_follow');
            $table->json('og_description')->nullable()->after('og_title');
            $table->string('og_image', 2048)->nullable()->after('og_description');
            $table->boolean('schema_faq')->default(true)->after('og_image');
        });
    }

    public function down(): void
    {
        $columns = ['seo_keys', 'seo_score', 'canonical_url', 'robots_index', 'robots_follow', 'og_title', 'og_description', 'og_image', 'schema_faq'];
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn($columns));
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn($columns));
    }
};
