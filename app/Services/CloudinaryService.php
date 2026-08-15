<?php

namespace App\Services;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Illuminate\Http\UploadedFile;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    /**
     * Page size used when walking the Cloudinary Admin API; 500 is the maximum
     * the endpoint accepts.
     */
    private const REMOTE_PAGE_SIZE = 500;

    /**
     * Upper bound on how many assets the media library will collect in one
     * request. Pagination happens in PHP, so the whole listing has to be
     * fetched; this keeps a very large Cloudinary account from turning a page
     * view into an unbounded number of API calls.
     */
    private const REMOTE_LISTING_LIMIT = 2000;

    public function __construct()
    {
        if ($this->isConfigured()) {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('services.cloudinary.cloud_name'),
                    'api_key'    => config('services.cloudinary.api_key'),
                    'api_secret' => config('services.cloudinary.api_secret'),
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
        return !empty(config('services.cloudinary.cloud_name'))
            && !empty(config('services.cloudinary.api_key'))
            && !empty(config('services.cloudinary.api_secret'));
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
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
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

            return $response['secure_url'];
        } catch (\Exception $e) {
            Log::error("Cloudinary Upload Error: " . $e->getMessage());
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
     * List files in a specific folder.
     */
    public function listResources(string $folder = 'general'): array
    {
        if (!$this->isConfigured()) {
            return $this->listLocalFallbackResources($folder);
        }

        try {
            $adminApi = new AdminApi();
            $resources = [];
            $cursor = null;

            do {
                $options = [
                    'type' => 'upload',
                    'prefix' => $folder === 'all' ? '' : $folder,
                    'max_results' => self::REMOTE_PAGE_SIZE,
                ];
                if ($cursor !== null) {
                    $options['next_cursor'] = $cursor;
                }

                $results = $adminApi->resources($options);

                foreach ($results['resources'] ?? [] as $resource) {
                    $resources[] = [
                        'secure_url' => $resource['secure_url'],
                        'public_id' => $resource['public_id'],
                        'bytes' => $resource['bytes'],
                        'created_at' => $resource['created_at'],
                        'format' => $resource['format'] ?? 'file',
                    ];
                }

                $cursor = $results['next_cursor'] ?? null;
            } while ($cursor !== null && count($resources) < self::REMOTE_LISTING_LIMIT);

            return $resources;
        } catch (\Exception $e) {
            Log::error("Cloudinary List Resources Error: " . $e->getMessage());
            return $this->listLocalFallbackResources($folder);
        }
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

            $results = (new AdminApi())->resources($options);
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
        } catch (\Exception $e) {
            Log::error('Cloudinary List Image Resources Error: ' . $e->getMessage());
            return $this->listImageResourcePageFromLocalFallback($folder, $cursor, $limit);
        }
    }

    /**
     * Delete an asset from Cloudinary (or local storage fallback).
     */
    public function deleteResource(string $publicId): bool
    {
        if (str_contains($publicId, '..') || ! preg_match('#^(products|brands|categories|posts|banners|settings|avatars|general)/[A-Za-z0-9_.\-/]+$#', $publicId)) {
            return false;
        }

        if (!$this->isConfigured()) {
            // Find and delete the local file if it exists
            // The publicId for local files is stored as folder/filename.ext
            if (Storage::disk('public')->exists($publicId)) {
                return Storage::disk('public')->delete($publicId);
            }
            return true;
        }

        try {
            $uploadApi = new UploadApi();
            $result = $uploadApi->destroy($publicId);
            return isset($result['result']) && $result['result'] === 'ok';
        } catch (\Exception $e) {
            Log::error("Cloudinary Delete Error: " . $e->getMessage());
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

            $dimensions = $this->localImageDimensions($file);
            $resources[] = [
                'secure_url' => $this->localUrl($file),
                'public_id' => $file,
                'bytes' => Storage::disk('public')->size($file),
                'created_at' => date('Y-m-d\TH:i:s\Z', Storage::disk('public')->lastModified($file)),
                'format' => pathinfo($file, PATHINFO_EXTENSION),
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
