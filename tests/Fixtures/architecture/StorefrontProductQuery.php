<?php

namespace App\Http\Controllers\Client;

use App\Models\Product as Item;

class FixtureProductController
{
    public function viaAlias()
    {
        return Item::where('is_active', true)->get();
    }

    public function viaFullyQualified()
    {
        return \App\Models\Product::query()->latest()->paginate();
    }

    public function classConstantOnly()
    {
        return Item::class;
    }
}
