<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product categories and blog categories get the SEO box (admin.shared.seo-box) of posts,
 * products and pages; blog categories also get the SEO title and description they lacked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('seo_keys', 255)->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->json('og_title')->nullable();
            $table->json('og_description')->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->boolean('schema_faq')->default(true);
        });

        Schema::table('post_categories', function (Blueprint $table) {
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('seo_keys', 255)->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->json('og_title')->nullable();
            $table->json('og_description')->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->boolean('schema_faq')->default(true);
        });
    }

    public function down(): void
    {
        $columns = ['seo_keys', 'seo_score', 'canonical_url', 'robots_index', 'robots_follow', 'og_title', 'og_description', 'og_image', 'schema_faq'];
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn($columns));
        Schema::table('post_categories', fn (Blueprint $table) => $table->dropColumn(['meta_title', 'meta_description', ...$columns]));
    }
};
