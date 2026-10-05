<?php

use App\Providers\AppServiceProvider;
use App\Providers\ExtensionServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    // The website's extensions/ (docs/EXTENSIONS_DESIGN.md); none there = nothing changes.
    ExtensionServiceProvider::class,
    SanctumServiceProvider::class,
];
