<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiBulkJob;
use App\Services\AiGateway\BulkSeoService;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\MetaSuggestionService;
use App\Services\LanguageRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Bulk SEO": count the work, estimate, run one gateway job per kind (meta,
 * SEO fixes, image alts), review the results with checkboxes, apply the
 * chosen ones. Route: feature:ai_seo + can:ai.use; each record also needs
 * its own update permission.
 */
class AiBulkController extends Controller
{
    public function __construct(
        private readonly BulkSeoService $bulk,
        private readonly LanguageRegistry $languages,
    ) {}

    public function index(Request $request): View
    {
        $contentLocale = $this->contentLocale($request);

        return view('admin.ai.bulk.index', [
            'contentLocale' => $contentLocale,
            'languages' => $this->languages->active(),
            'counts' => $this->bulk->counts($request->user(), $contentLocale),
            'jobs' => AiBulkJob::query()->latest('id')->limit(10)->get(),
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            return response()->json(['success' => true, 'data' => $this->bulk->estimate($data['kind'], $request->user(), $data['content_locale'], $data['types'] ?? [])]);
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $job = $this->bulk->start($data['kind'], $request->user(), $data['content_locale'], $data['types'] ?? []);
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

        return view('admin.ai.bulk.show', [
            'job' => $job,
            'error' => $error,
            'suggestions' => $job->imported_at ? $this->bulk->review($job) : collect(),
            'titles' => collect($job->items)->keyBy(fn ($item) => $item['suggestion_id'] ?? '-'),
        ]);
    }

    public function apply(Request $request, string $locale, AiBulkJob $job): RedirectResponse
    {
        $data = $request->validate([
            'chosen' => ['required', 'array', 'min:1', 'max:100'],
            'chosen.*' => ['integer'],
            'title.*' => ['nullable', 'string', 'max:'.MetaSuggestionService::TITLE_MAX],
            'description.*' => ['nullable', 'string', 'max:'.MetaSuggestionService::DESCRIPTION_MAX],
            'seo_title.*' => ['nullable', 'string', 'max:255'],
            'seo_description.*' => ['nullable', 'string', 'max:500'],
            'use_intro.*' => ['nullable', 'boolean'],
            'alt.*' => ['nullable', 'string', 'max:250'],
        ]);

        $fields = ['meta' => ['title', 'description'], 'seo_fix' => ['seo_title', 'seo_description', 'use_intro'], 'alt' => ['alt']][$job->kind()] ?? [];
        $chosen = [];
        foreach (array_map('intval', $data['chosen']) as $id) {
            $chosen[$id] = collect($fields)->mapWithKeys(fn ($field) => [$field => trim((string) ($data[$field][$id] ?? ''))])->all();
        }

        $counts = $this->bulk->apply($job, $chosen, $request->user());

        return redirect()->route('admin.ai.bulk.show', ['locale' => $locale, 'job' => $job->id])
            ->with($counts['conflicts'] + $counts['closed'] > 0 ? 'warning' : 'success', __('ai.bulk.applied', $counts));
    }

    /** @return array{kind: string, content_locale: string, types?: list<string>} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(BulkSeoService::KINDS))],
            'content_locale' => ['required', Rule::in($this->languages->codes())],
            'types' => ['required_if:kind,meta', 'array'],
            'types.*' => [Rule::in(array_keys(MetaSuggestionService::ENTITIES))],
        ]);
    }

    private function contentLocale(Request $request): string
    {
        $locale = (string) $request->query('content_locale', $this->languages->defaultLocale());

        return $this->languages->supports($locale) ? $locale : $this->languages->defaultLocale();
    }
}
