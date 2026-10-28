<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\SiteBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Sửa với AI" in the inline storefront editor: the AI rewrites a piece of text the admin selected.
 * It only answers; the editor puts the text into the region, where it waits for Lưu like any typing
 * (and is sanitized on save like any typing). Route: ai.access + can:ai.use + throttle:admin-ai.
 */
class AiRewriteController extends Controller
{
    public const TASK = 'content.rewrite';

    public const MODES = ['improve', 'shorten', 'expand', 'seo', 'friendly', 'professional'];

    public const KINDS = ['heading', 'paragraph', 'button', 'label', 'list'];

    public function __invoke(Request $request, GatewayClient $gateway, SiteBranding $branding): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'mode' => ['required', Rule::in(self::MODES)],
            'kind' => ['nullable', Rule::in(self::KINDS)],
            'page_title' => ['nullable', 'string', 'max:300'],
            'context' => ['nullable', 'string', 'max:1500'],
        ]);

        $text = trim(strip_tags($data['text']));
        abort_if($text === '', 422, __('ai.rewrite.empty'));

        try {
            $response = $gateway->task(self::TASK, array_filter([
                'locale' => app()->getLocale(),
                'text' => $text,
                'mode' => $data['mode'],
                'kind' => $data['kind'] ?? null,
                'page_title' => isset($data['page_title']) ? Str::limit(trim(strip_tags($data['page_title'])), 290, '') : null,
                'site_name' => Str::limit((string) ($branding->current()['name'] ?? ''), 120, '') ?: null,
                'context' => isset($data['context']) ? Str::limit(trim(strip_tags($data['context'])), 1400, '') : null,
            ], fn ($value) => $value !== null && $value !== ''), (string) Str::uuid());
        } catch (GatewayUnavailable $e) {
            return AiMetaController::unavailableResponse($e);
        }

        // Plain text only: whatever came back is shown as text, never as markup.
        $result = trim(strip_tags((string) ($response['result']['text'] ?? '')));
        abort_if($result === '', 502, __('ai.errors.default'));

        return response()->json(['success' => true, 'data' => ['text' => $result, 'usage' => (array) ($response['usage'] ?? [])]]);
    }
}
