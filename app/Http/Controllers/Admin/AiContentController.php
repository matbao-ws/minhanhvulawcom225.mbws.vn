<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSuggestion;
use App\Services\AiGateway\ContentEditService;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\MetaSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "AI copy edit" beside the rich-text editors of products, posts and policy
 * pages. Route: feature:ai_seo + can:ai.use; the service also requires the
 * record's own update permission.
 */
class AiContentController extends Controller
{
    public function __construct(private readonly ContentEditService $edits) {}

    public function suggest(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(array_keys(MetaSuggestionService::ENTITIES))],
            'entity_id' => ['required', 'integer', 'min:1'],
            'content_locale' => ['required', 'string', 'max:10'],
            'level' => ['required', Rule::in(ContentEditService::LEVELS)],
        ]);

        try {
            [$suggestion, $usage] = $this->edits->suggest(
                $data['entity_type'], (int) $data['entity_id'], $data['content_locale'], $data['level'], $request->user(),
            );
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'suggestion_id' => $suggestion->id,
                // Already sanitized with the record's own sanitizer.
                'html' => $suggestion->options[0]['html'],
                'changes' => $suggestion->options[0]['changes'],
                'usage' => $usage,
                'apply_url' => route('admin.ai.content.apply', ['locale' => $locale, 'suggestion' => $suggestion->id]),
                'reject_url' => route('admin.ai.content.reject', ['locale' => $locale, 'suggestion' => $suggestion->id]),
            ],
        ]);
    }

    public function apply(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $this->edits->apply($suggestion, $request->user());

        return response()->json([
            'success' => true,
            'message' => __('ai.edit.applied'),
            'data' => ['html' => $suggestion->options[0]['html'], 'reload' => $suggestion->entity_type === 'page'],
        ]);
    }

    public function reject(Request $request, string $locale, AiSuggestion $suggestion): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/']]);

        $this->edits->reject($suggestion, $request->user(), $data['reason'] ?? null);

        return response()->json(['success' => true]);
    }
}
