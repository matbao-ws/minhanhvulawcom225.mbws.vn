<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CoreManifest;
use App\Support\Extensions\ExtensionManager;

/**
 * Superadmin page: the website's extensions and what became of each in this request (loaded,
 * failed with the reason, disabled, incompatible, invalid). Read-only — switching one on or off is
 * `php artisan extension:enable|disable`, which works even while an extension breaks the admin.
 */
class ExtensionController extends Controller
{
    public function index(ExtensionManager $extensions, CoreManifest $core)
    {
        return view('admin.extensions.index', [
            'records' => $extensions->all(),
            'enabled' => $extensions->enabled(),
            'coreVersion' => $core->version(),
        ]);
    }
}
