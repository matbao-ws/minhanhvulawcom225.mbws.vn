<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\SeoTaskFixer;
use App\Services\AiGateway\SeoTaskService;
use App\Services\Analytics\AnalyticsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "This week's SEO work": the ranked list (page), its top for the dashboard
 * card (JSON), AI advice for one task, the AI fix (one step at a time, then
 * apply) and the before/after results of fixed tasks. Route: feature:ai_seo + can:ai.use.
 */
class AiSeoTasksController extends Controller
{
    public function __construct(
        private readonly SeoTaskService $tasks,
        private readonly SeoTaskFixer $fixer,
    ) {}

    public function index(Request $request, AnalyticsReport $analytics): View
    {
        $connected = $analytics->settings()['gsc_site_url'] !== null;
        $weekly = null;
        $results = null;
        $error = null;

        if ($connected) {
            try {
                $weekly = $this->tasks->forUser($request->user());
                $weekly['tasks'] = array_map(fn (array $task) => $task + ['fix' => $this->fixer->plan($task)], $weekly['tasks']);
            } catch (GatewayUnavailable $e) {
                $error = $this->message($e);
            }

            try {
                $results = $this->tasks->outcomes();
            } catch (GatewayUnavailable) {
                // A gateway without results yet: the list still works, the results tab says so.
            }
        }

        $canApply = (bool) $request->user()?->can('ai.apply');

        return view('admin.ai.seo-tasks.index', compact('connected', 'weekly', 'results', 'error', 'canApply'));
    }

    /** Totals and the first five tasks, for the dashboard card. */
    public function summary(Request $request, AnalyticsReport $analytics): JsonResponse
    {
        // The local setting is authoritative. Do not let a cached gateway result
        // keep old Search Console figures visible after the connection is removed.
        if ($analytics->settings()['gsc_site_url'] === null) {
            return response()->json(['success' => true, 'data' => [
                'status' => 'not_connected',
                'synced_through' => null,
                'totals' => null,
                'count' => 0,
                'tasks' => [],
            ]]);
        }

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
                'priority' => $task['priority'] ?? null,
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

    /** One step of the AI fix: the suggestion the review shows. Nothing is written yet. */
    public function fixStep(Request $request): JsonResponse
    {
        $step = (string) $request->route('step');
        abort_unless(in_array($step, SeoTaskFixer::STEPS, true), 404);

        try {
            $result = $this->fixer->suggest((string) $request->route('task'), $step, $request->user());
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** Write what the owner kept from the review and start measuring the result. */
    public function fixApply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:'.count(SeoTaskFixer::STEPS)],
            'changes.*.step' => ['required', 'string', 'in:'.implode(',', SeoTaskFixer::STEPS)],
            'changes.*.suggestion_id' => ['nullable', 'integer'],
            'changes.*.option' => ['nullable', 'integer', 'min:0', 'max:5'],
            'changes.*.title' => ['nullable', 'string', 'max:300'],
            'changes.*.description' => ['nullable', 'string', 'max:600'],
            'changes.*.section' => ['nullable', 'array'],
            'changes.*.section.heading' => ['nullable', 'string', 'max:300'],
            'changes.*.section.paragraphs' => ['nullable', 'array', 'max:6'],
            'changes.*.section.paragraphs.*' => ['nullable', 'string', 'max:2000'],
            'changes.*.section.bullets' => ['nullable', 'array', 'max:6'],
            'changes.*.section.bullets.*' => ['nullable', 'string', 'max:500'],
            'changes.*.items' => ['nullable', 'array', 'max:10'],
            'changes.*.items.*.suggestion_id' => ['nullable', 'integer'],
            'changes.*.items.*.alt' => ['nullable', 'string', 'max:300'],
            'changes.*.items.*.question' => ['nullable', 'string', 'max:300'],
            'changes.*.items.*.answer' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->fixer->apply((string) $request->route('task'), $data['changes'], $request->user());
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }

        $ok = $result['applied'] !== [];

        return response()->json([
            'success' => $ok,
            'message' => $ok ? __('ai.tasks.fix.applied', ['date' => now()->addDays(28)->format('d/m/Y')]) : ($result['failed'][0]['message'] ?? __('ai.errors.default')),
            'data' => $result + ['ready_on' => now()->addDays(28)->toDateString()],
        ], $ok ? 200 : 422);
    }

    public function mark(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:done,dismissed,open']]);

        try {
            // By name, for the same reason as advice().
            $this->tasks->mark((string) $request->route('task'), $data['status']);
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }

        return response()->json(['success' => true, 'message' => __('ai.tasks.marked.'.$data['status'])]);
    }

    private function message(GatewayUnavailable $e): string
    {
        // This page's own wording first, then the general AI one (credits used up, AI off…).
        foreach (['ai.tasks.errors.'.$e->reason, 'ai.errors.'.$e->reason] as $key) {
            if (__($key) !== $key) {
                return __($key);
            }
        }

        return __('ai.errors.default');
    }

    private function path(string $url): string
    {
        return rawurldecode((string) parse_url($url, PHP_URL_PATH)) ?: $url;
    }
}
