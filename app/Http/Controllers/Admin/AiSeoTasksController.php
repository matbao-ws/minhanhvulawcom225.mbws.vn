<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\SeoTaskService;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "This week's SEO work": the ranked list (page), its top for the dashboard
 * card (JSON) and AI advice for one task. Route: feature:ai_seo + can:ai.use.
 */
class AiSeoTasksController extends Controller
{
    public function __construct(private readonly SeoTaskService $tasks) {}

    public function index(Request $request, AnalyticsReport $analytics): View
    {
        $connected = $analytics->settings()['gsc_site_url'] !== null;
        $weekly = null;
        $error = null;

        if ($connected) {
            try {
                $weekly = $this->tasks->forUser($request->user());
            } catch (GatewayUnavailable $e) {
                $error = $this->message($e);
            }
        }

        return view('admin.ai.seo-tasks.index', compact('connected', 'weekly', 'error'));
    }

    /** Totals and the first five tasks, for the dashboard card. */
    public function summary(Request $request): JsonResponse
    {
        try {
            $weekly = $this->tasks->forUser($request->user());
        } catch (GatewayUnavailable $e) {
            return response()->json(['success' => false, 'reason' => $e->reason, 'message' => $this->message($e)]);
        }

        return response()->json(['success' => true, 'data' => [
            'status' => $weekly['status'],
            'synced_through' => $weekly['synced_through'],
            'totals' => $weekly['totals'],
            'count' => count($weekly['tasks']),
            'tasks' => array_map(fn (array $task) => [
                'type' => $task['type'],
                'label' => __('ai.tasks.types.'.$task['type']),
                'title' => $task['entity']['title'] ?? $this->path($task['page'] ?? ''),
                'gain' => (int) ($task['gain'] ?? 0),
                'edit_url' => $task['edit_url'],
            ], array_slice($weekly['tasks'], 0, 5)),
        ]]);
    }

    public function advice(Request $request): JsonResponse
    {
        try {
            // By name: the {locale} prefix would fill a positional string parameter.
            $result = $this->tasks->advice((string) $request->route('task'));
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function message(GatewayUnavailable $e): string
    {
        $key = 'ai.tasks.errors.'.$e->reason;

        return __($key) !== $key ? __($key) : __('ai.errors.default');
    }

    private function path(string $url): string
    {
        return rawurldecode((string) parse_url($url, PHP_URL_PATH)) ?: $url;
    }
}
