<?php

namespace App\Extension\Registries;

use App\Http\Controllers\Controller;

class FixtureRegistry extends Controller
{
    public function kernel(): string
    {
        return 'App\Http\Kernel';
    }

    public function middleware(): string
    {
        return \App\Http\Middleware\EnsureFeatureEnabled::class;
    }
}
