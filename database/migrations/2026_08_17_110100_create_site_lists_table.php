<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many boxes a repeatable storefront region holds, and in what order.
 *
 * This is deliberately not a page builder, and the difference is structural
 * rather than a matter of degree. The template still decides what a box looks
 * like and where the region sits; the only thing stored here is a list of ids.
 * An added box is rendered as a sibling with the authored tag and classes, so
 * it cannot introduce a layout the design never had — an editor can add a fifth
 * board member, not a new section.
 *
 * Each box's own content lives in `site_blocks` under
 * `<list key>.item_<id>.<slot>`, which is why the ids must be stable: removing
 * the second of four boxes must not shift the others onto each other's text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_lists');
    }
};
