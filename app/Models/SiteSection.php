<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An editor's departures from how the template renders one section.
 *
 * Keyed `<list key>.<section name>` — `home.sections.hero`. The row is the
 * override: no row means the section renders exactly as the design does, which
 * is why nothing is ever seeded here.
 *
 * `settings` holds tokens, not CSS classes. {@see \App\Support\SectionRegistry}
 * says which tokens a given section accepts and what class each one maps to.
 */
class SiteSection extends Model
{
    protected $fillable = [
        'key',
        'variant',
        'settings',
        'is_visible',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_visible' => 'boolean',
    ];

    /**
     * The storage key for one section of one list.
     *
     * Built here rather than at each call site: the section name and the list key
     * are joined with a dot, and a name carrying one would address a different
     * section's storage. `SiteListService::isValidId()` is what keeps that from
     * happening, and it rejects dots.
     */
    public static function keyFor(string $listKey, string $section): string
    {
        return $listKey.'.'.$section;
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SiteSectionRevision::class)->latest('created_at');
    }
}
