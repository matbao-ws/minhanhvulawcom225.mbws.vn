<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Mail now goes through the MatBao relay on the gateway. The previous release
 * fetched the shared SMTP password from the SMTP export and kept a "last good"
 * copy in the cache forever; drop it so the password does not stay on the site.
 * Runs on deploy because deploys always migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['mail_settings.remote', 'mail_settings.remote_last_good'] as $key) {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // A cache store that is not reachable during deploy holds nothing to purge here.
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: the password must not come back.
    }
};
