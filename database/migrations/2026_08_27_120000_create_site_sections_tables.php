<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How one section of a storefront template looks, when an editor departed from
 * the design's own answer.
 *
 * A row exists only after somebody changed something, so a fresh install renders
 * the approved design with nothing seeded — the same contract `site_blocks` and
 * `site_lists` follow.
 *
 * No `page_id`, no parent, no ordering. The key carries the location
 * (`<list key>.<section name>`) and the template carries the layout; ordering is
 * already `site_lists`'s job. Adding structure here is how this would slide into
 * being the page builder this core deliberately does not have: what an editor can
 * change is which of the shapes the section already knows how to render it uses,
 * never where the section sits or what elements it contains.
 *
 * Values are tokens (`primary`, `center`, `lg`), never CSS classes.
 * `App\Support\SectionRegistry` owns the token → class mapping, so repainting a
 * token is a stylesheet change rather than an UPDATE across every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('variant', 40)->nullable();
            $table->json('settings');
            // Hiding a section, mirroring the empty string that hides a region in
            // `site_blocks`. Reversible and still listed in the outline panel, or
            // it would be unreachable once hidden.
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('site_section_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('variant', 40)->nullable();
            $table->json('settings');
            $table->boolean('is_visible')->default(true);
            // A revision is a snapshot; it is never updated.
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Safe to drop, unlike `site_blocks`.
     *
     * These two tables are introduced here and have never existed in an earlier
     * build, so a rollback cannot destroy content this migration did not create.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_section_revisions');
        Schema::dropIfExists('site_sections');
    }
};
