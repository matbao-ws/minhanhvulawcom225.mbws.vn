<?php

namespace App\Support;

/**
 * Site settings a shop writes once per language: the home page SEO title/description, the head
 * office address, and each branch's name and address. Phone, email and map links are the same in
 * every language and stay single.
 *
 * Stored expand-only: the default language keeps its old place (`seo.title`, `contact.address`,
 * `contact.branches.0.name`), so code from before translations still reads it; the other languages
 * sit beside it under `translations` => [locale => [field => text]]. Reading for a language with no
 * text of its own falls back to the default language, like any other content.
 */
final class LocalizedSiteSettings
{
    public const SEO_FIELDS = ['title', 'description'];

    public const CONTACT_FIELDS = ['address'];

    public const BRANCH_FIELDS = ['name', 'address'];

    /**
     * The shop name is a plain string under `shop_name`, which older code reads as is, so its other
     * languages live under a key of their own: [locale => name].
     */
    public const SHOP_NAME_TRANSLATIONS = 'shop_name_translations';

    /** The shop name as $locale reads it: its own name when it has one, the default otherwise. */
    public static function shopName(?string $default, mixed $translations, string $locale): ?string
    {
        $name = is_array($translations) ? ($translations[$locale] ?? null) : null;

        return is_string($name) && trim($name) !== '' ? $name : $default;
    }

    /** The shop name translations to store; languages the form did not show keep their name. */
    public static function storeShopName(mixed $submitted, mixed $stored, array $locales): array
    {
        $submitted = is_array($submitted) ? $submitted : [];
        $names = array_diff_key(is_array($stored) ? $stored : [], array_flip($locales));

        foreach ($locales as $locale) {
            $name = $submitted[$locale] ?? null;
            if (is_string($name) && trim($name) !== '') {
                $names[$locale] = trim($name);
            }
        }
        ksort($names);

        return $names;
    }

    /** The SEO setting as $locale reads it, without the translations map. */
    public static function seo(mixed $seo, string $locale): array
    {
        return self::localize(is_array($seo) ? $seo : [], self::SEO_FIELDS, $locale);
    }

    /** The contact setting as $locale reads it, branches included, without translations maps. */
    public static function contact(mixed $contact, string $locale): array
    {
        $contact = self::localize(is_array($contact) ? $contact : [], self::CONTACT_FIELDS, $locale);

        if (is_array($contact['branches'] ?? null)) {
            $contact['branches'] = array_map(
                fn (mixed $branch) => is_array($branch) ? self::localize($branch, self::BRANCH_FIELDS, $locale) : $branch,
                $contact['branches'],
            );
        }

        return $contact;
    }

    /**
     * The SEO setting to store from the admin form. $locales are the non-default languages the form
     * showed; a language the form did not show (multilingual switched off, language deactivated)
     * keeps the text it had.
     */
    public static function storeSeo(array $submitted, mixed $stored, array $locales): array
    {
        return self::store($submitted, is_array($stored) ? $stored : [], self::SEO_FIELDS, $locales);
    }

    public static function storeContact(array $submitted, mixed $stored, array $locales): array
    {
        $stored = is_array($stored) ? $stored : [];
        $contact = self::store($submitted, $stored, self::CONTACT_FIELDS, $locales);
        $storedBranches = array_values(is_array($stored['branches'] ?? null) ? $stored['branches'] : []);

        if (is_array($contact['branches'] ?? null)) {
            $branches = array_values($contact['branches']);
            $contact['branches'] = array_map(function (mixed $branch, int $index) use ($storedBranches, $locales) {
                if (! is_array($branch)) {
                    return $branch;
                }
                // Branches are a list the form renumbers: carry a hidden language over only to the
                // same branch, recognised by its default-language name.
                $previous = $storedBranches[$index] ?? [];
                $same = is_array($previous) && ($previous['name'] ?? null) === ($branch['name'] ?? null);

                return self::store($branch, $same ? $previous : [], self::BRANCH_FIELDS, $locales);
            }, $branches, array_keys($branches));
        }

        return $contact;
    }

    private static function localize(array $value, array $fields, string $locale): array
    {
        $translations = is_array($value['translations'] ?? null) ? $value['translations'] : [];
        unset($value['translations']);

        foreach ($fields as $field) {
            $text = $translations[$locale][$field] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $value[$field] = $text;
            }
        }

        return $value;
    }

    private static function store(array $submitted, array $stored, array $fields, array $locales): array
    {
        $sent = is_array($submitted['translations'] ?? null) ? $submitted['translations'] : [];
        $kept = is_array($stored['translations'] ?? null) ? $stored['translations'] : [];
        unset($submitted['translations']);

        $translations = array_diff_key($kept, array_flip($locales));
        foreach ($locales as $locale) {
            foreach ($fields as $field) {
                $text = $sent[$locale][$field] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $translations[$locale][$field] = trim($text);
                }
            }
        }

        if ($translations !== []) {
            ksort($translations);
            $submitted['translations'] = $translations;
        }

        return $submitted;
    }
}
