<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AnalyticsUnavailable;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsReport $report) {}

    /**
     * Traffic report for the dashboard, loaded after the page so a slow Google
     * never holds up the dashboard itself. The range is one of Google
     * Analytics' presets (`range`) or a custom `from`/`to` (Y-m-d).
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'range' => ['nullable', Rule::in(AnalyticsReport::PRESETS)],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d'],
        ]);

        try {
            [$from, $to, $preset] = $this->report->range($request->input('range'), $request->input('from'), $request->input('to'));
        } catch (\InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'reason' => 'invalid_range',
                'message' => __('admin.analytics.invalid_range', ['days' => config('analytics.max_days')]),
            ], 422);
        }

        try {
            return response()->json(['success' => true, 'report' => $this->report->report($from, $to, $preset)]);
        } catch (AnalyticsUnavailable $e) {
            return response()->json(['success' => false, 'reason' => $e->reason, 'message' => $e->getMessage()]);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        $this->report->flush();

        return $this->show($request);
    }

    /** "Test connection" in Settings: reads the property name live. */
    public function check(): JsonResponse
    {
        try {
            $name = $this->report->checkConnection();

            return response()->json(['success' => true, 'message' => __('admin.analytics.connected', ['name' => $name])]);
        } catch (AnalyticsUnavailable $e) {
            return response()->json(['success' => false, 'reason' => $e->reason, 'message' => $e->getMessage()], 422);
        }
    }
}
