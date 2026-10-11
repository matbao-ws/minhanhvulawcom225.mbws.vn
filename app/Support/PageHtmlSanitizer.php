<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class PageHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'article', 'aside', 'b', 'blockquote', 'br', 'button', 'circle', 'code',
        'dd', 'details', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'footer', 'g',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'img', 'li', 'line',
        'main', 'mark', 'nav', 'ol', 'p', 'path', 'picture', 'polygon', 'polyline', 'pre',
        'rect', 'section', 'small', 'source', 'span', 'strong', 'summary', 'svg', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'alt', 'class', 'colspan', 'd', 'fill', 'height', 'href', 'id', 'points', 'preserveaspectratio',
        'rel', 'role', 'rowspan', 'scope', 'src', 'srcset', 'stroke', 'stroke-linecap',
        'stroke-linejoin', 'stroke-width', 'style', 'target', 'title', 'type', 'viewbox',
        'width', 'xmlns',
    ];

    /**
     * Removed with their content, never unwrapped. The raw-text ones (style,
     * xmp, noscript…) hold markup as unescaped text: unwrapping them hands that
     * text to the parent, where it serialises as live markup.
     */
    private const REMOVED_TAGS = [
        'script', 'object', 'embed', 'iframe', 'form', 'input', 'textarea', 'select',
        'style', 'noscript', 'template', 'title', 'xmp', 'noembed', 'noframes', 'plaintext',
        'math', 'base', 'link', 'meta',
    ];

    public function clean(?string $html): string
    {
        // Re-parse until stable: anything a pass exposes is cleaned by the next.
        $output = (string) $html;
        for ($pass = 0; $pass < 3; $pass++) {
            $next = $this->cleanOnce($output);
            if ($next === $output) {
                break;
            }
            $output = $next;
        }

        return MediaUrl::relativizeHtmlSources($output);
    }

    private function cleanOnce(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="page-builder-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $removed = implode('|', array_map(
            static fn (string $tag): string => "//*[local-name()='{$tag}']",
            self::REMOVED_TAGS,
        ));
        foreach (iterator_to_array($xpath->query($removed) ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            if (! $element instanceof DOMElement || $element->getAttribute('id') === 'page-builder-root') {
                continue;
            }
            if (! in_array(strtolower($element->tagName), self::ALLOWED_TAGS, true)) {
                $this->unwrap($element);

                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $allowedAttribute = in_array($name, self::ALLOWED_ATTRIBUTES, true)
                    || str_starts_with($name, 'aria-')
                    || str_starts_with($name, 'data-');
                if (! $allowedAttribute
                    || str_starts_with($name, 'on')
                    || (in_array($name, ['href', 'src', 'srcset'], true) && ! $this->safeUrl($attribute->value))
                    || ($name === 'style' && $this->containsDangerousCss($attribute->value))) {
                    $element->removeAttribute($attribute->name);
                }
            }
            if ($element->hasAttribute('target') && $element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $root = $document->getElementById('page-builder-root');
        if (! $root) {
            return '';
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function unwrap(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    private function containsDangerousCss(string $css): bool
    {
        return (bool) preg_match(
            '/@import|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|<\s*\/?\s*(script|style)/i',
            $css,
        );
    }
}
