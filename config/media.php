<?php

return [
    /*
     * Local public storage has no inherent quota. This is the media-library
     * budget shown to administrators; operators may override it per project.
     */
    'local_storage_limit_bytes' => (int) env('MEDIA_LIBRARY_STORAGE_LIMIT_MB', 10240) * 1024 * 1024,
];
