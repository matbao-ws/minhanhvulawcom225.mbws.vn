<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides for individual static regions of a storefront template.
 *
 * A row exists only after an admin edits that region: the Blade slot is the
 * default, so a fresh install renders the approved design with nothing seeded.
 * The key carries the location and the template carries the layout — no
 * page_id, no nesting, no ordering, or this becomes a second page builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded rather than plain create: an earlier build of this feature
        // shipped the same two tables, so installations upgraded from it
        // already hold live rows. Recreating them would fail, and dropping them
        // would throw away real content.
        if (! Schema::hasTable('site_blocks')) {
            Schema::create('site_blocks', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('type', 20)->default('text');
                // Translatable: the storefront renders one locale at a time and
                // saving one must leave the others untouched.
                $table->json('content');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_block_revisions')) {
            Schema::create('site_block_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_block_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('content');
                // A revision is a snapshot; it is never updated.
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    /**
     * Deliberately empty.
     *
     * These tables predate this migration on upgraded installations, so
     * dropping them on rollback would delete content this migration never
     * created. Removing the feature is a separate, explicit decision.
     */
    public function down(): void
    {
        //
    }
};
