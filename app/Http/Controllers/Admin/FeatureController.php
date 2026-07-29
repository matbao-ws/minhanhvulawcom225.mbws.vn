<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function index()
    {
        $features = FeatureSetting::query()
            ->whereIn('feature_code', config('features.codes', []))
            ->get()
            ->keyBy('feature_code');

        $featureGroups = collect(config('features.groups', []))
            ->map(fn (array $codes) => collect($codes)
                ->map(fn (string $code) => $features->get($code))
                ->filter()
                ->values());

        return view('admin.features.index', [
            'featureGroups' => $featureGroups,
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
        $validated = $request->validate([
            'feature_code' => ['required', 'string', Rule::in(config('features.codes', []))],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $setting = FeatureSetting::where('feature_code', $validated['feature_code'])->first();
        if ($setting) {
            $setting->update([
                'is_enabled' => $validated['is_enabled'],
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

    public function toggleGroup(Request $request)
    {
        $groups = config('features.groups', []);
        $validated = $request->validate([
            'group' => ['required', 'string', Rule::in(array_keys($groups))],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $updated = DB::transaction(fn () => FeatureSetting::query()
            ->whereIn('feature_code', $groups[$validated['group']])
            ->update([
                'is_enabled' => $validated['is_enabled'],
                'updated_at' => now(),
            ]));

        return response()->json([
            'success' => true,
            'message' => __('admin.features.group_updated_success'),
            'updated' => $updated,
        ]);
    }
}
