<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL redirects (301/302/307/410) and the log of storefront URLs that answered 404, so a site
 * moved from WordPress keeps the rankings of its old addresses. Redirects are looked up only
 * when nothing else answers a URL (App\Services\Seo\RedirectService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('redirects')) {
            Schema::create('redirects', function (Blueprint $table) {
                $table->id();
                // Normalised: decoded, lower case, leading slash, no trailing slash.
                $table->string('source_path', 500)->unique();
                // Where to go when the record below is gone or has no storefront URL.
                $table->string('target_url', 2048)->nullable();
                // The record the old URL now lives at: the target follows its slug.
                $table->nullableMorphs('redirectable');
                $table->unsignedSmallInteger('status_code')->default(301);
                $table->string('origin', 20)->default('manual');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('hits')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('not_found_logs')) {
            Schema::create('not_found_logs', function (Blueprint $table) {
                $table->id();
                $table->string('path', 500)->unique();
                $table->unsignedInteger('hits')->default(1);
                $table->string('referer', 500)->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
    }
};
