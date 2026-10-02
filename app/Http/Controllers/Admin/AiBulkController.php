<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiBulkJob;
use App\Services\AiGateway\BulkMetaService;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\MetaSuggestionService;
use App\Services\LanguageRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Bulk SEO": count what lacks meta, estimate, run one gateway job, review
 * the results with checkboxes, apply the chosen ones. Route: feature:ai_seo +
 * can:ai.use; each record also needs its own update permission.
 */
class AiBulkController extends Controller
{
    public function __construct(
        private readonly BulkMetaService $bulk,
        private readonly LanguageRegistry $languages,
    ) {}

    public function index(Request $request): View
    {
        $contentLocale = $this->contentLocale($request);
        $candidates = $this->bulk->candidates($request->user(), $contentLocale);
        $jobs = AiBulkJob::query()->latest('id')->limit(10)->get();

        return view('admin.ai.bulk.index', [
            'contentLocale' => $contentLocale,
            'languages' => $this->languages->active(),
            'counts' => array_map(fn ($records) => $records->count(), $candidates),
            'jobs' => $jobs,
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            return response()->json(['success' => true, 'data' => $this->bulk->estimate($request->user(), $data['content_locale'], $data['types'])]);
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $job = $this->bulk->start($request->user(), $data['content_locale'], $data['types']);
        } catch (GatewayUnavailable $e) {
            return back()->with('error', AiMetaController::unavailableResponse($e)->getData(true)['message']);
        }

        return redirect()->route('admin.ai.bulk.show', ['locale' => $locale, 'job' => $job->id])->with('success', __('ai.bulk.started'));
    }

    public function show(Request $request, string $locale, AiBulkJob $job): View
    {
        $error = null;
        try {
            $job = $this->bulk->refresh($job);
        } catch (GatewayUnavailable $e) {
            $error = AiMetaController::unavailableResponse($e)->getData(true)['message'];
        }

        $titles = collect($job->items)->keyBy(fn ($item) => $item['entity_type'].':'.$item['entity_id']);

        return view('admin.ai.bulk.show', [
            'job' => $job,
            'error' => $error,
            'suggestions' => $job->imported_at ? $this->bulk->review($job) : collect(),
            'titles' => $titles,
        ]);
    }

    public function apply(Request $request, string $locale, AiBulkJob $job): RedirectResponse
    {
        $data = $request->validate([
            'chosen' => ['required', 'array', 'min:1', 'max:100'],
            'chosen.*' => ['integer'],
            'title' => ['array'],
            'title.*' => ['nullable', 'string', 'max:'.MetaSuggestionService::TITLE_MAX],
            'description' => ['array'],
            'description.*' => ['nullable', 'string', 'max:'.MetaSuggestionService::DESCRIPTION_MAX],
        ]);

        $rows = array_map(fn ($id) => [
            'suggestion_id' => (int) $id,
            'title' => trim((string) ($data['title'][$id] ?? '')),
            'description' => trim((string) ($data['description'][$id] ?? '')),
        ], $data['chosen']);
        $rows = array_values(array_filter($rows, fn ($row) => $row['title'] !== '' && $row['description'] !== ''));

        $counts = $this->bulk->apply($job, $rows, $request->user());

        return redirect()->route('admin.ai.bulk.show', ['locale' => $locale, 'job' => $job->id])
            ->with($counts['conflicts'] + $counts['closed'] > 0 ? 'warning' : 'success', __('ai.bulk.applied', $counts));
    }

    /** @return array{content_locale: string, types: list<string>} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'content_locale' => ['required', Rule::in($this->languages->codes())],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(array_keys(MetaSuggestionService::ENTITIES))],
        ]);
    }

    private function contentLocale(Request $request): string
    {
        $locale = (string) $request->query('content_locale', $this->languages->defaultLocale());

        return $this->languages->supports($locale) ? $locale : $this->languages->defaultLocale();
    }
}
