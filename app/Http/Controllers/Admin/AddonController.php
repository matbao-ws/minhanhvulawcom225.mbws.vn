<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;

class AddonController extends Controller
{
    /**
     * Display the integrations catalogue.
     */
    public function index()
    {
        return view('admin.addons.index', [
            'addons' => Addon::query()->orderBy('name')->get(),
        ]);
    }
}
