<?php

namespace App\Http\Controllers\Admin;

use App\Models;
use App\Models\Order as Purchase;

class FixtureOrderController
{
    public function viaAlias()
    {
        return Purchase::create([]);
    }

    public function viaFullyQualifiedQuery()
    {
        return \App\Models\Order::query()->create([]);
    }

    public function viaPartiallyQualified()
    {
        return Models\OrderItem::forceCreate([]);
    }

    public function viaNew()
    {
        $order = new Purchase;
        $order->save();
    }

    public function viaClosure()
    {
        return collect([1])->map(fn () => Purchase::query()->lockForUpdate()->firstOrCreate([]));
    }

    public function readOnly()
    {
        return Purchase::query()->where('id', 1)->first();
    }
}
