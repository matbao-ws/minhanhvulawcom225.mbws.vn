<?php

namespace App\Services;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
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
        if (!$this->isConfigured()) {
            Log::info("Cloudinary is not configured. Falling back to local storage.");
            $path = $file->store($folder, 'public');
            return Storage::disk('public')->url($path);
        }

        try {
            $uploadApi = new UploadApi();
            $response = $uploadApi->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'auto',
            ]);

            return $response['secure_url'];
        } catch (\Exception $e) {
            Log::error("Cloudinary Upload Error: " . $e->getMessage());
            // Fallback to local storage in case of API failure
            $path = $file->store($folder, 'public');
            return Storage::disk('public')->url($path);
        }
    }

    /**
     * Get a list of folders (predefined for the application).
     */
    public function listFolders(): array
    {
        return ['products', 'brands', 'categories', 'general'];
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
            $results = $adminApi->resources([
                'type' => 'upload',
                'prefix' => $folder === 'all' ? '' : $folder,
                'max_results' => 50,
            ]);

            $resources = [];
            foreach ($results['resources'] ?? [] as $resource) {
                $resources[] = [
                    'secure_url' => $resource['secure_url'],
                    'public_id' => $resource['public_id'],
                    'bytes' => $resource['bytes'],
                    'created_at' => $resource['created_at'],
                    'format' => $resource['format'] ?? 'file',
                ];
            }

            return $resources;
        } catch (\Exception $e) {
            Log::error("Cloudinary List Resources Error: " . $e->getMessage());
            return $this->listLocalFallbackResources($folder);
        }
    }

    /**
     * Delete an asset from Cloudinary (or local storage fallback).
     */
    public function deleteResource(string $publicId): bool
    {
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
            if (str_contains($file, '.gitignore') || str_contains($file, '.DS_Store')) {
                continue;
            }

            $resources[] = [
                'secure_url' => Storage::disk('public')->url($file),
                'public_id' => $file,
                'bytes' => Storage::disk('public')->size($file),
                'created_at' => date('Y-m-d\TH:i:s\Z', Storage::disk('public')->lastModified($file)),
                'format' => pathinfo($file, PATHINFO_EXTENSION),
            ];
        }

        // Sort descending by created_at
        usort($resources, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $resources;
    }
}
