<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerRequest;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Services\ActivityLogger;
use App\Services\Partners\PartnerService;
use App\Support\SearchTerm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Partner / customer logos (admin → Đối tác). The storefront reads them through
 * App\Services\Partners\PartnerQueryService; they are never inline-editable there.
 */
class PartnerController extends Controller
{
    use HandlesBulkActions;
    use HandlesPagination;

    public function __construct(private readonly PartnerService $partners) {}

    public function index(Request $request)
    {
        $groups = PartnerGroup::query()->withCount('partners')->orderBy('sort_order')->orderBy('id')->get();
        $activeGroup = $request->filled('group') ? $groups->firstWhere('id', (int) $request->query('group')) : null;
        $keyword = SearchTerm::from($request->query('q')) ?? '';

        $query = Partner::query()->with('group');

        if ($activeGroup) {
            $query->where('partner_group_id', $activeGroup->id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status') === '1');
        }

        if ($keyword !== '') {
            $query->where('name', 'like', SearchTerm::contains($keyword));
        }

        // Dragging only means something inside one group's strip, with nothing hidden from it.
        $sortable = $activeGroup !== null && $keyword === '' && ! $request->filled('status');
        $isFiltered = $activeGroup !== null || $keyword !== '' || $request->filled('status');

        $partners = $query->orderBy('sort_order')->orderBy('id')
            ->paginate($this->perPage())
            ->withQueryString();

        return view('admin.partners.index', compact('partners', 'groups', 'activeGroup', 'sortable', 'isFiltered'));
    }

    public function create(Request $request)
    {
        $groups = $this->groups();
        if ($groups->isEmpty()) {
            return redirect()->route('admin.partner-groups.create')->with('error', __('partners.messages.no_group_yet'));
        }

        return view('admin.partners.create', [
            'partner' => new Partner([
                'is_active' => true,
                'partner_group_id' => $groups->firstWhere('id', (int) $request->query('group'))->id ?? $groups->first()->id,
            ]),
            'groups' => $groups,
        ]);
    }

    public function store(PartnerRequest $request)
    {
        $partner = $this->partners->create($request->validated());
        ActivityLogger::log('created', $partner, __('partners.log.created', ['name' => $partner->name]), [
            'new' => $partner->only(['name', 'partner_group_id', 'url', 'is_active']),
        ]);

        return redirect()
            ->route('admin.partners.index', ['group' => $partner->partner_group_id])
            ->with('success', __('partners.messages.created'));
    }

    public function edit(string $locale, Partner $partner)
    {
        return view('admin.partners.edit', [
            'partner' => $partner,
            'groups' => $this->groups(),
        ]);
    }

    public function update(PartnerRequest $request, string $locale, Partner $partner)
    {
        $old = $partner->only(['name', 'partner_group_id', 'url', 'is_active']);
        $partner = $this->partners->update($partner, $request->validated());
        ActivityLogger::log('updated', $partner, __('partners.log.updated', ['name' => $partner->name]), [
            'old' => $old,
            'new' => $partner->only(['name', 'partner_group_id', 'url', 'is_active']),
        ]);

        return redirect()
            ->route('admin.partners.index', ['group' => $partner->partner_group_id])
            ->with('success', __('partners.messages.updated'));
    }

    public function destroy(string $locale, Partner $partner)
    {
        $partner->delete();
        ActivityLogger::log('deleted', $partner, __('partners.log.deleted', ['name' => $partner->name]));

        return back()->with('success', __('partners.messages.deleted'));
    }

    /** Persist the order dragged inside one group's list. */
    public function sort(Request $request, string $locale)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:partners,id'],
            'start_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->partners->reorder($validated['ids'], (int) ($validated['start_order'] ?? 0));

        return response()->json(['message' => __('partners.messages.sorted')]);
    }

    public function bulk(Request $request, string $locale)
    {
        $validated = $this->validatedBulkAction($request, 'partners', 'partners');
        $ids = $validated['ids'];

        if ($validated['action'] === 'delete') {
            $deleted = Partner::query()->whereIn('id', $ids)->delete();
            ActivityLogger::log('bulk_deleted', null, __('partners.log.bulk_deleted'), [
                'model' => Partner::class,
                'ids' => $ids,
                'count' => $deleted,
            ]);

            return back()->with('success', __('partners.messages.bulk_deleted', ['count' => $deleted]));
        }

        $isActive = $validated['action'] === 'activate';
        $updated = Partner::query()->whereIn('id', $ids)->update(['is_active' => $isActive]);
        ActivityLogger::log('bulk_status_changed', null, __('partners.log.bulk_status'), [
            'model' => Partner::class,
            'ids' => $ids,
            'count' => $updated,
            'is_active' => $isActive,
        ]);

        return back()->with('success', __($isActive ? 'partners.messages.bulk_activated' : 'partners.messages.bulk_deactivated', ['count' => $updated]));
    }

    /** @return Collection<int, PartnerGroup> */
    private function groups(): Collection
    {
        return PartnerGroup::query()->orderBy('sort_order')->orderBy('id')->get();
    }
}
