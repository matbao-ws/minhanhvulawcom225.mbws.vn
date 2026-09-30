<?php

namespace App\Support;

/**
 * Finds static content in a storefront Blade file that nobody marked editable.
 *
 * The inline editor is mark-up driven: a region is editable because someone
 * wrapped it in `<x-client::editable>`, and a region nobody wrapped is simply
 * invisible to the editor. That is a good property — it is what keeps content
 * owned by an admin screen (a product name, a menu label) from acquiring a
 * second place to edit it — but it has no backstop. Cut a theme into Blade and
 * the regions you forgot look exactly like the regions you decided against, so
 * the omission survives review and ships.
 *
 * This is the backstop. It reports, per file, the elements that look like
 * authored copy and carry no marking, so AuditEditableRegions can wrap them and
 * a drift test can keep them wrapped.
 *
 * The rule that makes it safe is {@see self::isAuthoredCopy()}: content holding
 * a Blade echo or a control directive is never a candidate. Anything printed
 * through an echo came from a controller, which means it came from the
 * database, which means it already has an admin screen. So the scanner cannot
 * propose the one thing the whole mechanism exists to forbid.
 */
class EditableRegionScanner
{
    /**
     * Tags whose text is worth offering to an editor.
     *
     * Block-level and text-bearing only. Inline tags are deliberately absent:
     * a `<strong>` inside a paragraph is part of that paragraph's content, not
     * a region of its own, and offering both would give one sentence two
     * owners.
     */
    public const CANDIDATE_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'li', 'blockquote', 'figcaption', 'td', 'th', 'dt', 'dd',
    ];

    /**
     * Tags allowed to appear *inside* a candidate.
     *
     * A paragraph with a bold word is still a paragraph. A paragraph with a
     * `<div>` in it is a layout, and rewriting layout is not what this editor
     * does — so anything outside this list disqualifies the element rather than
     * producing a region that swallows structure.
     */
    public const INLINE_TAGS = [
        'a', 'abbr', 'b', 'br', 'cite', 'code', 'del', 'em', 'i', 'ins',
        'mark', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'wbr',
    ];

    /** The marker an author puts on copy that is static on purpose. */
    public const IGNORE_ATTRIBUTE = 'data-editable-ignore';

    /** Elements that never have a closing tag. */
    private const VOID_TAGS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * Ancestors that can name the area a key belongs to.
     *
     * Consulted only when no ancestor offered an id or class to borrow.
     */
    private const LANDMARK_TAGS = [
        'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'form', 'table', 'figure',
    ];

    /**
     * Blade control flow.
     *
     * A bare `@` cannot be the signal: an email address in body copy is literal
     * text, and treating it as a directive would quietly exclude every contact
     * block in the theme.
     */
    private const DIRECTIVE_PATTERN = '/@(if|elseif|else|endif|unless|endunless|isset|endisset|empty|endempty|for|endfor|foreach|endforeach|forelse|while|switch|case|break|continue|can|cannot|canany|endcan|auth|guest|env|production|include|includeIf|includeWhen|includeFirst|each|php|json|lang|choice|error|once|push|prepend|stack|yield|section|parent|csrf|method|class|style|checked|selected|disabled|props|aware|use|inject|verbatim|fragment)\b/i';

    /**
     * Every unmarked region in one Blade file, in source order.
     *
     * `$viewKey` is the dotted view name minus the `client.` prefix; it becomes
     * the first segment of every key generated here.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scan(string $source, string $viewKey): array
    {
        $masked = $this->mask($source);
        $tokens = $this->tokenize($masked);

        $findings = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'open') {
                $stack[] = [
                    'name' => $token['name'],
                    'attrs' => $token['attrs'],
                    'start' => $token['start'],
                    'contentStart' => $token['end'],
                    'suppressed' => $this->suppresses($token['attrs']) || $this->stackSuppresses($stack),
                    'claimed' => $this->stackClaims($stack),
                ];

                continue;
            }

            if ($token['type'] === 'void') {
                if ($token['name'] !== 'img'
                    || $this->suppresses($token['attrs'])
                    || $this->stackSuppresses($stack)
                    || $this->stackClaims($stack)) {
                    continue;
                }

                $finding = $this->inspectImage($token, $source, $stack);

                if ($finding !== null) {
                    $findings[] = $finding;
                }

                continue;
            }

            // A closing tag. Blade conditionals routinely open a tag in one
            // branch and close it in another, so an unmatched close is ordinary
            // and must not unwind the whole stack.
            $depth = $this->findOpen($stack, $token['name']);

            if ($depth === null) {
                continue;
            }

            while (count($stack) > $depth + 1) {
                array_pop($stack);
            }

            $element = array_pop($stack);

            if (! in_array($element['name'], self::CANDIDATE_TAGS, true)) {
                continue;
            }

            if ($element['suppressed'] || $element['claimed']) {
                continue;
            }

            $inner = substr($source, $element['contentStart'], $token['start'] - $element['contentStart']);

            if (! $this->isAuthoredCopy($inner)) {
                continue;
            }

            $area = $this->areaFor($stack);

            $findings[] = [
                'kind' => 'text',
                'tag' => $element['name'],
                'line' => $this->lineAt($source, $element['start']),
                'start' => $element['start'],
                'end' => $token['end'],
                'attrs' => $this->rawAttributes($source, $element['start'], $element['contentStart'], $element['name']),
                'inner' => $inner,
                'area' => $area,
                'preview' => $this->preview($inner),
            ];

        }

        // Closing tags arrive innermost first, so a nested candidate is emitted
        // before the one that contains it. Dropping the inner ones here rather
        // than suppressing them during the walk is what keeps the walk honest:
        // containment is known from the offsets, and nothing has to be guessed
        // about a region that has not closed yet.
        // Keys are assigned only after filtering. Numbering a region that is
        // about to be discarded would leave gaps in the sequence, and a gap is
        // the kind of thing someone later reads as a missing region.
        return $this->withKeys($this->outermost($findings), $viewKey);
    }

    /**
     * Give every surviving finding its storage key, in source order.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function withKeys(array $findings, string $viewKey): array
    {
        $ordinals = [];

        foreach ($findings as $index => $finding) {
            $findings[$index]['key'] = $this->makeKey($viewKey, $finding['area'], $finding['tag'], $ordinals);
        }

        return $findings;
    }

    /**
     * Findings that no other finding contains.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function outermost(array $findings): array
    {
        $kept = array_values(array_filter($findings, static function (array $finding) use ($findings): bool {
            foreach ($findings as $other) {
                if ($other === $finding) {
                    continue;
                }

                if ($other['start'] <= $finding['start'] && $other['end'] >= $finding['end']) {
                    return false;
                }
            }

            return true;
        }));

        usort($kept, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $kept;
    }

    /**
     * Blade spans that must not be read as markup, blanked in place.
     *
     * Replacement preserves length, so every offset the tokenizer reports still
     * points at the same byte of the original source. That is what lets `--fix`
     * splice the file without parsing it a second time.
     */
    private function mask(string $source): string
    {
        $masked = $source;

        $spans = [
            '/\{\{--.*?--\}\}/s',
            '/@php\b.*?@endphp/is',
            '/@verbatim\b.*?@endverbatim/is',
            '/\{!!.*?!!\}/s',
            '/\{\{.*?\}\}/s',
            '/<!--.*?-->/s',
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
        ];

        foreach ($spans as $pattern) {
            $replaced = preg_replace_callback(
                $pattern,
                static fn (array $m): string => str_repeat("\x01", strlen($m[0])),
                $masked,
            );

            $masked = $replaced ?? $masked;
        }

        return $masked;
    }

    /**
     * Tag boundaries in the masked source.
     *
     * Quote aware, because an attribute can legitimately contain the character
     * that ends a tag — `alt="2 > 1"` being the shortest example.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tokenize(string $masked): array
    {
        $tokens = [];
        $length = strlen($masked);
        $pos = 0;

        while (($lt = strpos($masked, '<', $pos)) !== false) {
            $pos = $lt + 1;

            if ($pos >= $length) {
                break;
            }

            $closing = $masked[$pos] === '/';

            if ($closing) {
                $pos++;
            }

            if (! preg_match('/\G([A-Za-z][A-Za-z0-9:._-]*)/', $masked, $m, 0, $pos)) {
                continue;
            }

            $name = strtolower($m[1]);
            $afterName = $pos + strlen($m[1]);
            $cursor = $afterName;
            $quote = null;

            while ($cursor < $length) {
                $char = $masked[$cursor];

                if ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }

                $cursor++;
            }

            if ($cursor >= $length) {
                break;
            }

            $attrs = rtrim(substr($masked, $afterName, $cursor - $afterName));
            $selfClosing = str_ends_with($attrs, '/');

            if ($selfClosing) {
                $attrs = rtrim(substr($attrs, 0, -1));
            }

            $type = $closing
                ? 'close'
                : (($selfClosing || in_array($name, self::VOID_TAGS, true)) ? 'void' : 'open');

            $tokens[] = [
                'type' => $type,
                'name' => $name,
                'attrs' => $attrs,
                'start' => $lt,
                'end' => $cursor + 1,
            ];

            $pos = $cursor + 1;
        }

        return $tokens;
    }

    /**
     * Whether an element's own attributes take it out of scope.
     *
     * `contenteditable` counts in either direction: `false` is an author saying
     * the editor must not reach here, and `true` is an author who already wired
     * something up by hand.
     *
     * `data-client-editable-root` takes its whole subtree out, and for the
     * reason this class exists. That element is a CMS page body: the bar makes
     * all of it editable at once and saves it as a single document into
     * `pages.published_html`. A region marked inside it would store the same
     * words a second time in `site_blocks`, and the next whole-document save
     * would overwrite the markup the region rendered. Two owners for one
     * sentence, which is the failure the marking convention is here to prevent.
     */
    private function suppresses(string $attrs): bool
    {
        return str_contains($attrs, self::IGNORE_ATTRIBUTE)
            || str_contains($attrs, 'contenteditable')
            || str_contains($attrs, 'data-block-key')
            || str_contains($attrs, 'data-client-editable-root');
    }

    /** @param  array<int, array<string, mixed>>  $stack */
    private function stackSuppresses(array $stack): bool
    {
        foreach ($stack as $element) {
            if ($element['suppressed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when an ancestor already owns this content.
     *
     * Only an editable component counts here. Containment between two
     * candidates cannot be settled during the walk — the outer one has not
     * closed yet — so that case is resolved afterwards by {@see self::outermost()}.
     *
     * @param  array<int, array<string, mixed>>  $stack
     */
    private function stackClaims(array $stack): bool
    {
        foreach ($stack as $element) {
            if (! empty($element['claimed']) || str_starts_with($element['name'], 'x-client::editable')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Index of the nearest open element with this name, or null.
     *
     * @param  array<int, array<string, mixed>>  $stack
     */
    private function findOpen(array $stack, string $name): ?int
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['name'] === $name) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether this content is copy an author typed, rather than data.
     *
     * The disqualifiers here are the whole safety argument of this class:
     *
     * - A Blade echo means a controller supplied the value, so the value lives
     *   in the database and has an admin screen already.
     * - A control directive means the shape of the output depends on state, and
     *   a stored override would replace the branch along with the words.
     * - A nested component or block tag means this is structure, not a sentence.
     */
    private function isAuthoredCopy(string $inner): bool
    {
        $trimmed = trim($inner);

        if ($trimmed === '') {
            return false;
        }

        if (str_contains($inner, '{{') || str_contains($inner, '{!!')) {
            return false;
        }

        if (preg_match(self::DIRECTIVE_PATTERN, $inner)) {
            return false;
        }

        if (stripos($inner, '<x-') !== false) {
            return false;
        }

        // Punctuation, entities and whitespace alone are not worth a region.
        $letters = preg_replace('/&(?:[a-z]+|#\d+);/i', '', strip_tags($trimmed));

        if (! preg_match('/[\p{L}\p{N}]/u', $letters ?? $trimmed)) {
            return false;
        }

        foreach ($this->tagNamesIn($inner) as $name) {
            if (! in_array($name, self::INLINE_TAGS, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private function tagNamesIn(string $html): array
    {
        preg_match_all('/<\/?([A-Za-z][A-Za-z0-9:._-]*)/', $html, $matches);

        return array_map('strtolower', $matches[1]);
    }

    /**
     * An `<img>` nobody marked, when the picture is part of the design.
     *
     * A `src` holding a variable is a picture the database chose, so it is out
     * for the same reason a `{{ }}` paragraph is. A `src` holding only a path
     * helper — `asset('client-assets/img/hero.jpg')` — is the theme's own
     * artwork, which is exactly what the media picker exists to replace.
     *
     * @param  array<string, mixed>  $token
     * @param  array<int, array<string, mixed>>  $stack
     * @return array<string, mixed>|null
     */
    private function inspectImage(array $token, string $source, array $stack): ?array
    {
        $raw = substr($source, $token['start'], $token['end'] - $token['start']);

        if (! preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/is', $raw, $srcMatch)) {
            return null;
        }

        if (str_contains($srcMatch[2], '$')) {
            return null;
        }

        return [
            'kind' => 'image',
            'tag' => 'img',
            'line' => $this->lineAt($source, $token['start']),
            'start' => $token['start'],
            'end' => $token['end'],
            'attrs' => $this->rawAttributes($source, $token['start'], $token['end'], 'img'),
            'inner' => '',
            'area' => $this->areaFor($stack),
            'preview' => $srcMatch[2],
        ];
    }

    /**
     * The middle segment of a key: where on the page this region sits.
     *
     * An id or class is borrowed from the nearest ancestor that has one,
     * because that is the name the theme's own author already gave the area.
     * A landmark tag is the fallback, and a poor one — two unnamed sections
     * produce `section.h2-1` and `section.h2-2`, which is stable but says
     * nothing. Themes with named blocks get readable keys for free.
     *
     * @param  array<int, array<string, mixed>>  $stack
     */
    private function areaFor(array $stack): string
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $attrs = $stack[$i]['attrs'];

            if (preg_match('/\sid\s*=\s*(["\'])(.*?)\1/is', $attrs, $m)) {
                $slug = $this->slug($m[2]);

                if ($slug !== '') {
                    return $slug;
                }
            }

            if (preg_match('/\sclass\s*=\s*(["\'])(.*?)\1/is', $attrs, $m)) {
                foreach (preg_split('/\s+/', trim($m[2])) ?: [] as $candidate) {
                    $slug = $this->slug($candidate);

                    if ($slug !== '') {
                        return $slug;
                    }
                }
            }
        }

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if (in_array($stack[$i]['name'], self::LANDMARK_TAGS, true)) {
                return $stack[$i]['name'];
            }
        }

        return 'main';
    }

    /**
     * `<page>.<area>.<tag>-<n>`, in the shape SiteBlockUpdateRequest accepts.
     *
     * The ordinal is per area and per tag, assigned in source order. That makes
     * the key stable for a file that is only appended to, and unstable for one
     * whose regions are reordered — which is precisely why `--fix` writes the
     * key into the file the first time and never recomputes it. A key that
     * moved would read a different row, and the region would silently show
     * someone else's text.
     *
     * @param  array<string, int>  $ordinals
     */
    private function makeKey(string $viewKey, string $area, string $tag, array &$ordinals): string
    {
        $page = $this->slugPath($viewKey) ?: 'page';
        $area = $this->slug($area, 40) ?: 'main';

        $bucket = $area.'|'.$tag;
        $ordinals[$bucket] = ($ordinals[$bucket] ?? 0) + 1;

        return $page.'.'.$area.'.'.$tag.'-'.$ordinals[$bucket];
    }

    /**
     * A dotted view name, slugged one segment at a time.
     *
     * The dots stay: `catalog.category` is two segments of the key, not one
     * name containing a separator, and flattening it to `catalog-category`
     * would put this file's keys in a different shape from the ones already
     * written by hand.
     */
    private function slugPath(string $viewKey): string
    {
        $segments = array_filter(array_map(
            fn (string $segment): string => $this->slug($segment, 30),
            explode('.', $viewKey),
        ), static fn (string $segment): bool => $segment !== '');

        return implode('.', array_slice($segments, -4));
    }

    /**
     * Lowercase, hyphen separated, and nothing the key regex would reject.
     *
     * Dots go too: a dot is the key's own separator, so a class named `a.b`
     * would reach into a neighbouring segment's storage.
     */
    private function slug(string $value, int $limit = 40): string
    {
        $value = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value));
        $value = trim((string) preg_replace('/-+/', '-', $value), '-');

        if (strlen($value) > $limit) {
            $value = rtrim(substr($value, 0, $limit), '-');
        }

        return $value;
    }

    /**
     * The element's attributes exactly as the author wrote them.
     *
     * The tokenizer works on the masked copy, where every Blade echo has been
     * blanked — good for finding tag boundaries, useless for rewriting. A fix
     * has to carry `class="{{ $theme }}"` through unchanged, so the attribute
     * text is cut out of the original here instead.
     */
    private function rawAttributes(string $source, int $start, int $openTagEnd, string $name): string
    {
        $tag = substr($source, $start, $openTagEnd - $start);
        $tag = rtrim(substr($tag, 1 + strlen($name)));

        if (str_ends_with($tag, '>')) {
            $tag = rtrim(substr($tag, 0, -1));
        }

        if (str_ends_with($tag, '/')) {
            $tag = rtrim(substr($tag, 0, -1));
        }

        return $tag;
    }

    private function lineAt(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }

    private function preview(string $inner): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($inner)));

        return mb_strlen($text) > 60 ? mb_substr($text, 0, 57).'…' : $text;
    }
}
