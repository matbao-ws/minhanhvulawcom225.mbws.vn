<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * The SEO score of a post, the same checks as the post form's SEO box runs in the browser
 * (resources/views/admin/posts/_form-assets.blade.php, analyzeSEO): keep both in step, rule
 * names and weights alike. The weights add up to 100.
 */
class PostSeoAnalyzer
{
    public const WEIGHTS = [
        // Basic SEO
        'keyword_exists' => 4,
        'title_keyword' => 8,
        'desc_keyword' => 6,
        'slug_keyword' => 6,
        'first_paragraph' => 6,
        'content_length' => 8,
        // Additional
        'keyword_in_subheadings' => 5,
        'keyword_image_alt' => 4,
        'keyword_density' => 6,
        'url_length' => 3,
        'external_links' => 3,
        'internal_links' => 5,
        'image_alts' => 4,
        // Title & description
        'title_length' => 7,
        'desc_length' => 6,
        'title_keyword_start' => 4,
        'title_number' => 2,
        // Content readability
        'headings' => 4,
        'short_paragraphs' => 4,
        'content_media' => 5,
    ];

    /** A paragraph longer than this many words is hard to read on a phone. */
    private const LONG_PARAGRAPH_WORDS = 150;

    private const MAX_SLUG_LENGTH = 75;

