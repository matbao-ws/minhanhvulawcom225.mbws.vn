<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureSetting;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        $features = FeatureSetting::all();

        return view('admin.features.index', [
            'features' => $features,
        ]);
    }

    public function update(Request $request)
    {
        $features = $request->input('features', []);
        $limits = $request->input('limits', []);

        $allSettings = FeatureSetting::all();

        foreach ($allSettings as $setting) {
            $code = $setting->feature_code;
            
            if (isset($features[$code])) {
                $isEnabled = $features[$code] == '1';
                $limitValue = $limits[$code] ?? null;

                $setting->update([
                    'is_enabled' => $isEnabled,
                    'limit_value' => $limitValue !== '' ? $limitValue : null,
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()
            ->route('admin.features.index')
            ->with('success', __('admin.features.updated_success'));
    }

    public function toggle(Request $request)
    {
        $code = $request->input('feature_code');
        $isEnabled = (bool) $request->input('is_enabled');

        $setting = FeatureSetting::where('feature_code', $code)->first();
        if ($setting) {
            $setting->update([
                'is_enabled' => $isEnabled,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => __('admin.features.updated_success'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Feature setting not found.',
        ], 404);
    }
}
