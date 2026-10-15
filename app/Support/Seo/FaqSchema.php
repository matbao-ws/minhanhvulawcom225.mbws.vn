<?php

namespace App\Support\Seo;

use DOMDocument;
use DOMElement;

/**
 * The questions and answers of a post's FAQ section, for FAQPage structured data. The section is
 * what the AI "FAQ from this content" tool appends (an <h2> "Câu hỏi thường gặp" / "Frequently
 * asked questions", then <h3> question + answer paragraphs), or the same shape written by hand.
 */
final class FaqSchema
{
    private const MAX_ITEMS = 20;

    /** @return list<array{question: string, answer: string}> */
    public static function fromHtml(string $html): array
    {
        if (stripos($html, '<h2') === false || stripos($html, '<h3') === false) {
            return [];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="faq-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $items = [];
        foreach ($document->getElementsByTagName('h2') as $heading) {
            if (! self::isFaqHeading($heading->textContent)) {
                continue;
            }

            $question = null;
            $answer = [];
            for ($node = $heading->nextSibling; $node !== null; $node = $node->nextSibling) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($node->tagName);
                if ($tag === 'h2' || $tag === 'h1') {
                    break;
                }
                if ($tag === 'h3') {
                    self::push($items, $question, $answer);
                    $question = self::text($node->textContent);
                    $answer = [];

                    continue;
                }
                if ($question !== null) {
                    $answer[] = self::text($node->textContent);
                }
            }
            self::push($items, $question, $answer);
        }

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    private static function isFaqHeading(string $text): bool
    {
        $text = mb_strtolower(self::text($text), 'UTF-8');
        $known = array_map(
            fn (string $locale) => mb_strtolower((string) trans('ai.faq.heading', [], $locale), 'UTF-8'),
            ['vi', 'en'],
        );

        return in_array($text, $known, true)
            || str_contains($text, 'câu hỏi thường gặp')
            || str_contains($text, 'hỏi đáp')
            || preg_match('/\bfaqs?\b/u', $text) === 1;
    }

    /**
     * @param  list<array{question: string, answer: string}>  $items
     * @param  list<string>  $answer
     */
    private static function push(array &$items, ?string $question, array $answer): void
    {
        $answer = trim(implode(' ', array_filter($answer)));
        if ($question !== null && $question !== '' && $answer !== '') {
            $items[] = ['question' => $question, 'answer' => $answer];
        }
    }

    private static function text(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
