<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\ActivityLogger;
use App\Services\CloudinaryService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    use HandlesBulkActions;
    use HandlesPagination;

    public function __construct(private readonly CloudinaryService $cloudinaryService) {}

    /**
     * Display a listing of banners.
     */
    public function index(Request $request)
    {
        $query = Banner::query();

        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === '1');
        }

        $isFiltered = $request->filled('position') || $request->filled('status');
        $banners = $query->orderBy('sort_order', 'asc')->paginate($this->perPage(10))->withQueryString();

        return view('admin.banners.index', compact('banners', 'isFiltered'));
    }

    /**
     * Show the form for creating a new banner.
     */
    public function create()
    {
        return view('admin.banners.create');
    }

    /**
     * Store a newly created banner.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image_file' => 'nullable|image|max:2048',
            'image_url' => 'required_without:image_file|string|max:255',
            'link_url' => 'nullable|string|max:255',
            'position' => 'required|string|max:255',
        ]);

        $imagePath = $request->hasFile('image_file')
            ? $this->cloudinaryService->uploadFile($request->file('image_file'), 'banners')
            : $validated['image_url'];

        Banner::create([
            'title' => $validated['title'] ?? null,
            'image_path' => $imagePath,
            'link_url' => $validated['link_url'] ?? null,
            'position' => $validated['position'],
            'sort_order' => (Banner::query()->max('sort_order') ?? -1) + 1,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()
            ->route('admin.banners.index')
            ->with('success', __('admin.banners.messages.create_success'));
    }

    /**
     * Show the form for editing the specified banner.
     */
    public function edit(string $locale, Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    /**
     * Update the specified banner in storage.
     */
    public function update(Request $request, string $locale, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image_file' => 'nullable|image|max:2048',
            'image_url' => 'nullable|string|max:255',
            'link_url' => 'nullable|string|max:255',
            'position' => 'required|string|max:255',
        ]);

        $imagePath = $banner->image_path;

        if ($request->hasFile('image_file')) {
            // Delete old resource if it was local fallback
            if (! empty($banner->image_path)) {
                try {
                    $localPath = MediaUrl::publicDiskPath($banner->image_path);
                    if ($localPath !== null && Storage::disk('public')->exists($localPath)) {
                        Storage::disk('public')->delete($localPath);
                    }
                } catch (\Exception $e) {
                    // Ignore deletion error
                }
            }

            $imagePath = $this->cloudinaryService->uploadFile($request->file('image_file'), 'banners');
        } elseif (! empty($validated['image_url'])) {
            $imagePath = $validated['image_url'];
        }

        $banner->update([
            'title' => $validated['title'] ?? null,
            'image_path' => $imagePath,
            'link_url' => $validated['link_url'] ?? null,
            'position' => $validated['position'],
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()
            ->route('admin.banners.index')
            ->with('success', __('admin.banners.messages.update_success'));
    }

    /**
     * Remove the specified banner from storage.
     */
    public function destroy(string $locale, Banner $banner)
    {
        $this->deleteBanner($banner);

        return redirect()
            ->route('admin.banners.index')
            ->with('success', __('admin.banners.messages.delete_success'));
    }

    /** Persist the manual order from the unfiltered banner listing. */
    public function sort(Request $request, string $locale)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:banners,id'],
            'start_order' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach (array_values($validated['ids']) as $index => $id) {
            Banner::query()->whereKey($id)->update([
                'sort_order' => (int) ($validated['start_order'] ?? 0) + $index,
            ]);
        }

        return response()->json([
            'message' => __('admin.banners.messages.update_success'),
        ]);
    }

    public function bulk(Request $request, string $locale)
    {
        $validated = $this->validatedBulkAction($request, 'banners', 'banners');
        $ids = $validated['ids'];

        if ($validated['action'] === 'delete') {
            Banner::query()->whereIn('id', $ids)->get()->each(fn (Banner $banner) => $this->deleteBanner($banner));
            ActivityLogger::log('bulk_deleted', null, __('admin.banners.log.bulk_deleted'), [
                'model' => Banner::class,
                'ids' => $ids,
                'count' => count($ids),
            ]);

            return back()->with('success', __('admin.banners.messages.bulk_deleted', ['count' => count($ids)]));
        }

        $isActive = $validated['action'] === 'activate';
        $updated = Banner::query()->whereIn('id', $ids)->update(['is_active' => $isActive]);
        ActivityLogger::log('bulk_status_changed', null, __('admin.banners.log.bulk_status'), [
            'model' => Banner::class,
            'ids' => $ids,
            'count' => $updated,
            'is_active' => $isActive,
        ]);

        return back()->with('success', __($isActive ? 'admin.banners.messages.bulk_activated' : 'admin.banners.messages.bulk_deactivated', ['count' => $updated]));
    }

    private function deleteBanner(Banner $banner): void
    {
        if (! empty($banner->image_path)) {
            try {
                $localPath = MediaUrl::publicDiskPath($banner->image_path);
                if ($localPath !== null && Storage::disk('public')->exists($localPath)) {
                    Storage::disk('public')->delete($localPath);
                }
            } catch (\Exception) {
                // The database record can still be safely deleted when an old local file is missing.
            }
        }
        $banner->delete();
    }
}
