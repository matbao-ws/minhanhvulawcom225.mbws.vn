<?php

namespace App\Contracts;

interface TranslationProvider
{
    /**
     * @param list<string> $texts
     * @return list<string>
     */
    public function translate(array $texts, string $sourceLocale, string $targetLocale, string $format = 'text'): array;

    public function configured(): bool;

    public function name(): string;
}
