<?php

namespace App\Services;

use App\Services\Seo\RedirectService;
use Illuminate\Support\Str;

/**
 * The SEO a WordPress post had in Rank Math or Yoast, in this site's terms: the title and
 * description Google showed, the focus keywords, the canonical and robots choices.
 *
 * Read from a WXR export's post meta (titles may be templates such as "%title% %sep% %sitename%",
 * resolved here) or from what the REST API renders (`yoast_head_json`, or Rank Math's headless
 * `getHead`), which is already final text.
 */
class WordPressSeoMeta
{
    /**
     * @param  array<string, string>  $meta  post meta, key => value
     * @param  array{title?: string, excerpt?: string, site_name?: string, site_description?: string, category?: string, date?: string, link?: string}  $context
     * @return array{title: string, description: string, keywords: string, canonical: ?string, index: bool, follow: bool}
     */
    public function fromPostMeta(array $meta, array $context): array
    {
        $keywords = $this->keywords($this->first($meta, ['rank_math_focus_keyword', '_yoast_wpseo_focuskw', '_yoast_wpseo_focuskw_text_input']));
        $context['focuskw'] = trim(explode(',', $keywords)[0]);

        // Rank Math keeps robots as a serialised list ("noindex", "nofollow"…): read the words,
        // never unserialize() an export. Yoast stores '1' for "no" ('2' means an explicit yes).
        $robots = strtolower((string) ($meta['rank_math_robots'] ?? ''));

        return [
            'title' => $this->resolve($this->first($meta, ['rank_math_title', '_yoast_wpseo_title']), $context),
            'description' => $this->resolve($this->first($meta, ['rank_math_description', '_yoast_wpseo_metadesc']), $context),
            'keywords' => $keywords,
            'canonical' => $this->canonical($this->first($meta, ['rank_math_canonical_url', '_yoast_wpseo_canonical']), (string) ($context['link'] ?? '')),
            'index' => ! str_contains($robots, '"noindex"') && trim((string) ($meta['_yoast_wpseo_meta-robots-noindex'] ?? '')) !== '1',
            'follow' => ! str_contains($robots, '"nofollow"') && trim((string) ($meta['_yoast_wpseo_meta-robots-nofollow'] ?? '')) !== '1',
        ];
    }

    /**
     * From rendered head data: Yoast's `yoast_head_json`, or parseHead() of Rank Math's getHead.
     *
     * @param  array<string, mixed>  $head
     * @return array{title: string, description: string, keywords: string, canonical: ?string, index: bool, follow: bool}
     */
    public function fromHead(array $head, string $link): array
    {
        $robots = strtolower((string) json_encode($head['robots'] ?? ''));

        return [
            'title' => $this->clean((string) ($head['title'] ?? '')),
            'description' => $this->clean((string) ($head['description'] ?? '')),
            'keywords' => '',
            'canonical' => $this->canonical((string) ($head['canonical'] ?? ''), $link),
            'index' => ! str_contains($robots, 'noindex'),
            'follow' => ! str_contains($robots, 'nofollow'),
        ];
    }

    /**
     * The tags of a rendered <head> (Rank Math's /rankmath/v1/getHead) in yoast_head_json's shape.
     *
     * @return array{title: string, description: string, canonical: string, robots: string}
     */
    public function parseHead(string $html): array
    {
        $attribute = function (string $pattern) use ($html): string {
            return preg_match($pattern, $html, $match) === 1 ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        };

        return [
            'title' => $attribute('#<title[^>]*>(.*?)</title>#is'),
            'description' => $attribute('#<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)["\']#i'),
            'canonical' => $attribute('#<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']*)["\']#i'),
            'robots' => $attribute('#<meta\s+name=["\']robots["\']\s+content=["\']([^"\']*)["\']#i'),
        ];
    }

    /**
     * Rank Math (%var%) and Yoast (%%var%%) variables replaced by the post's values; unknown
     * ones dropped, so no "%sitename%" ever reaches a search result.
     *
     * @param  array<string, string>  $context
     */
    public function resolve(string $template, array $context): string
    {
        if (trim($template) === '') {
            return '';
        }

        $date = $this->date((string) ($context['date'] ?? ''));
        $values = [
            'title' => $context['title'] ?? '',
            'sitename' => $context['site_name'] ?? '',
            'sitedesc' => $context['site_description'] ?? '',
            'sep' => '-',
            'excerpt' => $context['excerpt'] ?? '',
            'excerpt_only' => $context['excerpt'] ?? '',
            'category' => $context['category'] ?? '',
            'primary_category' => $context['category'] ?? '',
            'focuskw' => $context['focuskw'] ?? '',
            'date' => $date,
            'modified' => $date,
            'currentyear' => now()->format('Y'),
            'currentmonth' => now()->format('m'),
            'currentday' => now()->format('d'),
            'currentdate' => now()->format('d/m/Y'),
        ];
        $lookup = fn (array $match): string => (string) ($values[strtolower($match[1])] ?? '');

        $text = (string) preg_replace_callback('/%%([a-z0-9_]+)%%/i', $lookup, $template);
        $text = (string) preg_replace_callback('/%([a-z][a-z0-9_]*)(?:\([^)%]*\))?%/i', $lookup, $text);

        return $this->clean($text);
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        // A variable that was empty leaves "Title - - Site" or "- Site": tidy the separators.
        $text = (string) preg_replace('/(\s*[-|–—•·]\s*){2,}/u', ' - ', $text);

        return trim($text, " \t-|–—•·:");
    }

    /** "a, b,  a" → "a, b": the post form's badges, first one scored. */
    private function keywords(string $value): string
    {
        $words = collect(explode(',', $value))
            ->map(fn (string $word) => trim((string) preg_replace('/\s+/u', ' ', $word)))
            ->filter()
            ->unique(fn (string $word) => mb_strtolower($word))
            ->values();

        return Str::limit($words->implode(', '), 255, '');
    }

    /** A canonical pointing at the post itself says nothing once the post moves: dropped. */
    private function canonical(string $canonical, string $link): ?string
    {
        $canonical = trim($canonical);
        if ($canonical === '' || filter_var($canonical, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($canonical, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }
        if ($link !== '' && RedirectService::normalize($canonical) === RedirectService::normalize($link)) {
            return null;
        }

        return Str::limit($canonical, 2048, '');
    }

    /** @param list<string> $keys */
    private function first(array $meta, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function date(string $value): string
    {
        $time = strtotime($value);

        return $time === false || str_starts_with($value, '0000') ? '' : date('d/m/Y', $time);
    }
}
