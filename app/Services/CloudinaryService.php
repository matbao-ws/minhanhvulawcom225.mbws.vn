<?php

namespace App\Services;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Illuminate\Http\UploadedFile;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CloudinaryService
{
    /**
     * Page size used when walking the Cloudinary Admin API; 500 is the maximum
     * the endpoint accepts.
     */
    private const REMOTE_PAGE_SIZE = 500;

    /**
     * Upper bound on how many assets the media library returns in one request.
     * Cloudinary images and videos are fetched separately, then merged and
     * trimmed before PHP pagination so a large account remains bounded.
     */
    private const REMOTE_LISTING_LIMIT = 2000;

    /**
     * How long a folder's resource listing is cached. Each call to the Admin
     * API costs real round-trip latency regardless of how few assets it
     * returns (measured well over a second per call), and every pagination
     * click, folder switch, sort change, and post-upload/delete redirect
     * re-renders the library — without this, all of them pay that cost again.
     */
    private const RESOURCE_LIST_CACHE_TTL_SECONDS = 60;

    public function __construct(private readonly CloudinaryCredentials $credentials)
    {
        if ($credentials = $this->credentials->current()) {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $credentials['cloud_name'],
                    'api_key'    => $credentials['api_key'],
                    'api_secret' => $credentials['api_secret'],
                ],
                'url' => [
                    'secure' => true
                ]
            ]);
        }
    }

    /**
     * Check if Cloudinary credentials are fully configured.
     */
    public function isConfigured(): bool
    {
        return $this->credentials->current() !== null;
    }

    /**
     * Extensions a stored file may carry. The web server serves storage/ by
     * extension, so anything that renders (html, svg, xml…) would run on the
     * admin's own origin.
     */
    public const STORABLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'ico', 'cur', 'pdf', 'mp4'];

    /**
     * The extension comes from the content, not from the client's file name:
     * validation reads the content, so an image polyglot named x.html passes it.
     * The client's extension is only a fallback for types the MIME database
     * names differently (cursor files), and only from the allowlist.
     */
    private function storedExtension(UploadedFile $file): string
    {
        foreach ([$file->guessExtension(), $file->getClientOriginalExtension()] as $candidate) {
            $candidate = strtolower((string) $candidate);
            if (in_array($candidate, self::STORABLE_EXTENSIONS, true)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['file' => __('catalog.media.upload_invalid_file')]);
    }

    /**
     * Upload a file to Cloudinary (or local storage fallback).
     */
    public function uploadFile(UploadedFile $file, string $folder = 'general'): string
    {
        if (! in_array($folder, $this->listFolders(), true)) {
            throw new \InvalidArgumentException('Invalid media folder.');
        }

        // Generate clean, SEO-friendly filename from original file name
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $this->storedExtension($file);
        $slug = \Illuminate\Support\Str::slug($originalName);
        if (empty($slug)) {
            $slug = 'file';
        }
        $suffix = substr(md5(uniqid((string) mt_rand(), true)), 0, 6);
        $friendlyFilename = "{$slug}-{$suffix}.{$extension}";
        $publicId = "{$slug}-{$suffix}";

        if (!$this->isConfigured()) {
            Log::info("Cloudinary is not configured. Falling back to local storage.");
            $path = $file->storeAs($folder, $friendlyFilename, 'public');
            return $this->localUrl($path);
        }

        try {
            $uploadApi = new UploadApi();
            $response = $uploadApi->upload($file->getRealPath(), [
                'folder' => $folder,
                'public_id' => $publicId,
                'use_filename' => true,
                'unique_filename' => false,
                'resource_type' => 'auto',
            ]);

            $this->forgetResourcesCache($folder);

            return $response['secure_url'];
        } catch (\Throwable $e) {
            Log::error('Cloudinary upload failed.', ['exception' => $e::class]);
            // Fallback to local storage in case of API failure
            $path = $file->storeAs($folder, $friendlyFilename, 'public');
            return $this->localUrl($path);
        }
    }

    /**
     * Reference to a file on our own public disk.
     *
     * Deliberately root-relative rather than absolute: these references are
     * persisted, and an absolute URL would pin the record to whatever APP_URL
     * happened to be set when the upload ran. See {@see MediaUrl}.
     */
    private function localUrl(string $path): string
    {
        return (string) MediaUrl::toStorable(Storage::disk('public')->url($path));
    }

    /**
     * Get a list of folders (predefined for the application).
     */
    public function listFolders(): array
    {
        return ['products', 'brands', 'categories', 'posts', 'banners', 'settings', 'avatars', 'general'];
    }

    /**
     * Usage for the media-library sidebar. Local storage has a configured soft
     * quota, while Cloudinary exposes its plan quota through the Admin API.
     *
     * @return array{used: int|null, limit: int|null, credits_used: float|null, credits_limit: float|null}
     */
    public function storageUsage(): array
    {
        if (! $this->isConfigured()) {
            $used = 0;
            foreach (Storage::disk('public')->allFiles() as $file) {
                $used += Storage::disk('public')->size($file);
            }

            return [
                'used' => $used,
                'limit' => max(0, (int) config('media.local_storage_limit_bytes')),
                'credits_used' => null,
                'credits_limit' => null,
            ];
        }

        try {
            $cacheKey = $this->credentials->usageCacheKey();
            $usage = Cache::remember($cacheKey, now()->addMinutes(30), function (): array {
                $response = (new AdminApi())->usage();

                return [
                    'storage' => $response['storage'] ?? [],
                    'credits' => $response['credits'] ?? [],
                ];
            });
            $storage = $usage['storage'];
            $credits = $usage['credits'];

            return [
                'used' => isset($storage['usage']) ? (int) $storage['usage'] : null,
                'limit' => isset($storage['limit']) ? (int) $storage['limit'] : null,
                'credits_used' => isset($credits['usage']) ? (float) $credits['usage'] : null,
                'credits_limit' => isset($credits['limit']) ? (float) $credits['limit'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Cloudinary usage lookup failed.', ['exception' => $e::class]);

            return [
                'used' => null,
                'limit' => null,
                'credits_used' => null,
                'credits_limit' => null,
            ];
        }
    }

    /**
     * List files in a specific folder.
     *
     * Once Cloudinary is connected, files uploaded to local storage before the
     * connection (or during a later Cloudinary outage) would otherwise vanish
     * from the library even though they are still served from disk, so both
     * sources are merged here and each entry is tagged with where it lives.
     */
    public function listResources(string $folder = 'general'): array
    {
        $local = $this->filterManaged($this->tagSource($this->listLocalFallbackResources($folder), 'local'));

        if (!$this->isConfigured()) {
            return $local;
        }

        try {
            $cloud = $this->filterManaged($this->tagSource($this->fetchCloudResources($folder), 'cloudinary'));
        } catch (\Throwable $e) {
            Log::error('Cloudinary resource listing failed.', ['exception' => $e::class]);
            return $local;
        }

        $resources = array_merge($cloud, $local);
        usort($resources, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return array_slice($resources, 0, self::REMOTE_LISTING_LIMIT);
    }

    /**
     * Cloudinary separates video from image resources, even when upload uses
     * resource_type=auto. Fetch both so a video never disappears from the
     * media library after a successful upload.
     */
    protected function fetchCloudResources(string $folder): array
    {
        $fetch = function () use ($folder): array {
            $resources = [];

            foreach (['image', 'video'] as $resourceType) {
                $cursor = null;
                $resourceCount = 0;

                do {
                    $options = [
                        'type' => 'upload',
                        'resource_type' => $resourceType,
                        'prefix' => $folder === 'all' ? '' : $folder,
                        'max_results' => self::REMOTE_PAGE_SIZE,
                    ];
                    if ($cursor !== null) {
                        $options['next_cursor'] = $cursor;
                    }

                    $results = (new AdminApi())->assets($options);

                    foreach ($results['resources'] ?? [] as $resource) {
                        $resources[] = [
                            'secure_url' => $resource['secure_url'],
                            'public_id' => $resource['public_id'],
                            'bytes' => $resource['bytes'],
                            'created_at' => $resource['created_at'],
                            'format' => $resource['format'] ?? 'file',
                            'resource_type' => $resourceType,
                        ];
                        $resourceCount++;
                    }

                    $cursor = $results['next_cursor'] ?? null;
                } while ($cursor !== null && $resourceCount < self::REMOTE_LISTING_LIMIT);
            }

            return $resources;
        };

        $cacheKey = $this->resourcesCacheKey($folder);

        return $cacheKey === null
            ? $fetch()
            : Cache::remember($cacheKey, now()->addSeconds(self::RESOURCE_LIST_CACHE_TTL_SECONDS), $fetch);
    }

    private function resourcesCacheKey(string $folder): ?string
    {
        $cloudName = $this->credentials->current()['cloud_name'] ?? null;

        return $cloudName === null ? null : 'cloudinary:resources:'.hash('sha256', $cloudName.'|'.$folder);
    }

    /**
     * Invalidate the cached listing for a folder after a change, plus the
     * "all" view since it includes every folder. Without this, an admin's own
     * upload or delete would not show up until the cache naturally expires.
     */
    private function forgetResourcesCache(string $folder): void
    {
        foreach (array_unique([$folder, 'all']) as $affectedFolder) {
            $cacheKey = $this->resourcesCacheKey($affectedFolder);
            if ($cacheKey !== null) {
                Cache::forget($cacheKey);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $resources */
    private function tagSource(array $resources, string $source): array
    {
        foreach ($resources as &$resource) {
            $resource['source'] = $source;
        }

        return $resources;
    }

    /**
     * Drop assets outside the app's managed folders before they ever reach the
     * library grid. Every Cloudinary account ships with root-level demo assets
     * (e.g. "sample", "cloudinary-icon") that predate this app and were never
     * uploaded through it; listing them lets an admin select and "delete" a file
     * that {@see deleteResource()} can never validate, surfacing a raw
     * items.*.public_id validation error instead of a usable action.
     *
     * @param array<int, array<string, mixed>> $resources
     */
    private function filterManaged(array $resources): array
    {
        return array_values(array_filter(
            $resources,
            fn (array $resource): bool => $this->isManagedPublicId($resource['public_id']),
        ));
    }

    private function isManagedPublicId(string $publicId): bool
    {
        $folders = implode('|', array_map(
            fn (string $folder): string => preg_quote($folder, '#'),
            $this->listFolders(),
        ));

        return (bool) preg_match('#^(?:'.$folders.')/[A-Za-z0-9_.\-/]+$#', $publicId);
    }

    /**
     * Return one page of images for the admin picker. Cloudinary cursors are
     * passed through unchanged; local storage uses an opaque page cursor.
     */
    public function listImageResourcePage(string $folder, ?string $cursor = null, int $limit = 24): array
    {
        $limit = max(1, min($limit, 100));

        if (! $this->isConfigured()) {
            $resources = array_values(array_filter(
                $this->listLocalFallbackResources($folder),
                fn (array $resource): bool => $this->isImageFormat($resource['format'] ?? ''),
            ));
            $page = $cursor && preg_match('/^local:(\d+)$/', $cursor, $matches) ? max(1, (int) $matches[1]) : 1;
            $offset = ($page - 1) * $limit;

            return [
                'resources' => array_slice($resources, $offset, $limit),
                'next_cursor' => $offset + $limit < count($resources) ? 'local:' . ($page + 1) : null,
            ];
        }

        try {
            $options = [
                'type' => 'upload',
                'resource_type' => 'image',
                'prefix' => $folder === 'all' ? '' : $folder,
                'max_results' => $limit,
            ];
            if ($cursor) $options['next_cursor'] = $cursor;

            $results = (new AdminApi())->assets($options);
            $resources = array_map(fn (array $resource) => [
                'secure_url' => $resource['secure_url'],
                'public_id' => $resource['public_id'],
                'bytes' => $resource['bytes'],
                'created_at' => $resource['created_at'],
                'format' => $resource['format'] ?? 'file',
                'width' => isset($resource['width']) ? (int) $resource['width'] : null,
                'height' => isset($resource['height']) ? (int) $resource['height'] : null,
            ], $results['resources'] ?? []);

            return ['resources' => $resources, 'next_cursor' => $results['next_cursor'] ?? null];
        } catch (\Throwable $e) {
            Log::error('Cloudinary image listing failed.', ['exception' => $e::class]);
            return $this->listImageResourcePageFromLocalFallback($folder, $cursor, $limit);
        }
    }

    /**
     * Delete an asset from Cloudinary or local storage.
     *
     * Once both sources are merged in the media library, the caller must say
     * which one a given entry actually lives on (`$source`); with Cloudinary
     * connected, a `public_id` alone no longer implies where the file is.
     * Passing no source keeps the pre-merge behaviour of picking local
     * storage only when Cloudinary is not configured at all.
     */
    public function deleteResource(string $publicId, string $resourceType = 'image', ?string $source = null): bool
    {
        if (
            str_contains($publicId, '..')
            || ! $this->isManagedPublicId($publicId)
            || ! in_array($resourceType, ['image', 'video'], true)
        ) {
            return false;
        }

        if ($source === 'local' || ($source === null && ! $this->isConfigured())) {
            // Find and delete the local file if it exists
            // The publicId for local files is stored as folder/filename.ext
            if (Storage::disk('public')->exists($publicId)) {
                return Storage::disk('public')->delete($publicId);
            }
            return true;
        }

        try {
            $uploadApi = new UploadApi();
            $result = $uploadApi->destroy($publicId, ['resource_type' => $resourceType]);
            $deleted = isset($result['result']) && $result['result'] === 'ok';

            if ($deleted) {
                $this->forgetResourcesCache((string) strstr($publicId, '/', true));
            }

            return $deleted;
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed.', ['exception' => $e::class]);
            return false;
        }
    }

    /**
     * List resources from the local public storage as a fallback.
     */
    private function listLocalFallbackResources(string $folder): array
    {
        $dir = $folder === 'all' ? '' : $folder;
        if (!Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }

        $files = Storage::disk('public')->allFiles($dir);
        $resources = [];

        foreach ($files as $file) {
            // Skip system files if any
            if (str_contains($file, '.gitignore') || str_contains($file, '.DS_Store') || str_contains($file, '/.') || str_starts_with($file, '.')) {
                continue;
            }

            $format = pathinfo($file, PATHINFO_EXTENSION);
            $dimensions = $this->localImageDimensions($file);
            $resources[] = [
                'secure_url' => $this->localUrl($file),
                'public_id' => $file,
                'bytes' => Storage::disk('public')->size($file),
                'created_at' => date('Y-m-d\TH:i:s\Z', Storage::disk('public')->lastModified($file)),
                'format' => $format,
                'resource_type' => $this->isVideoFormat($format) ? 'video' : 'image',
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ];
        }

        // Sort descending by created_at
        usort($resources, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $resources;
    }

    private function listImageResourcePageFromLocalFallback(string $folder, ?string $cursor, int $limit): array
    {
        $resources = array_values(array_filter(
            $this->listLocalFallbackResources($folder),
            fn (array $resource): bool => $this->isImageFormat($resource['format'] ?? ''),
        ));
        $page = $cursor && preg_match('/^local:(\d+)$/', $cursor, $matches) ? max(1, (int) $matches[1]) : 1;
        $offset = ($page - 1) * $limit;

        return [
            'resources' => array_slice($resources, $offset, $limit),
            'next_cursor' => $offset + $limit < count($resources) ? 'local:' . ($page + 1) : null,
        ];
    }

    private function isImageFormat(string $format): bool
    {
        return in_array(strtolower($format), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'], true);
    }

    private function isVideoFormat(string $format): bool
    {
        return strtolower($format) === 'mp4';
    }

    /** @return array{width: int|null, height: int|null} */
    private function localImageDimensions(string $file): array
    {
        try {
            $path = Storage::disk('public')->path($file);
            $size = is_file($path) ? @getimagesize($path) : false;

            return [
                'width' => is_array($size) ? (int) $size[0] : null,
                'height' => is_array($size) ? (int) $size[1] : null,
            ];
        } catch (\Throwable) {
            return ['width' => null, 'height' => null];
        }
    }
}
