<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    use HandlesPagination;

    public function __construct(private readonly CloudinaryService $cloudinaryService)
    {
    }

    public function index(Request $request)
    {
        $activeFolder = $request->query('folder', 'general');
        $folders = $this->cloudinaryService->listFolders();

        if (!in_array($activeFolder, $folders) && $activeFolder !== 'all') {
            $activeFolder = 'general';
        }

        // Neither Cloudinary nor the local disk fallback is a query builder, so
        // the listing is paginated in PHP over the resolved collection.
        $allResources = $this->cloudinaryService->listResources($activeFolder);
        $perPage = $this->perPage(25);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $resources = new LengthAwarePaginator(
            array_slice($allResources, ($currentPage - 1) * $perPage, $perPage),
            count($allResources),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
        $resources->withQueryString();

        return view('admin.media.index', [
            'folders' => $folders,
            'activeFolder' => $activeFolder,
            'resources' => $resources,
            'isConfigured' => $this->cloudinaryService->isConfigured(),
        ]);
    }

    /**
     * Image-only data source for the admin media picker.
     */
    public function resources(Request $request)
    {
        $folder = $request->query('folder', 'all');
        $folders = $this->cloudinaryService->listFolders();

        if ($folder !== 'all' && ! in_array($folder, $folders, true)) {
            abort(422, 'Invalid media folder.');
        }

        $cursor = $request->query('cursor');
        abort_unless(is_null($cursor) || (is_string($cursor) && mb_strlen($cursor) <= 512), 422, 'Invalid media cursor.');
        $page = $this->cloudinaryService->listImageResourcePage($folder, $cursor);

        return response()
            ->json($page)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function upload(Request $request)
    {
        $imageOnly = $request->boolean('image_only');
        $allowedMimetypes = $imageOnly
            ? 'image/jpeg,image/png,image/webp,image/gif'
            : 'image/jpeg,image/png,image/webp,image/gif,application/pdf';

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimetypes:'.$allowedMimetypes],
            'folder' => ['nullable', 'string', Rule::in($this->cloudinaryService->listFolders())],
            'image_only' => ['nullable', 'boolean'],
        ], [
            'file.required' => __('catalog.media.upload_required'),
            'file.file' => __('catalog.media.upload_invalid_file'),
            'file.max' => __('catalog.media.upload_too_large'),
            'file.mimetypes' => __($imageOnly
                ? 'catalog.media.upload_invalid_image_type'
                : 'catalog.media.upload_invalid_type'),
            'folder.in' => __('catalog.media.upload_invalid_folder'),
            'image_only.boolean' => __('catalog.media.upload_invalid_context'),
        ]);

        $folder = $request->input('folder', 'general');
        
        try {
            $url = $this->cloudinaryService->uploadFile($request->file('file'), $folder);
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'url' => $url,
                    'message' => __('catalog.media.upload_success')
                ]);
            }

            return redirect()->back()->with('success', __('catalog.media.upload_success'));
        } catch (\Exception $e) {
            Log::error("Media Controller Upload Error: " . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('catalog.media.upload_failed')
                ], 500);
            }

            return redirect()->back()->withErrors(['file' => __('catalog.media.upload_failed')]);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'public_id' => ['required', 'string', 'max:255', 'regex:/^(products|brands|categories|posts|banners|settings|avatars|general)\/[A-Za-z0-9_\.\/-]+$/'],
        ]);

        $publicId = $request->input('public_id');

        try {
            $deleted = $this->cloudinaryService->deleteResource($publicId);
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => $deleted,
                    'message' => $deleted ? __('catalog.media.delete_success') : __('catalog.media.delete_failed')
                ]);
            }

            if ($deleted) {
                return redirect()->back()->with('success', __('catalog.media.delete_success'));
            }
            return redirect()->back()->withErrors(['public_id' => __('catalog.media.delete_failed')]);
        } catch (\Exception $e) {
            Log::error("Media Controller Delete Error: " . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('catalog.media.delete_failed')
                ], 500);
            }

            return redirect()->back()->withErrors(['public_id' => __('catalog.media.delete_failed')]);
        }
    }
}