    /**
     * @param  array{title?: string|null, slug?: string|null, content?: string|null, seo_title?: string|null, seo_description?: string|null, focus_keyword?: string|null}  $data
     * @return array{score: int, status: string, rules: array<string, bool>, metrics: array<string, int|float>}
     */
    public function analyze(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $content = (string) ($data['content'] ?? '');
        $seoTitle = trim((string) ($data['seo_title'] ?? '')) ?: $title;
        $seoDescription = trim((string) ($data['seo_description'] ?? ''));
        $keyword = $this->normalize(self::primaryKeyword($data['focus_keyword'] ?? null));
        $plainText = $this->plainText($content);
        $words = $this->words($plainText);
        $wordCount = count($words);
        $occurrences = $keyword === '' ? 0 : $this->occurrences($plainText, $keyword);
        $keywordWordCount = max(1, count($this->words($keyword)));
        $density = $wordCount > 0 ? round(($occurrences * $keywordWordCount / $wordCount) * 100, 2) : 0.0;
        $keywordSlug = Str::slug($keyword);
        $firstWords = implode(' ', array_slice($words, 0, 100));
        $keywordAt = $keyword === '' ? false : mb_strpos($this->normalize($seoTitle), $keyword);

        $rules = [
            'keyword_exists' => $keyword !== '',
            'title_length' => mb_strlen($seoTitle) >= 40 && mb_strlen($seoTitle) <= 60,
            'title_keyword' => $keyword !== '' && str_contains($this->normalize($seoTitle), $keyword),
            'slug_keyword' => $keywordSlug !== '' && str_contains(Str::slug($slug), $keywordSlug),
            'desc_length' => mb_strlen($seoDescription) >= 120 && mb_strlen($seoDescription) <= 160,
            'desc_keyword' => $keyword !== '' && str_contains($this->normalize($seoDescription), $keyword),
            'content_length' => $wordCount >= 300,
            'keyword_density' => $density >= 0.5 && $density <= 2.5,
            'first_paragraph' => $keyword !== '' && str_contains($this->normalize($firstWords), $keyword),
            'headings' => preg_match('/<h[23]\b/i', $content) === 1,
            'image_alts' => $this->allImagesHaveAlt($content),
            'internal_links' => $this->hasInternalLink($content),
            'keyword_in_subheadings' => $keyword !== '' && $this->subheadingsContain($content, $keyword),
            'keyword_image_alt' => $keyword !== '' && $this->anImageAltContains($content, $keyword),
            'url_length' => $slug !== '' && mb_strlen($slug) <= self::MAX_SLUG_LENGTH,
            'external_links' => $this->hasExternalLink($content),
            // In the first half of the title, where it weighs most and survives truncation.
            'title_keyword_start' => $keywordAt !== false && $keywordAt <= mb_strlen($seoTitle) / 2,
            'title_number' => preg_match('/\d/u', $seoTitle) === 1,
            'short_paragraphs' => $wordCount > 0 && $this->longestParagraph($content) <= self::LONG_PARAGRAPH_WORDS,
            'content_media' => preg_match('/<(img|iframe|video)\b/i', $content) === 1,
        ];

        $score = collect($rules)->reduce(
            fn (int $score, bool $passed, string $rule) => $score + ($passed ? self::WEIGHTS[$rule] : 0),
            0,
        );

        return [
            'score' => $score,
            'status' => $score >= 80 ? 'good' : ($score >= 50 ? 'ok' : 'needs_work'),
            'rules' => $rules,
            'metrics' => [
                'word_count' => $wordCount,
                'keyword_occurrences' => $occurrences,
                'keyword_density' => $density,
                'seo_title_length' => mb_strlen($seoTitle),
                'seo_description_length' => mb_strlen($seoDescription),
            ],
        ];
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/u', $this->normalize($text)) ?: [],
            fn (string $word) => $word !== '',
        ));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }

    /**
     * Keywords are entered comma-separated (badges in the post form); the first one is the
     * focus keyword that is scored, as in Rank Math. The others are secondary.
     */
    public static function primaryKeyword(?string $keywords): string
    {
        return trim(explode(',', (string) $keywords)[0]);
    }

    private function occurrences(string $text, string $keyword): int
    {
        return substr_count($this->normalize($text), $keyword);
    }

    private function allImagesHaveAlt(string $html): bool
    {
        if (preg_match('/<img\b/i', $html) !== 1) {
            return true;
        }

        preg_match_all('/<img\b[^>]*>/i', $html, $images);
        foreach ($images[0] as $image) {
            if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $image, $alt) !== 1 || trim($alt[2]) === '') {
                return false;
            }
        }

        return true;
    }

    private function subheadingsContain(string $html, string $keyword): bool
    {
        preg_match_all('/<h[2-4]\b[^>]*>(.*?)<\/h[2-4]>/is', $html, $headings);

        return collect($headings[1])->contains(fn (string $heading) => str_contains($this->normalize($this->plainText($heading)), $keyword));
    }

    private function anImageAltContains(string $html, string $keyword): bool
    {
        preg_match_all('/<img\b[^>]*\balt\s*=\s*(["\'])(.*?)\1/is', $html, $alts);

        return collect($alts[2])->contains(fn (string $alt) => str_contains($this->normalize(html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $keyword));
    }

    /** A link to another site: citing sources is a trust signal. */
    private function hasExternalLink(string $html): bool
    {
        preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/i', $html, $links);
        $own = array_map(fn (?string $host) => preg_replace('/^www\./', '', mb_strtolower((string) $host)), [
            parse_url((string) config('app.url'), PHP_URL_HOST),
            request()->getHost(),
        ]);

        foreach ($links[2] as $href) {
            $host = preg_replace('/^www\./', '', mb_strtolower((string) parse_url(trim($href), PHP_URL_HOST)));
            if ($host !== '' && preg_match('#^(https?:)?//#i', trim($href)) === 1 && ! in_array($host, $own, true)) {
                return true;
            }
        }

        return false;
    }

    private function longestParagraph(string $html): int
    {
        preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $paragraphs);
        $blocks = $paragraphs[1] ?: [$html];

        return (int) collect($blocks)->map(fn (string $block) => count($this->words($this->plainText($block))))->max();
    }

    private function hasInternalLink(string $html): bool
    {
        preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/i', $html, $links);
        $siteHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        foreach ($links[2] ?? [] as $href) {
            $href = trim($href);
            if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
                return true;
            }

            $host = parse_url($href, PHP_URL_HOST);
            if ($siteHost && $host && mb_strtolower($host) === mb_strtolower((string) $siteHost)) {
                return true;
            }
        }

        return false;
    }
}
