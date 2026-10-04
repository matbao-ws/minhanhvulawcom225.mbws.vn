<?php

/*
 * Website extensions in extensions/<id>/ (docs/EXTENSIONS_DESIGN.md). Each extension's own settings
 * are merged in at runtime under extensions.<id>.
 */
return [
    /*
     * false: load no extension at all and run the bare core — the way out when an extension breaks
     * even `artisan`. Switching off one extension: php artisan extension:disable <id>.
     */
    'enabled' => (bool) env('EXTENSIONS_ENABLED', true),
];
