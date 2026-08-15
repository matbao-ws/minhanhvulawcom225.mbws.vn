<?php

namespace Database\Seeders\Concerns;

use App\Models\LocalizedSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * `truncate()` bypasses model events, so the `deleting` hook in
 * HasLocalizedSlugs never runs and every localized slug survives its record.
 * Because truncating also resets the auto-increment, the surviving rows then
 * re-attach themselves to whatever new record happens to take the same id — a
 * slug silently pointing at the wrong post. Seeders that truncate must clear
 * the slugs explicitly.
 */
trait ClearsLocalizedSlugs
{
    /**
     * @param  array<int, class-string<Model>>  $models
     */
    protected function clearLocalizedSlugs(array $models): void
    {
        if (! Schema::hasTable('localized_slugs')) {
            return;
        }

        foreach ($models as $model) {
            LocalizedSlug::query()
                ->where('sluggable_type', (new $model())->getMorphClass())
                ->delete();
        }
    }
}
