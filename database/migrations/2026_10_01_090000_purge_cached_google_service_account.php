<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Google Analytics now goes through the MatBaoAI-SEO gateway. The previous
 * release fetched the shared service-account key from the SMTP export and kept
 * a "last good" copy in the cache forever; drop it so no key stays on the site.
 * Runs on deploy because deploys always migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['analytics.service_account', 'analytics.service_account_last_good', 'analytics.access_token'] as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // A cache store that is not reachable during deploy holds nothing to purge here.
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: the key must not come back.
    }
};
