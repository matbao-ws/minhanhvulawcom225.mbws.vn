<?php

namespace App\Services;

use Spatie\Translatable\HasTranslations;

class LocalizedContent
{
    public function __construct(private readonly LanguageRegistry $languages)
    {
    }

    public function get(object $model, string $attribute, ?string $locale = null): mixed
    {
        $locale = $this->languages->resolve($locale ?: app()->getLocale());

        if (method_exists($model, 'getTranslation')) {
            return $model->getTranslation($attribute, $locale, false)
                ?: $model->getTranslation($attribute, $this->languages->fallbackLocale(), false)
                ?: collect($model->getTranslations($attribute))->first();
        }

        return $model->{$attribute};
    }

    /** @return array<string, mixed> */
    public function merge(object $model, string $attribute, mixed $submitted): array
    {
        $translations = method_exists($model, 'getTranslations') ? $model->getTranslations($attribute) : [];

        if (is_array($submitted)) {
            foreach ($submitted as $locale => $value) {
                if ($this->languages->supports($locale) && is_string($value) && trim($value) !== '') {
                    $translations[$locale] = trim($value);
                }
            }
        } elseif (is_string($submitted) && trim($submitted) !== '') {
            $translations[app()->getLocale()] = trim($submitted);
        }

        return array_filter($translations, fn ($value) => $value !== null && $value !== '');
    }
}
