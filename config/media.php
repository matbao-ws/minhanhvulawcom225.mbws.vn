<?php

return [
    /*
     * Local public storage has no inherent quota. This is the media-library
     * budget shown to administrators; operators may override it per project.
     */
    'local_storage_limit_bytes' => (int) env('MEDIA_LIBRARY_STORAGE_LIMIT_MB', 10240) * 1024 * 1024,

    /*
     * Uploaded JPG/PNG/WebP photos are resized and re-encoded before they are
     * stored (see App\Services\Media\ImageOptimizer). The original is kept
     * whenever the optimised file would not be smaller.
     */
    'optimize' => [
        'enabled' => (bool) env('MEDIA_OPTIMIZE_IMAGES', true),
        // Longest side in pixels; enough for full-width banners on retina screens.
        'max_dimension' => (int) env('MEDIA_IMAGE_MAX_DIMENSION', 2560),
        // WebP/JPEG quality; 80-85 is visually indistinguishable from the source.
        'quality' => (int) env('MEDIA_IMAGE_QUALITY', 82),
        // Larger images are stored untouched rather than risk running out of memory.
        'max_pixels' => 50_000_000,
    ],
];
