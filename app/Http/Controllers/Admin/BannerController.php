<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinaryService)
    {
    }

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

        $banners = $query->orderBy('sort_order', 'asc')->paginate(10);

        return view('admin.banners.index', compact('banners'));
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
            'image_file' => 'required|image|max:2048',
            'link_url' => 'nullable|string|max:255',
            'position' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:0',
        ]);

        $imagePath = $this->cloudinaryService->uploadFile($request->file('image_file'), 'banners');

        Banner::create([
            'title' => $validated['title'] ?? null,
            'image_path' => $imagePath,
            'link_url' => $validated['link_url'] ?? null,
            'position' => $validated['position'],
            'sort_order' => $validated['sort_order'],
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
            'link_url' => 'nullable|string|max:255',
            'position' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:0',
        ]);

        $imagePath = $banner->image_path;

        if ($request->hasFile('image_file')) {
            // Delete old resource if it was local fallback
            if (!empty($banner->image_path)) {
                try {
                    $parsedUrl = parse_url($banner->image_path);
                    if (isset($parsedUrl['path'])) {
                        $localPath = str_replace('/storage/', '', $parsedUrl['path']);
                        if (Storage::disk('public')->exists($localPath)) {
                            Storage::disk('public')->delete($localPath);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore deletion error
                }
            }

            $imagePath = $this->cloudinaryService->uploadFile($request->file('image_file'), 'banners');
        }

        $banner->update([
            'title' => $validated['title'] ?? null,
            'image_path' => $imagePath,
            'link_url' => $validated['link_url'] ?? null,
            'position' => $validated['position'],
            'sort_order' => $validated['sort_order'],
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
        if (!empty($banner->image_path)) {
            try {
                $parsedUrl = parse_url($banner->image_path);
                if (isset($parsedUrl['path'])) {
                    $localPath = str_replace('/storage/', '', $parsedUrl['path']);
                    if (Storage::disk('public')->exists($localPath)) {
                        Storage::disk('public')->delete($localPath);
                    }
                }
            } catch (\Exception $e) {
                // Ignore deletion error
            }
        }

        $banner->delete();

        return redirect()
            ->route('admin.banners.index')
            ->with('success', __('admin.banners.messages.delete_success'));
    }
}
