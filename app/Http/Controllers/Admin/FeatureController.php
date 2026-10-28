<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureSetting;
use App\Services\Packages\PackageInfo;
use App\Services\Packages\PackageSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function __construct(
        private readonly PackageSync $packages,
    ) {}

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
            'packageConfigured' => app(PackageInfo::class)->configured(),
        ]);
    }

    public function update(Request $request)
    {
        $features = $request->input('features', []);
        $limits = $request->input('limits', []);

        foreach (FeatureSetting::all() as $setting) {
            $code = $setting->feature_code;

            if (isset($features[$code])) {
                $limitValue = $limits[$code] ?? null;
                $setting->update(['limit_value' => $limitValue !== '' ? $limitValue : null]);
                $this->packages->setByAdmin($setting, $features[$code] == '1');
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
            $this->packages->setByAdmin($setting, (bool) $validated['is_enabled']);

            return response()->json([
                'success' => true,
                'message' => __('admin.features.updated_success'),
                'is_overridden' => $setting->is_overridden,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('admin.features.not_found'),
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
            ->get()
            ->each(fn (FeatureSetting $setting) => $this->packages->setByAdmin($setting, (bool) $validated['is_enabled']))
            ->count());

        return response()->json([
            'success' => true,
            'message' => __('admin.features.group_updated_success'),
            'updated' => $updated,
        ]);
    }

    public function syncPackage()
    {
        $result = $this->packages->sync();

        $message = match ($result['status']) {
            PackageInfo::OK => $result['package']['plan_known']
                ? __('admin.package.synced', ['package' => $result['package']['name']])
                : __('admin.package.synced_unknown_plan', ['code' => $result['package']['code']]),
            PackageInfo::NOT_FOUND => __('admin.package.not_found', ['domain' => config('packages.domain')]),
            default => __('admin.package.unreachable'),
        };

        return redirect()
            ->route('admin.features.index')
            ->with($result['status'] === PackageInfo::OK ? 'success' : 'error', $message);
    }

    public function resetOverrides(Request $request)
    {
        $validated = $request->validate([
            'feature_code' => ['nullable', 'string', Rule::in(config('features.codes', []))],
        ]);

        $this->packages->resetOverrides(filled($validated['feature_code'] ?? null) ? [$validated['feature_code']] : null);

        return redirect()
            ->route('admin.features.index')
            ->with('success', __('admin.package.overrides_reset'));
    }
}
