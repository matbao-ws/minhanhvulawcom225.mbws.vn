<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranslationRequest;
use App\Services\LanguageRegistry;
use App\Services\MultilingualSettings;
use App\Services\Translation\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class TranslationController extends Controller
{
    public function preview(
        Request $request,
        TranslationService $translations,
        LanguageRegistry $languages,
        MultilingualSettings $multilingual,
    ): JsonResponse
    {
        if (! $multilingual->usesManualContent()) {
            return response()->json([
                'message' => 'Dịch nội dung thủ công không hoạt động trong chế độ đa ngôn ngữ hiện tại.',
            ], 409);
        }

        $validated = $request->validate([
            'source_locale' => ['required', 'string', Rule::in($languages->codes())],
            'target_locale' => ['required', 'string', Rule::in($languages->codes()), 'different:source_locale'],
            'fields' => ['required', 'array', 'min:1', 'max:20'],
            'fields.*' => ['nullable', 'string', 'max:50000'],
            'formats' => ['nullable', 'array'],
            'formats.*' => ['nullable', Rule::in(['text', 'html'])],
        ]);

        if (! $translations->configured()) {
            return response()->json([
                'message' => 'Dịch tự động hiện chưa được cấu hình trên máy chủ.',
            ], 503);
        }

        $fields = collect($validated['fields'])
            ->map(fn ($value) => is_string($value) ? $value : '')
            ->all();
        $characterCount = collect($fields)->sum(fn (string $value) => mb_strlen($value));
        $dailyLimit = (int) config('multilingual.translation.daily_character_limit', 500000);
        $usedToday = TranslationRequest::query()
            ->where('user_id', $request->user()?->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('character_count');

        if ($characterCount > 100000 || $usedToday + $characterCount > $dailyLimit) {
            return response()->json([
                'message' => 'Đã vượt giới hạn ký tự dịch tự động. Vui lòng thử lại sau hoặc dịch thủ công.',
            ], 429);
        }

        $audit = TranslationRequest::query()->create([
            'user_id' => $request->user()?->id,
            'provider' => $translations->providerName(),
            'source_locale' => $validated['source_locale'],
            'target_locale' => $validated['target_locale'],
            'character_count' => $characterCount,
            'source_hash' => hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'status' => 'pending',
        ]);

        try {
            $result = $translations->preview(
                $fields,
                $validated['source_locale'],
                $validated['target_locale'],
                $validated['formats'] ?? [],
            );
            $audit->update(['status' => 'succeeded']);

            return response()->json(['data' => ['fields' => $result]]);
        } catch (Throwable $exception) {
            $errorCode = str($exception->getMessage())->limit(80, '')->toString();
            $audit->update(['status' => 'failed', 'error_code' => $errorCode]);

            report($exception);

            return response()->json([
                'message' => 'Google Translate chưa thể xử lý yêu cầu. Vui lòng thử lại sau.',
            ], 502);
        }
    }
}
