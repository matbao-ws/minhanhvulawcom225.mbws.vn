<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerGroupRequest;
use App\Models\PartnerGroup;
use App\Services\ActivityLogger;
use App\Services\Partners\PartnerService;

/**
 * Groups of partner logos (admin → Đối tác → Nhóm). A theme asks for a group by its key, so the
 * key is set on create and never edited, and a group that still holds logos cannot be deleted.
 */
class PartnerGroupController extends Controller
{
    public function __construct(private readonly PartnerService $partners) {}

    public function index()
    {
        $groups = PartnerGroup::query()->withCount('partners')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.partner-groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.partner-groups.create', [
            'group' => new PartnerGroup,
        ]);
    }

    public function store(PartnerGroupRequest $request)
    {
        $group = $this->partners->createGroup($request->validated());
        ActivityLogger::log('created', $group, __('partners.groups.log.created', ['name' => $group->name]), [
            'new' => $group->only(['key', 'name', 'sort_order']),
        ]);

        return redirect()
            ->route('admin.partner-groups.index')
            ->with('success', __('partners.groups.messages.created', ['key' => $group->key]));
    }

    public function edit(string $locale, PartnerGroup $partnerGroup)
    {
        return view('admin.partner-groups.edit', [
            'group' => $partnerGroup,
        ]);
    }

    public function update(PartnerGroupRequest $request, string $locale, PartnerGroup $partnerGroup)
    {
        $old = $partnerGroup->only(['name', 'sort_order']);
        $group = $this->partners->updateGroup($partnerGroup, $request->validated());
        ActivityLogger::log('updated', $group, __('partners.groups.log.updated', ['name' => $group->name]), [
            'old' => $old,
            'new' => $group->only(['name', 'sort_order']),
        ]);

        return redirect()
            ->route('admin.partner-groups.index')
            ->with('success', __('partners.groups.messages.updated'));
    }

    public function destroy(string $locale, PartnerGroup $partnerGroup)
    {
        // The foreign key restricts too; this answers with a message instead of an error page.
        $count = $partnerGroup->partners()->count();
        if ($count > 0) {
            return back()->with('error', __('partners.groups.messages.delete_in_use', ['count' => $count]));
        }

        $partnerGroup->delete();
        ActivityLogger::log('deleted', $partnerGroup, __('partners.groups.log.deleted', ['name' => $partnerGroup->name]), [
            'old' => $partnerGroup->only(['key', 'name']),
        ]);

        return redirect()
            ->route('admin.partner-groups.index')
            ->with('success', __('partners.groups.messages.deleted'));
    }
}
