<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSuggestion;
use App\Services\AiGateway\AiUsage;
use App\Services\AiGateway\AltTextService;
use App\Services\AiGateway\FaqService;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\InternalLinkService;
use App\Services\AiGateway\MetaSuggestionService;
use App\Services\AiGateway\ProductDescriptionService;
use App\Services\AiGateway\SeoFixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FAQ, alt text, SEO fixes and product descriptions. Route: feature:ai_seo +
 * can:ai.use; each service also requires the record's own update permission.
 * Every suggestion endpoint asks, every apply endpoint writes; rejects go
 * through the shared reject on each service.
 */
class AiToolsController extends Controller
{
    public function __construct(
        private readonly FaqService $faq,
        private readonly AltTextService $alt,
        private readonly SeoFixService $seo,
        private readonly ProductDescriptionService $product,
        private readonly InternalLinkService $links,
    ) {}

    public function faqSuggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate($this->recordRules(FaqService::ENTITY_TYPES));

        return $this->asked(fn () => $this->faq->suggest($data['entity_type'], (int) $data['entity_id'], $data['content_locale'], $request->user()),
            fn (AiSuggestion $s) => ['items' => $s->options], $locale, 'faq');
    }

    public function faqApply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:6'],
            'items.*.question' => ['required', 'string', 'max:200'],
            'items.*.answer' => ['required', 'string', 'max:600'],
        ]);
        $record = $this->faq->apply($suggestion, array_values($data['items']), $request->user());

        return $this->applied(__('ai.faq.applied'), $suggestion, $record->getTranslation(
            MetaSuggestionService::ENTITIES[$suggestion->entity_type]['content'], $suggestion->locale, false,
        ));
    }

    public function altMissing(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate($this->recordRules(AltTextService::ENTITY_TYPES));

        return response()->json(['success' => true, 'data' => [
            'images' => $this->alt->missing($data['entity_type'], (int) $data['entity_id'], $data['content_locale'], $request->user()),
        ]]);
    }

    public function altSuggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate($this->recordRules(AltTextService::ENTITY_TYPES) + ['src' => ['required', 'string', 'max:2048']]);

        return $this->asked(fn () => $this->alt->suggest($data['entity_type'], (int) $data['entity_id'], $data['content_locale'], $data['src'], $request->user()),
            fn (AiSuggestion $s) => ['alt' => $s->options[0]['alt'] ?? '', 'src' => $s->old_values['src']], $locale, 'alt');
    }

    public function altApply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate(['alt' => ['required', 'string', 'max:255']]);
        $record = $this->alt->apply($suggestion, trim($data['alt']), $request->user());

        return $this->applied(__('ai.alt.applied'), $suggestion, $record->getTranslation(
            MetaSuggestionService::ENTITIES[$suggestion->entity_type]['content'], $suggestion->locale, false,
        ));
    }

    public function seoSuggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate(['entity_id' => ['required', 'integer', 'min:1']]);

        try {
            [$suggestion, $usage, $analysis] = $this->seo->suggest((int) $data['entity_id'], $request->user());
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }
        AiUsage::remember($usage);

        return response()->json(['success' => true, 'data' => [
            'suggestion_id' => $suggestion->id,
            'current' => $suggestion->old_values,
            'proposal' => $suggestion->options[0],
            'score' => $analysis['score'],
            'usage' => $usage,
            'apply_url' => route('admin.ai.seo.apply', ['locale' => $locale, 'suggestion' => $suggestion->id]),
            'reject_url' => route('admin.ai.reject', ['locale' => $locale, 'suggestion' => $suggestion->id]),
        ]]);
    }

    public function seoApply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'intro_html' => ['nullable', 'string', 'max:20000'],
        ]);
        $scores = $this->seo->apply($suggestion, $data, $request->user());

        return response()->json(['success' => true, 'message' => __('ai.seo.applied', $scores), 'data' => $scores + ['reload' => true]]);
    }

    public function productSuggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'min:1'],
            'content_locale' => ['required', 'string', 'max:10'],
            'selling_points' => ['nullable', 'string', 'max:2000'],
            'target_words' => ['required', 'integer', 'min:'.ProductDescriptionService::MIN_WORDS, 'max:'.ProductDescriptionService::MAX_WORDS],
        ]);

        return $this->asked(fn () => $this->product->suggest((int) $data['entity_id'], $data['content_locale'], $data['selling_points'] ?? null, (int) $data['target_words'], $request->user()),
            fn (AiSuggestion $s) => $s->options[0], $locale, 'product');
    }

    public function productApply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $product = $this->product->apply($suggestion, $request->user());

        return $this->applied(__('ai.product.applied'), $suggestion, $product->getTranslation('description', $suggestion->locale, false));
    }

    public function linksSuggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate($this->recordRules(InternalLinkService::ENTITY_TYPES));

        return $this->asked(fn () => $this->links->suggest($data['entity_type'], (int) $data['entity_id'], $data['content_locale'], $request->user()),
            fn (AiSuggestion $s) => ['links' => $s->options], $locale, 'links');
    }

    public function linksApply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate(['chosen' => ['required', 'array', 'min:1', 'max:10'], 'chosen.*' => ['integer', 'min:0', 'max:20']]);
        [$record, $inserted] = $this->links->apply($suggestion, array_map('intval', $data['chosen']), $request->user());

        return $this->applied(__('ai.links.applied', ['count' => $inserted]), $suggestion, $record->getTranslation(
            MetaSuggestionService::ENTITIES[$suggestion->entity_type]['content'], $suggestion->locale, false,
        ));
    }

    /** Shared by every tool here: the record keeps its value, the gateway learns it was not used. */
    public function reject(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/']]);
        $service = match ($suggestion->task) {
            FaqService::TASK => $this->faq,
            AltTextService::TASK => $this->alt,
            SeoFixService::TASK => $this->seo,
            ProductDescriptionService::TASK => $this->product,
            InternalLinkService::TASK => $this->links,
            default => abort(404),
        };
        $service->reject($suggestion, $request->user(), $data['reason'] ?? null);

        return response()->json(['success' => true]);
    }

    private function recordRules(array $types): array
    {
        return [
            'entity_type' => ['required', Rule::in($types)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'content_locale' => ['required', 'string', 'max:10'],
        ];
    }

    /**
     * @param  \Closure(): array{0: AiSuggestion, 1: array}  $ask
     * @param  \Closure(AiSuggestion): array  $payload
     */
    private function asked(\Closure $ask, \Closure $payload, string $locale, string $tool): JsonResponse
    {
        try {
            [$suggestion, $usage] = $ask();
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }
        AiUsage::remember($usage);

        return response()->json(['success' => true, 'data' => $payload($suggestion) + [
            'suggestion_id' => $suggestion->id,
            'usage' => $usage,
            'apply_url' => route("admin.ai.$tool.apply", ['locale' => $locale, 'suggestion' => $suggestion->id]),
            'reject_url' => route('admin.ai.reject', ['locale' => $locale, 'suggestion' => $suggestion->id]),
        ]]);
    }

    private function applied(string $message, AiSuggestion $suggestion, ?string $html): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => [
            'html' => (string) $html,
            // Policy pages keep stored HTML out of the admin editor; the page reloads instead.
            'reload' => $suggestion->entity_type === 'page',
        ]]);
    }
}
