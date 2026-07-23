<?php

namespace App\Services\Translation;

use App\Contracts\TranslationProvider;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    public function __construct(
        private readonly TranslationProvider $provider,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $formats
     * @return array<string, string>
     */
    public function preview(array $fields, string $sourceLocale, string $targetLocale, array $formats = []): array
    {
        $result = [];
        $missing = ['text' => [], 'html' => []];

        foreach ($fields as $key => $value) {
            if (trim($value) === '') {
                $result[$key] = '';
                continue;
            }

            $format = ($formats[$key] ?? 'text') === 'html' ? 'html' : 'text';
            $cacheKey = $this->cacheKey($value, $sourceLocale, $targetLocale, $format);
            $cached = Cache::get($cacheKey);
            if (is_string($cached)) {
                $result[$key] = $cached;
            } else {
                $missing[$format][$key] = $value;
            }
        }

        foreach ($missing as $format => $values) {
            foreach (array_chunk($values, 128, true) as $chunk) {
                $translated = $this->provider->translate(array_values($chunk), $sourceLocale, $targetLocale, $format);
                foreach (array_keys($chunk) as $index => $key) {
                    $value = html_entity_decode((string) ($translated[$index] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($format === 'html') {
                        $value = $this->htmlSanitizer->clean($value);
                    }

                    $result[$key] = $value;
                    Cache::put(
                        $this->cacheKey($chunk[$key], $sourceLocale, $targetLocale, $format),
                        $value,
                        (int) config('multilingual.translation.cache_ttl', 86400),
                    );
                }
            }
        }

        return collect(array_keys($fields))->mapWithKeys(fn (string $key) => [$key => $result[$key] ?? ''])->all();
    }

    public function configured(): bool
    {
        return $this->provider->configured();
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    private function cacheKey(string $value, string $sourceLocale, string $targetLocale, string $format): string
    {
        return 'translation.preview.'.hash('sha256', implode('|', [$sourceLocale, $targetLocale, $format, $value]));
    }
}
