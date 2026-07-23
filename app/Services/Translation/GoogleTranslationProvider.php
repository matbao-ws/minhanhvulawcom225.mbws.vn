<?php

namespace App\Services\Translation;

use App\Contracts\TranslationProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoogleTranslationProvider implements TranslationProvider
{
    public function translate(array $texts, string $sourceLocale, string $targetLocale, string $format = 'text'): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('translation_provider_not_configured');
        }

        $translations = [];

        foreach (array_values($texts) as $text) {
            if ($text === '') {
                $translations[] = '';

                continue;
            }

            try {
                $response = Http::acceptJson()
                    ->asForm()
                    ->timeout((int) config('multilingual.translation.google.timeout', 12))
                    ->retry(2, 250, throw: false)
                    ->withQueryParameters([
                        'client' => 'gtx',
                        'dt' => 't',
                        'ie' => 'UTF-8',
                        'oe' => 'UTF-8',
                        'sl' => $sourceLocale,
                        'tl' => $targetLocale,
                    ])
                    ->post(config('multilingual.translation.google.endpoint'), [
                        'q' => $text,
                    ]);
            } catch (Throwable) {
                throw new RuntimeException('translation_provider_connection_failed');
            }

            if (! $response->successful()) {
                throw new RuntimeException('translation_provider_http_'.$response->status());
            }

            $segments = $response->json('0');

            if (! is_array($segments)) {
                throw new RuntimeException('translation_provider_invalid_response');
            }

            $translatedText = collect($segments)
                ->filter(fn ($segment) => is_array($segment) && array_key_exists(0, $segment))
                ->map(fn (array $segment) => (string) $segment[0])
                ->implode('');

            if ($translatedText === '') {
                throw new RuntimeException('translation_provider_invalid_response');
            }

            $translations[] = $translatedText;
        }

        return $translations;
    }

    public function configured(): bool
    {
        return filled(config('multilingual.translation.google.endpoint'));
    }

    public function name(): string
    {
        return 'google-unofficial';
    }
}
