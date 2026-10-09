<?php

namespace App\Support\Seo;

use Illuminate\Database\Eloquent\Model;

/**
 * The languages a record has its own text in, judged by its title (or name).
 *
 * A record opened in another language shows the fallback language's text. Declaring that URL
 * as a translation (hreflang, sitemap) tells search engines the site has a Japanese page that is
 * really the Vietnamese one; SeoMetaFactory and SitemapService both ask here so they agree.
 */
final class RecordLocales
{
    /**
     * @param  list<string>  $codes  the site's languages
     * @return list<string> never empty: a record without any title text still has its fallback
     */
    public static function of(Model $model, array $codes, string $fallback): array
    {
        if (! method_exists($model, 'getTranslatableAttributes')) {
            return $codes;
        }

        $attributes = $model->getTranslatableAttributes();
        $field = in_array('title', $attributes, true) ? 'title' : (in_array('name', $attributes, true) ? 'name' : null);
        if ($field === null) {
            return $codes;
        }

        $texts = $model->getTranslations($field);
        $own = array_values(array_filter($codes, fn (string $code): bool => trim((string) ($texts[$code] ?? '')) !== ''));

        return $own !== [] ? $own : [in_array($fallback, $codes, true) ? $fallback : $codes[0]];
    }

    /** Where search engines should look for a record opened in a language it has no text in. */
    public static function canonicalLocale(string $current, array $own, string $fallback): string
    {
        if (in_array($current, $own, true)) {
            return $current;
        }

        return in_array($fallback, $own, true) ? $fallback : $own[0];
    }
}
