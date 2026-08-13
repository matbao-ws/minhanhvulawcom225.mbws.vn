<?php

namespace App\Services;

class WordPressContentNormalizer
{
    /**
     * Reproduce the useful part of WordPress wpautop() before sanitizing WXR
     * content. WordPress exports often contain plain text separated by blank
     * lines and relies on wpautop at render time to create paragraphs.
     */
    public function normalize(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", trim($html));
        if ($html === '') {
            return '';
        }

        $html = $this->normalizeHtml5Blocks($html);

        $blocks = '(?:address|article|aside|blockquote|details|div|dl|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|main|nav|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul|li)';
        $html = preg_replace('!(<'.$blocks.'[\s/>])!i', "\n\n$1", $html) ?? $html;
        $html = preg_replace('!(</'.$blocks.'>)!i', "$1\n\n", $html) ?? $html;
        $html = preg_replace("/\n\n+/", "\n\n", $html) ?? $html;
        $chunks = array_values(array_filter(array_map(
            'trim',
            preg_split('/\n\s*\n/', trim($html)) ?: [],
        ), fn (string $chunk): bool => $chunk !== ''));

        $html = '<p>'.implode("</p>\n<p>", array_map($this->lineBreaks(...), $chunks)).'</p>';
        $html = preg_replace('|<p>\s*</p>|', '', $html) ?? $html;
        $html = preg_replace('!<p>\s*(</?'.$blocks.'[^>]*>)\s*</p>!i', '$1', $html) ?? $html;
        $html = preg_replace('!<p>\s*(</?'.$blocks.'[^>]*>)!i', '$1', $html) ?? $html;
        $html = preg_replace('!(</?'.$blocks.'[^>]*>)\s*</p>!i', '$1', $html) ?? $html;
        $html = preg_replace('!<p>(<li\b.+?)</p>!is', '$1', $html) ?? $html;
        $html = preg_replace('!<p><blockquote([^>]*)>!i', '<blockquote$1><p>', $html) ?? $html;
        $html = str_replace('</blockquote></p>', '</p></blockquote>', $html);

        return trim($html);
    }

    private function normalizeHtml5Blocks(string $html): string
    {
        $html = preg_replace_callback('/<figure\b([^>]*)>/i', function (array $matches): string {
            $alignment = str_contains($matches[1], 'alignleft')
                ? ' text-start'
                : (str_contains($matches[1], 'alignright') ? ' text-end' : ' text-center');

            return '<div class="wp-figure mb-3'.$alignment.'">';
        }, $html) ?? $html;
        $html = preg_replace('/<\/figure>/i', '</div>', $html) ?? $html;
        $html = preg_replace('/<figcaption\b[^>]*>/i', '<p class="wp-caption text-muted text-center mt-2">', $html) ?? $html;
        $html = preg_replace('/<\/figcaption>/i', '</p>', $html) ?? $html;
        $html = preg_replace('/<section\b[^>]*>/i', '<div>', $html) ?? $html;
        $html = preg_replace('/<\/section>/i', '</div>', $html) ?? $html;
        $html = preg_replace('/<table\b[^>]*>/i', '<table class="table table-bordered">', $html) ?? $html;
        $html = preg_replace_callback('/<img\b([^>]*)>/i', function (array $matches): string {
            $attributes = preg_replace('/\s+class\s*=\s*(["\']).*?\1/is', '', $matches[1]) ?? $matches[1];

            return '<img class="img-fluid"'.$attributes.'>';
        }, $html) ?? $html;

        return $html;
    }

    private function lineBreaks(string $html): string
    {
        if (preg_match('/<(?:script|style|pre)\b/i', $html)) {
            return $html;
        }

        return preg_replace('/\n+/', "<br>\n", $html) ?? $html;
    }
}
