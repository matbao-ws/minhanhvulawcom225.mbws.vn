<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('canonical_url', 2048)->nullable()->after('seo_keys');
            $table->boolean('robots_index')->default(true)->after('canonical_url');
            $table->boolean('robots_follow')->default(true)->after('robots_index');
            $table->unsignedTinyInteger('seo_score')->nullable()->after('robots_follow');
            $table->json('seo_analysis')->nullable()->after('seo_score');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'canonical_url',
                'robots_index',
                'robots_follow',
                'seo_score',
                'seo_analysis',
            ]);
        });
    }
};
