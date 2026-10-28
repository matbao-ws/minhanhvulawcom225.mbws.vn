<?php

namespace App\Services\Partners;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single place the storefront reads partner / customer logos (admin → Đối tác).
 *
 * Blade must not call this application's own public API, so a theme and
 * `GET /api/public/partners?group=<key>` both come through here: adding a filter or an eager load
 * in only one of them is the drift this service exists to prevent.
 *
 * In a theme, loop over `app(\App\Services\Partners\PartnerQueryService::class)->forGroup('customers')`
 * and print `<img src="{{ $partner->logo_path }}" alt="{{ $partner->name }}">` for each partner,
 * wrapped in `<a href="{{ $partner->url }}">` when it has a url. `logo_path` reads as an absolute
 * URL (App\Support\MediaUrl); the database keeps it root-relative. The group key is the one shown
 * in admin → Đối tác → Nhóm đối tác.
 *
 * An unknown or empty group gives an empty collection, never an error: render nothing for it.
 * The logos are database content with their own admin screen, so they are never marked as
 * inline-editable regions on the storefront.
 */
class PartnerQueryService
{
    /** @var array<string, Collection<int, Partner>> results already read by this instance */
    private array $groups = [];

    /**
     * Active partners of a group, ordered by sort_order then id, with their group loaded.
     *
     * @return Collection<int, Partner>
     */
    public function forGroup(string $key): Collection
    {
        return $this->groups[$key] ??= Partner::query()
            ->whereHas('group', fn ($query) => $query->where('key', $key))
            ->where('is_active', true)
            ->with('group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
