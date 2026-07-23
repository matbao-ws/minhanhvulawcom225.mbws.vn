<?php

namespace App\Http\Requests\Concerns;

use App\Services\LanguageRegistry;

trait NormalizesLocalizedInput
{
    /** @param list<string> $fields */
    protected function normalizeLocalizedInput(array $fields): void
    {
        $registry = app(LanguageRegistry::class);
        $locale = $registry->resolve((string) ($this->route('locale') ?: app()->getLocale()));
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = [$locale => $value];
                if ($locale !== $registry->defaultLocale()) {
                    $normalized[$field][$registry->defaultLocale()] = $value;
                }
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /** @return array<string, array<int, string>> */
    protected function localizedStringRules(string $field, bool $requiredInDefault = false, ?int $max = null): array
    {
        $registry = app(LanguageRegistry::class);
        $rules = [$field => [$requiredInDefault ? 'required' : 'nullable', 'array']];

        foreach ($registry->codes() as $locale) {
            $fieldRules = [$requiredInDefault && $locale === $registry->defaultLocale() ? 'required' : 'nullable', 'string'];
            if ($max !== null) {
                $fieldRules[] = 'max:'.$max;
            }
            $rules[$field.'.'.$locale] = $fieldRules;
        }

        return $rules;
    }
}
