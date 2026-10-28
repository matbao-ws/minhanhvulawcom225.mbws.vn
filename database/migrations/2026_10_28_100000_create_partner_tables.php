<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner / customer logos managed from the admin (Đối tác), grouped by a key the theme asks for
 * ("customers", "strategic"). Read on the storefront through App\Services\Partners\PartnerQueryService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_groups')) {
            Schema::create('partner_groups', function (Blueprint $table) {
                $table->id();
                // What a theme asks for; never changes once created, or the theme loses the group.
                $table->string('key', 100)->unique();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                // restrict: deleting a group that still holds logos is a mistake, refused in the admin
                // with a message and, as a backstop, by the database.
                $table->foreignId('partner_group_id')->constrained('partner_groups')->restrictOnDelete();
                $table->string('name');
                // Root-relative for this site's own media (App\Support\MediaUrl).
                $table->string('logo_path', 2048)->nullable();
                $table->string('url', 2048)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['partner_group_id', 'is_active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
        Schema::dropIfExists('partner_groups');
    }
};
