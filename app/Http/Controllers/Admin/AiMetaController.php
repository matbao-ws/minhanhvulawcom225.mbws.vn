<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSuggestion;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\MetaSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "AI meta suggestions" on the product, post and page forms: ask, then apply
 * one option (optionally edited) or reject them all. Route: feature:ai_seo +
 * can:ai.use; the service also requires the record's own update permission.
 */
class AiMetaController extends Controller
{
    public function __construct(private readonly MetaSuggestionService $suggestions) {}

    public function suggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(array_keys(MetaSuggestionService::ENTITIES))],
            'entity_id' => ['required', 'integer', 'min:1'],
            'content_locale' => ['required', 'string', 'max:10'],
        ]);

        try {
            [$suggestion, $usage] = $this->suggestions->suggest(
                $data['entity_type'], (int) $data['entity_id'], $data['content_locale'], $request->user(),
            );
        } catch (GatewayUnavailable $e) {
            return $this->unavailable($e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'suggestion_id' => $suggestion->id,
                'current' => $suggestion->old_values,
                'options' => $suggestion->options,
                'usage' => $usage,
                'apply_url' => route('admin.ai.meta.apply', ['locale' => $locale, 'suggestion' => $suggestion->id]),
                'reject_url' => route('admin.ai.meta.reject', ['locale' => $locale, 'suggestion' => $suggestion->id]),
            ],
        ]);
    }

    public function apply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate([
            'option' => ['required', 'integer', 'min:0', 'max:9'],
            'title' => ['required', 'string', 'max:'.MetaSuggestionService::TITLE_MAX],
            'description' => ['required', 'string', 'max:'.MetaSuggestionService::DESCRIPTION_MAX],
        ]);

        $this->suggestions->apply($suggestion, (int) $data['option'], trim($data['title']), trim($data['description']), $request->user());

        return response()->json([
            'success' => true,
            'message' => __('ai.meta.applied'),
            'data' => ['title' => trim($data['title']), 'description' => trim($data['description'])],
        ]);
    }

    public function reject(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/']]);

        $this->suggestions->reject($suggestion, $request->user(), $data['reason'] ?? null);

        return response()->json(['success' => true]);
    }

    /** Gateway reason code → message for the admin. Nothing from the gateway is shown verbatim. */
    private function unavailable(GatewayUnavailable $e): JsonResponse
    {
        $key = 'ai.errors.'.$e->reason;
        $status = match ($e->reason) {
            'quota_exceeded', 'cost_cap_reached', 'too_many_failures', 'rate_limited' => 429,
            'invalid_input', 'refused', 'input_too_large' => 422,
            default => 503,
        };

        return response()->json([
            'success' => false,
            'reason' => $e->reason,
            'message' => __($key) !== $key ? __($key) : __('ai.errors.default'),
        ], $status);
    }
}
