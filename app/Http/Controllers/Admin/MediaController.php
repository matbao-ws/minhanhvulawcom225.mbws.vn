<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\CloudinaryCredentials;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    use HandlesPagination;

    private const DEFAULT_UPLOAD_MAX_KILOBYTES = 10240;

    private const VIDEO_UPLOAD_MAX_KILOBYTES = 51200;

    private const VIDEO_MIMETYPE = 'video/mp4';

    private const MAX_FILES_PER_UPLOAD = 10;

    public function __construct(
        private readonly CloudinaryService $cloudinaryService,
        private readonly CloudinaryCredentials $cloudinaryCredentials,
    ) {}

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
        $sort = $request->query('sort', 'newest');
        if (! in_array($sort, ['newest', 'oldest', 'name_asc', 'name_desc'], true)) {
            $sort = 'newest';
        }
        usort($allResources, function (array $left, array $right) use ($sort): int {
            return match ($sort) {
                'oldest' => strcmp($left['created_at'], $right['created_at']),
                'name_asc' => strnatcasecmp($left['public_id'], $right['public_id']),
                'name_desc' => strnatcasecmp($right['public_id'], $left['public_id']),
                default => strcmp($right['created_at'], $left['created_at']),
            };
        });
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
            'storageUsage' => $this->cloudinaryService->storageUsage(),
            'cloudinaryConnection' => $this->cloudinaryCredentials->summary(),
            'sort' => $sort,
        ]);
    }

    public function connectCloudinary(Request $request)
    {
        // Do not use Request::validate here: this redirect must carry errors
        // without ever flashing Cloudinary credentials into the session.
        $validator = Validator::make($request->only(['cloud_name', 'api_key', 'api_secret', 'current_password']), [
            'cloud_name' => ['required', 'string', 'max:128', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'api_key' => ['required', 'string', 'min:3', 'max:128', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'api_secret' => ['required', 'string', 'min:6', 'max:256', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'current_password' => ['required', 'current_password:web'],
        ], [
            'cloud_name.required' => __('catalog.media.cloudinary_cloud_name_required'),
            'cloud_name.regex' => __('catalog.media.cloudinary_cloud_name_invalid'),
            'api_key.required' => __('catalog.media.cloudinary_api_key_required'),
            'api_key.regex' => __('catalog.media.cloudinary_api_key_invalid'),
            'api_secret.required' => __('catalog.media.cloudinary_api_secret_required'),
            'api_secret.regex' => __('catalog.media.cloudinary_api_secret_invalid'),
            'current_password.required' => __('catalog.media.current_password_required'),
            'current_password.current_password' => __('catalog.media.current_password_invalid'),
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'cloudinary')
                ->with('cloudinary_action', 'connect');
        }

        $credentials = [
            'cloud_name' => trim((string) $request->input('cloud_name')),
            'api_key' => trim((string) $request->input('api_key')),
            'api_secret' => trim((string) $request->input('api_secret')),
        ];

        // Keep secrets out of the request object before any network or database
        // operation can throw and reach an exception renderer.
        $request->request->remove('cloud_name');
        $request->request->remove('api_key');
        $request->request->remove('api_secret');
        $request->request->remove('current_password');

        if (! $this->cloudinaryCredentials->verify($credentials)) {
            return back()
                ->withErrors(['api_secret' => __('catalog.media.cloudinary_connection_failed')], 'cloudinary')
                ->with('cloudinary_action', 'connect');
        }

        $setting = $this->cloudinaryCredentials->connect($credentials);
        ActivityLogger::log('updated', $setting, 'Kết nối Cloudinary cho thư viện media', [
            'cloud_name' => $credentials['cloud_name'],
        ]);

        return back()->with('success', __('catalog.media.cloudinary_connected'));
    }

    public function disconnectCloudinary(Request $request)
    {
        $validator = Validator::make($request->only('current_password'), [
            'current_password' => ['required', 'current_password:web'],
        ], [
            'current_password.required' => __('catalog.media.current_password_required'),
            'current_password.current_password' => __('catalog.media.current_password_invalid'),
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'cloudinary')
                ->with('cloudinary_action', 'disconnect');
        }

        $request->request->remove('current_password');
        $setting = $this->cloudinaryCredentials->disconnect();
        ActivityLogger::log('updated', $setting, 'Ngắt kết nối Cloudinary của thư viện media');

        return back()->with('success', __('catalog.media.cloudinary_disconnected'));
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
            : 'image/jpeg,image/png,image/webp,image/gif,application/pdf,'.self::VIDEO_MIMETYPE;
        $uploaded = $request->file('file');
        $files = is_array($uploaded) ? array_values($uploaded) : [$uploaded];

        $rules = [
            'folder' => ['nullable', 'string', Rule::in($this->cloudinaryService->listFolders())],
            'image_only' => ['nullable', 'boolean'],
        ];
        $messages = [
            'file.required' => __('catalog.media.upload_required'),
            'file.file' => __('catalog.media.upload_invalid_file'),
            'folder.in' => __('catalog.media.upload_invalid_folder'),
            'image_only.boolean' => __('catalog.media.upload_invalid_context'),
        ];

        if (is_array($uploaded)) {
            $rules['file'] = ['required', 'array', 'min:1', 'max:'.self::MAX_FILES_PER_UPLOAD];
            $messages['file.max'] = __('catalog.media.upload_too_many_files', ['count' => self::MAX_FILES_PER_UPLOAD]);
        }

        foreach ($files as $index => $file) {
            $isVideo = ! $imageOnly && $file?->getMimeType() === self::VIDEO_MIMETYPE;
            $key = is_array($uploaded) ? "file.{$index}" : 'file';
            $rules[$key] = [
                'required',
                'file',
                'max:'.($isVideo ? self::VIDEO_UPLOAD_MAX_KILOBYTES : self::DEFAULT_UPLOAD_MAX_KILOBYTES),
                'mimetypes:'.$allowedMimetypes,
                ...($isVideo ? ['extensions:mp4'] : []),
            ];
            $messages["{$key}.max"] = __($isVideo ? 'catalog.media.upload_video_too_large' : 'catalog.media.upload_too_large');
            $messages["{$key}.mimetypes"] = __($imageOnly
                ? 'catalog.media.upload_invalid_image_type'
                : 'catalog.media.upload_invalid_type');
            $messages["{$key}.extensions"] = __('catalog.media.upload_invalid_type');
        }

        validator($request->all(), $rules, $messages)->validate();

        $folder = $request->input('folder', 'general');

        try {
            $urls = array_map(
                fn ($file): string => $this->cloudinaryService->uploadFile($file, $folder),
                $files,
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'url' => $urls[0],
                    'urls' => $urls,
                    'message' => __('catalog.media.upload_success'),
                ]);
            }

            return redirect()->back()->with('success', __('catalog.media.upload_success'));
        } catch (\Exception $e) {
            Log::error('Media upload failed.', ['exception' => $e::class]);
            
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
            'resource_type' => ['nullable', 'string', Rule::in(['image', 'video'])],
            'source' => ['nullable', 'string', Rule::in(['local', 'cloudinary'])],
        ]);

        $publicId = $request->input('public_id');
        $resourceType = $request->input('resource_type', 'image');
        $source = $request->input('source');

        try {
            $deleted = $this->cloudinaryService->deleteResource($publicId, $resourceType, $source);

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
        } catch (\Throwable $e) {
            Log::error('Media deletion failed.', ['exception' => $e::class]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('catalog.media.delete_failed')
                ], 500);
            }

            return redirect()->back()->withErrors(['public_id' => __('catalog.media.delete_failed')]);
        }
    }

    /**
     * Delete several assets selected in the library grid in one request.
     *
     * Each item carries its own `source` because, once the local and
     * Cloudinary listings are merged, a single request can legitimately mix
     * files that live on each side.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.public_id' => ['required', 'string', 'max:255', 'regex:/^(products|brands|categories|posts|banners|settings|avatars|general)\/[A-Za-z0-9_\.\/-]+$/'],
            'items.*.resource_type' => ['nullable', 'string', Rule::in(['image', 'video'])],
            'items.*.source' => ['required', 'string', Rule::in(['local', 'cloudinary'])],
        ]);

        $deletedCount = 0;

        foreach ($validated['items'] as $item) {
            try {
                $deleted = $this->cloudinaryService->deleteResource(
                    $item['public_id'],
                    $item['resource_type'] ?? 'image',
                    $item['source'],
                );
            } catch (\Throwable $e) {
                Log::error('Bulk media deletion failed for one item.', ['exception' => $e::class]);
                $deleted = false;
            }

            if ($deleted) {
                $deletedCount++;
            }
        }

        $failedCount = count($validated['items']) - $deletedCount;
        $message = $failedCount === 0
            ? __('catalog.media.bulk_delete_success', ['count' => $deletedCount])
            : __('catalog.media.bulk_delete_partial', ['deleted' => $deletedCount, 'failed' => $failedCount]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $failedCount === 0,
                'deleted' => $deletedCount,
                'failed' => $failedCount,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with($failedCount === 0 ? 'success' : 'error', $message);
    }
}
