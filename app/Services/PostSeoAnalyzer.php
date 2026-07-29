<?php

namespace App\Services;

use Illuminate\Support\Str;

class PostSeoAnalyzer
{
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
        $keyword = $this->normalize((string) ($data['focus_keyword'] ?? ''));
        $plainText = $this->plainText($content);
        $words = $this->words($plainText);
        $wordCount = count($words);
        $occurrences = $keyword === '' ? 0 : $this->occurrences($plainText, $keyword);
        $keywordWordCount = max(1, count($this->words($keyword)));
        $density = $wordCount > 0 ? round(($occurrences * $keywordWordCount / $wordCount) * 100, 2) : 0.0;
        $keywordSlug = Str::slug($keyword);
        $firstWords = implode(' ', array_slice($words, 0, 100));

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
        ];

        $weights = [
            'keyword_exists' => 5,
            'title_length' => 10,
            'title_keyword' => 10,
            'slug_keyword' => 10,
            'desc_length' => 10,
            'desc_keyword' => 10,
            'content_length' => 10,
            'keyword_density' => 10,
            'first_paragraph' => 10,
            'headings' => 5,
            'image_alts' => 5,
            'internal_links' => 5,
        ];

        $score = collect($rules)->reduce(
            fn (int $score, bool $passed, string $rule) => $score + ($passed ? $weights[$rule] : 0),
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
