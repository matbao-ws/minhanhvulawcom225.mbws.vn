<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Contact form spam filter: a flagged submission is kept (an admin can release a false positive)
 * but sends no mail and stays out of the inbox. The sender's IP helps spot a flood.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->boolean('is_spam')->default(false)->index();
            $table->string('spam_reason', 30)->nullable();
            $table->string('ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropIndex(['is_spam']);
            $table->dropColumn(['is_spam', 'spam_reason', 'ip']);
        });
    }
};
