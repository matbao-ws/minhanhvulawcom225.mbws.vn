<?php

namespace App\Support;

/**
 * Classifies every piece of visible content in a storefront Blade file.
 *
 * The inline editor is mark-up driven: a region is editable because someone
 * wrapped it in `<x-client::editable>`, and a region nobody wrapped is simply
 * invisible to the editor. That is a good property — it is what keeps content
 * owned by an admin screen (a product name, a menu label) from acquiring a
 * second place to edit it — but it has no backstop. Cut a theme into Blade and
 * the regions you forgot look exactly like the regions you decided against, so
 * the omission survives review and ships.
 *
 * This is the backstop, and it works from the content rather than from a list
 * of tags. An earlier version only looked at `h1`–`h6`, `p`, `li` and a few
 * others, and reported "nothing left" for a theme whose hero title was a
 * `<div>`, whose CTA was an `<a class="btn">` and whose banner was a CSS
 * background. The audit was green and the editor could not reach half the
 * page. So now every visible text run and every image source is put in
 * exactly one bucket:
 *
 *   editable     inside an editable component, or the CMS page body
 *   dynamic      printed from a Blade echo or output directive
 *   ignored      inside `data-editable-ignore="<reason>"`
 *   unknown      none of the above — the thing this class exists to report
 *   unsupported  an image the editor cannot replace yet (backgrounds, srcset…)
 *
 * The rule that keeps it safe is the dynamic bucket: content holding a Blade
 * echo came from a controller, which means it came from the database, which
 * means it already has an admin screen. So the scanner cannot propose the one
 * thing the whole mechanism exists to forbid.
 *
 * Detection is broad; `fixable` is narrow on purpose. Only a finding whose
 * rewrite is mechanical — a text-level tag holding a plain sentence, an `<img>`
 * with a literal src — is offered to `--fix`. Everything else is reported with
 * a reason, because an auditor that rewrites markup it does not fully
 * understand becomes a new source of the bugs it was written to find.
 */
class EditableRegionScanner
{
    /**
     * Owners that `--fix` may wrap mechanically.
     *
     * Block-level and text-bearing only: wrapping one of these in the editable
     * component with the same `tag` leaves the DOM exactly as it was.
     */
    public const CANDIDATE_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'li', 'blockquote', 'figcaption', 'td', 'th', 'dt', 'dd',
    ];

    /**
     * Tags that are part of a sentence rather than a region of their own.
     *
     * A paragraph with a bold word is still one paragraph, owned once. Any
     * other descendant — a `<div>`, an `<img>`, a component — makes the element
     * a layout, and the text inside it has to find a smaller owner.
     */
    public const INLINE_TAGS = [
        'a', 'abbr', 'b', 'br', 'cite', 'code', 'del', 'em', 'i', 'ins',
        'mark', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'wbr',
    ];

    /**
     * The marker an author puts on content that is static on purpose.
     *
     * It must carry a reason — `data-editable-ignore="menu từ database"`. A bare
     * marker is an error, not an opt-out: it is the cheapest way to turn this
     * audit green, and a reason is what lets a reviewer tell a decision from a
     * silenced finding.
     */
    public const IGNORE_ATTRIBUTE = 'data-editable-ignore';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_UNSUPPORTED = 'unsupported';

    /**
     * Elements a sentence's ownership may climb into even without text of their own.
     *
     * `<h2><span>Dịch vụ</span></h2>` is the heading's text, and
     * `<a class="btn"><span>Liên hệ</span></a>` is the button's. A plain
     * wrapper `<div>` is not in this list: two CTA links side by side in one
     * `<div class="actions">` are two regions, not one region holding both.
     */
    private const OWNER_TAGS = [
        ...self::CANDIDATE_TAGS,
        'a', 'button', 'label', 'summary', 'legend', 'caption', 'option',
    ];

    /** Subtrees that never render visible copy of their own. */
    private const SKIPPED_SUBTREES = ['head', 'svg', 'math', 'template'];

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

    /** Attributes a lazy-loader reads the real image from. */
    private const LAZY_SOURCE_ATTRIBUTES = ['data-src', 'data-lazy-src', 'data-original', 'data-lazy'];

    /** `data-bg`, `data-background`, `data-background-image`, `data-bgset`… */
    private const BACKGROUND_ATTRIBUTE_PATTERN = '/(?:^|\s)data-(?:[a-z0-9]+-)*(?:bg|background|bgset)(?:-[a-z0-9]+)*\s*=\s*(["\'])(.*?)\1/is';

    /*
     * Masking replaces Blade and non-rendering spans byte for byte, so every
     * offset still points into the original source. The fill byte records
     * *what* was there, because "a value was printed here" and "a branch was
     * decided here" lead to different verdicts.
     */
    private const INERT = "\x01";

    private const OUTPUT = "\x02";

    private const CONTROL = "\x03";

    /** Directives that decide structure and print nothing visible. */
    private const CONTROL_DIRECTIVES = [
        'if', 'elseif', 'else', 'endif', 'unless', 'endunless', 'isset', 'endisset', 'empty', 'endempty',
        'for', 'endfor', 'foreach', 'endforeach', 'forelse', 'endforelse', 'while', 'endwhile',
        'switch', 'case', 'default', 'break', 'endswitch', 'continue',
        'can', 'cannot', 'canany', 'elsecan', 'elsecannot', 'elsecanany', 'endcan', 'endcannot', 'endcanany',
        'auth', 'endauth', 'guest', 'endguest', 'env', 'endenv', 'production', 'endproduction',
        'hassection', 'sectionmissing', 'section', 'endsection', 'stop', 'append', 'overwrite',
        'push', 'endpush', 'pushonce', 'endpushonce', 'prepend', 'endprepend', 'once', 'endonce',
        'props', 'aware', 'use', 'inject', 'php', 'endphp', 'extends', 'fragment', 'endfragment',
        'csrf', 'method', 'error', 'enderror', 'session', 'endsession',
        'class', 'style', 'checked', 'selected', 'disabled', 'readonly', 'required',
    ];

    /** Directives whose output is a value rather than authored copy. */
    private const OUTPUT_DIRECTIVES = [
        'lang', 'choice', 'json', 'yield', 'show', 'stack', 'each',
        'include', 'includeif', 'includewhen', 'includeunless', 'includefirst',
    ];

    private string $source = '';

    private string $masked = '';

    /** @var array<int, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<int, bool> */
    private array $phrasing = [];

    /** @var array<int, true> */
    private array $owners = [];

    /** @var array<int, array<int, array{0: int, 1: int, 2: bool, 3: bool}>> */
    private array $ownerless = [];

    /** @var array<int, array<string, mixed>> */
    private array $imageFindings = [];

    /** @var array<int, array<string, mixed>> */
    private array $ignored = [];

    /** @var array<int, array<string, mixed>> */
    private array $invalidIgnores = [];

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    /**
     * Every finding in one Blade file, in source order.
     *
     * `$viewKey` is the dotted view name minus the `client.` prefix; it becomes
     * the first segment of every key generated here.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scan(string $source, string $viewKey): array
    {
        return $this->audit($source, $viewKey)['findings'];
    }

    /**
     * Findings plus everything the report needs to show its arithmetic.
     *
     * @return array{
     *     findings: array<int, array<string, mixed>>,
     *     ignored: array<int, array<string, mixed>>,
     *     invalidIgnores: array<int, array<string, mixed>>,
     *     counts: array<string, array<string, int>>,
     * }
     */
    public function audit(string $source, string $viewKey): array
    {
        $this->source = $source;
        $this->masked = $this->mask($source);
        $this->nodes = $this->buildTree($this->tokenize($this->masked));
        $this->phrasing = [];
        $this->owners = [];
        $this->ownerless = [];
        $this->imageFindings = [];
        $this->ignored = [];
        $this->invalidIgnores = [];
        $this->counts = [
            'text' => ['editable' => 0, 'dynamic' => 0, 'ignored' => 0, 'unknown' => 0],
            'image' => ['editable' => 0, 'dynamic' => 0, 'ignored' => 0, 'unsupported' => 0, 'unknown' => 0],
        ];

        $this->walk(0, null);

        // Containment between text owners is settled after the walk, from the
        // offsets: an outer owner has not closed yet when an inner one is met.
        // Images are left out of it — a background on a <span> inside a
        // paragraph is a second, independent finding, not a duplicate.
        $findings = array_merge($this->outermost($this->textFindings()), $this->imageFindings);
        usort($findings, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        foreach ($findings as $finding) {
            $this->counts[$finding['kind']][$finding['status']]++;
        }

        foreach ($this->ignored as $entry) {
            if ($entry['text']) {
                $this->counts['text']['ignored']++;
            }

            if ($entry['image']) {
                $this->counts['image']['ignored']++;
            }
        }

        // Keys are assigned only after filtering. Numbering a region that is
        // about to be discarded would leave gaps in the sequence, and a gap is
        // the kind of thing someone later reads as a missing region.
        return [
            'findings' => $this->withKeys($findings, $viewKey),
            'ignored' => $this->ignored,
            'invalidIgnores' => $this->invalidIgnores,
            'counts' => $this->counts,
        ];
    }

    private function walk(int $id, ?int $ignoring): void
    {
        foreach ($this->nodes[$id]['children'] as $child) {
            if (is_array($child)) {
                $this->visitText($id, $child, $ignoring);

                continue;
            }

            $this->visitElement($child, $ignoring);
        }
    }

    private function visitElement(int $id, ?int $ignoring): void
    {
        $element = $this->nodes[$id];
        $name = $element['name'];
        $attrs = $element['attrs'];

        if (in_array($name, self::SKIPPED_SUBTREES, true)) {
            return;
        }

        if (str_starts_with($name, 'x-client::editable-image')) {
            $this->counts['image']['editable']++;

            return;
        }

        // Anything inside the editable component is its slot, which is the
        // default value — already owned, whatever markup it holds.
        if (str_starts_with($name, 'x-client::editable')) {
            $this->counts['text']['editable']++;

            return;
        }

        // `data-client-editable-root` is a CMS page body: the bar makes all of
        // it editable at once and saves it as one document into
        // `pages.published_html`. A region marked inside it would store the
        // same words a second time in `site_blocks`, and the next whole-document
        // save would overwrite what that region rendered.
        if (str_contains($attrs, 'data-block-key') || str_contains($attrs, 'data-client-editable-root')) {
            $this->counts[$name === 'img' ? 'image' : 'text']['editable']++;

            return;
        }

        if ($ignoring === null && preg_match('/(?:^|\s)'.self::IGNORE_ATTRIBUTE.'(?=[\s=]|$)/', $attrs)) {
            $reason = $this->ignoreReason($id);

            if ($reason !== '') {
                $ignoring = count($this->ignored);
                $this->ignored[] = [
                    'tag' => $name,
                    'line' => $this->lineAt($element['start']),
                    'reason' => $reason,
                    'preview' => $this->preview($this->innerRaw($id)),
                    'text' => false,
                    'image' => false,
                ];
            } else {
                // Reported, and deliberately *not* honoured: the content
                // underneath is still audited, so a bare marker cannot hide it.
                $this->invalidIgnores[] = [
                    'tag' => $name,
                    'line' => $this->lineAt($element['start']),
                    'preview' => $this->preview($this->innerRaw($id)),
                ];
            }
        }

        $this->inspectMedia($id, $ignoring);
        $this->walk($id, $ignoring);
    }

    /** @param  array{0: int, 1: int}  $run */
    private function visitText(int $parent, array $run, ?int $ignoring): void
    {
        [$start, $end] = $run;
        $text = substr($this->masked, $start, $end - $start);
        $letters = $this->hasLetters($text);
        $output = str_contains($text, self::OUTPUT);

        if (! $letters && ! $output) {
            return;
        }

        if ($ignoring !== null) {
            $this->ignored[$ignoring]['text'] = true;

            return;
        }

        $owner = $this->ownerOf($parent);

        if ($owner === null) {
            $this->ownerless[$parent][] = [$start, $end, $letters, $output];

            return;
        }

        $this->owners[$owner] = true;
    }

    /**
     * The element that owns a text run found directly inside `$id`, or null.
     *
     * The owner is the smallest element whose content is a sentence — text and
     * inline tags only — widened while the sentence visibly continues into the
     * parent. Null means the text sits beside block-level siblings, where no
     * existing element can be wrapped without swallowing layout.
     */
    private function ownerOf(int $id): ?int
    {
        if ($id === 0 || ! $this->isPhrasing($id)) {
            return null;
        }

        $owner = $id;

        while (($parent = $this->nodes[$owner]['parent']) !== 0
            && $this->isPhrasing($parent)
            && ($this->hasOwnLetters($parent) || in_array($this->nodes[$parent]['name'], self::OWNER_TAGS, true))) {
            $owner = $parent;
        }

        return $owner;
    }

    /** Whether every descendant element of `$id` is an inline tag. */
    private function isPhrasing(int $id): bool
    {
        if (isset($this->phrasing[$id])) {
            return $this->phrasing[$id];
        }

        $result = true;

        foreach ($this->nodes[$id]['children'] as $child) {
            if (is_array($child)) {
                continue;
            }

            if (! in_array($this->nodes[$child]['name'], self::INLINE_TAGS, true) || ! $this->isPhrasing($child)) {
                $result = false;

                break;
            }
        }

        return $this->phrasing[$id] = $result;
    }

    private function hasOwnLetters(int $id): bool
    {
        foreach ($this->nodes[$id]['children'] as $child) {
            if (is_array($child) && $this->hasLetters(substr($this->masked, $child[0], $child[1] - $child[0]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn the owners collected during the walk into findings and counts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function textFindings(): array
    {
        $findings = [];

        foreach (array_keys($this->owners) as $id) {
            $inner = $this->innerMasked($id);

            if (str_contains($inner, self::OUTPUT)) {
                $this->counts['text']['dynamic']++;

                continue;
            }

            $findings[] = $this->ownedFinding($id, str_contains($inner, self::CONTROL));
        }

        foreach ($this->ownerless as $parent => $runs) {
            $dynamic = false;

            foreach ($runs as [$start, $end, $letters, $output]) {
                if ($output) {
                    $dynamic = true;

                    continue;
                }

                $findings[] = [
                    'kind' => 'text',
                    'status' => self::STATUS_UNKNOWN,
                    'fixable' => false,
                    'reason' => 'mixed',
                    'tag' => 'text',
                    'container' => $parent === 0 ? null : $this->nodes[$parent]['name'],
                    'line' => $this->lineAt($start + strspn($this->source, " \t\r\n", $start)),
                    'start' => $start,
                    'end' => $end,
                    'attrs' => '',
                    'inner' => substr($this->source, $start, $end - $start),
                    'area' => $this->areaFor($parent === 0 ? [] : [...$this->ancestors($parent), $parent]),
                    'preview' => $this->preview(substr($this->source, $start, $end - $start)),
                ];
            }

            if ($dynamic) {
                $this->counts['text']['dynamic']++;
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    private function ownedFinding(int $id, bool $conditional): array
    {
        $element = $this->nodes[$id];
        $name = $element['name'];
        $inner = $this->innerRaw($id);
        $attrs = $this->rawAttributes($element['start'], $element['contentStart'], $name);

        // Wrapping is mechanical only when the element is a text-level tag,
        // was closed explicitly (so its end offset is real), and holds nothing
        // already marked — wrapping a paragraph that contains an ignored span
        // would put that span under the new region's ownership. A reasonless
        // marker on the element itself is a decision someone half-made; a
        // human finishes it, the fixer does not guess which way.
        $fixable = ! $conditional
            && $element['closed']
            && in_array($name, self::CANDIDATE_TAGS, true)
            && ! str_contains($attrs.$inner, self::IGNORE_ATTRIBUTE)
            && ! str_contains($inner, 'data-block-key');

        return [
            'kind' => 'text',
            'status' => self::STATUS_UNKNOWN,
            'fixable' => $fixable,
            'reason' => match (true) {
                $conditional => 'conditional',
                str_starts_with($name, 'x-') => 'slot',
                $fixable => null,
                default => 'manual',
            },
            'tag' => $name,
            'container' => null,
            'line' => $this->lineAt($element['start']),
            'start' => $element['start'],
            'end' => $element['end'],
            'attrs' => $attrs,
            'inner' => $inner,
            'area' => $this->areaFor($this->ancestors($id)),
            'preview' => $this->preview($inner),
        ];
    }

    /**
     * Every image source this element carries, classified.
     *
     * A source holding a variable is a picture the database chose, so it is
     * dynamic for the same reason a `{{ }}` paragraph is. A literal source is
     * the theme's own artwork — but the editor can only replace it when it is
     * the plain `src` of a plain `<img>`. Anything the browser actually reads
     * from somewhere else (srcset, `<source>`, a lazy-loader's `data-src`, CSS)
     * would keep showing the old picture after a "successful" replace, so it is
     * reported as unsupported rather than offered.
     */
    private function inspectMedia(int $id, ?int $ignoring): void
    {
        $element = $this->nodes[$id];
        $name = $element['name'];
        $raw = $this->rawAttributes($element['start'], $element['contentStart'], $name);
        $sources = [];

        if ($name === 'img') {
            $src = $this->attribute($raw, 'src');
            $lazy = null;

            foreach (self::LAZY_SOURCE_ATTRIBUTES as $attribute) {
                $lazy ??= $this->attribute($raw, $attribute);
            }

            $real = $lazy ?? $src;

            if ($real !== null) {
                $sources[] = match (true) {
                    str_contains($real, '$') => ['dynamic', null, $real],
                    $lazy !== null => [self::STATUS_UNSUPPORTED, 'lazy', $lazy],
                    $this->attribute($raw, 'srcset') !== null || $this->attribute($raw, 'data-srcset') !== null => [self::STATUS_UNSUPPORTED, 'srcset', $real],
                    $this->hasAncestor($id, 'picture') => [self::STATUS_UNSUPPORTED, 'picture', $real],
                    $this->isMechanicalImage($raw) => [self::STATUS_UNKNOWN, null, $real],
                    default => [self::STATUS_UNKNOWN, 'manual', $real],
                };
            }
        }

        if ($name === 'video' && ($poster = $this->attribute($raw, 'poster')) !== null) {
            $sources[] = str_contains($poster, '$')
                ? ['dynamic', null, $poster]
                : [self::STATUS_UNSUPPORTED, 'poster', $poster];
        }

        $style = $this->attribute($raw, 'style');

        if ($style !== null && preg_match('/url\s*\(/i', $style)) {
            $sources[] = str_contains($style, '$')
                ? ['dynamic', null, $style]
                : [self::STATUS_UNSUPPORTED, 'background', $style];
        }

        if (preg_match_all(self::BACKGROUND_ATTRIBUTE_PATTERN, $raw, $matches)) {
            foreach ($matches[2] as $value) {
                $sources[] = str_contains($value, '$')
                    ? ['dynamic', null, $value]
                    : [self::STATUS_UNSUPPORTED, 'data-background', $value];
            }
        }

        foreach ($sources as [$status, $reason, $preview]) {
            if ($ignoring !== null) {
                $this->ignored[$ignoring]['image'] = true;

                continue;
            }

            if ($status === 'dynamic') {
                $this->counts['image']['dynamic']++;

                continue;
            }

            $this->imageFindings[] = [
                'kind' => 'image',
                'status' => $status,
                'fixable' => $status === self::STATUS_UNKNOWN && $reason === null,
                'reason' => $reason,
                'tag' => $name,
                'container' => null,
                'line' => $this->lineAt($element['start']),
                'start' => $element['start'],
                'end' => $name === 'img' ? $element['end'] : $element['contentStart'],
                'attrs' => $raw,
                'inner' => '',
                'area' => $this->areaFor($this->ancestors($id)),
                'preview' => $this->preview($preview),
            ];
        }
    }

    /**
     * Whether `--fix` can lift src and alt into the image component.
     *
     * Both are props on the component rather than passthrough attributes. An
     * `alt` carrying a Blade echo is left for a human: the component renders
     * alt as text, and quietly turning a translated string into a literal is
     * the kind of change that never gets noticed.
     */
    private function isMechanicalImage(string $raw): bool
    {
        if (! preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/is', ' '.$raw)) {
            return false;
        }

        $alt = $this->attribute($raw, 'alt');

        return $alt === null || (! str_contains($alt, '{{') && ! str_contains($alt, '{!!'));
    }

    private function ignoreReason(int $id): string
    {
        $element = $this->nodes[$id];
        $raw = $this->rawAttributes($element['start'], $element['contentStart'], $element['name']);

        return trim((string) $this->attribute($raw, self::IGNORE_ATTRIBUTE));
    }

    /** A quoted attribute's value from raw attribute text, or null if absent. */
    private function attribute(string $raw, string $name): ?string
    {
        $pattern = '/(?:^|\s)'.preg_quote($name, '/').'\s*=\s*(?:(["\'])(.*?)\1|([^\s"\'>]+))/is';

        if (! preg_match($pattern, $raw, $match)) {
            return null;
        }

        return ($match[3] ?? '') !== '' ? $match[3] : $match[2];
    }

    private function hasAncestor(int $id, string $name): bool
    {
        foreach ($this->ancestors($id) as $ancestor) {
            if ($this->nodes[$ancestor]['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ancestors of `$id`, outermost first, excluding the document root.
     *
     * @return array<int, int>
     */
    private function ancestors(int $id): array
    {
        $chain = [];

        for ($parent = $this->nodes[$id]['parent']; $parent !== null && $parent !== 0; $parent = $this->nodes[$parent]['parent']) {
            array_unshift($chain, $parent);
        }

        return $chain;
    }

    private function hasLetters(string $masked): bool
    {
        $text = (string) preg_replace('/[\x01-\x03]+/', ' ', $masked);
        $text = (string) preg_replace('/&(?:[a-z]+|#\d+|#x[0-9a-f]+);/i', '', $text);

        return (bool) preg_match('/[\p{L}\p{N}]/u', $text);
    }

    private function innerEnd(int $id): int
    {
        return $this->nodes[$id]['closeStart'] ?? $this->nodes[$id]['end'];
    }

    private function innerMasked(int $id): string
    {
        $start = $this->nodes[$id]['contentStart'];

        return substr($this->masked, $start, $this->innerEnd($id) - $start);
    }

    private function innerRaw(int $id): string
    {
        $start = $this->nodes[$id]['contentStart'];

        return substr($this->source, $start, max(0, $this->innerEnd($id) - $start));
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
        return array_values(array_filter($findings, static function (array $finding) use ($findings): bool {
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
    }

    /**
     * Blade and non-rendering spans, blanked in place.
     *
     * Replacement preserves length, so every offset the tokenizer reports still
     * points at the same byte of the original source. That is what lets `--fix`
     * splice the file without parsing it a second time.
     */
    private function mask(string $source): string
    {
        $masked = $source;

        $spans = [
            '/\{\{--.*?--\}\}/s' => self::INERT,
            '/@verbatim\b.*?@endverbatim/is' => self::INERT,
            // Block form only: `@php($x = 1)` is a one-liner and has no end tag
            // to run to — matching it here would blank everything up to the
            // next block's @endphp.
            '/@php\b(?!\s*\().*?@endphp/is' => self::INERT,
            '/\{!!.*?!!\}/s' => self::OUTPUT,
            '/\{\{.*?\}\}/s' => self::OUTPUT,
            '/<!--.*?-->/s' => self::INERT,
            '/<![a-z][^>]*>/i' => self::INERT,
            '/<script\b[^>]*>.*?<\/script>/is' => self::INERT,
            '/<style\b[^>]*>.*?<\/style>/is' => self::INERT,
        ];

        foreach ($spans as $pattern => $fill) {
            $replaced = preg_replace_callback(
                $pattern,
                static fn (array $m): string => str_repeat($fill, strlen($m[0])),
                $masked,
            );

            $masked = $replaced ?? $masked;
        }

        return $this->maskDirectives($masked);
    }

    /**
     * Blade directives, with their arguments.
     *
     * A bare `@` cannot be the signal: an email address in body copy is literal
     * text, and treating it as a directive would quietly exclude every contact
     * block in the theme. So only a known directive name, or an unknown one
     * called with arguments (a custom directive, which may print), is masked.
     */
    private function maskDirectives(string $masked): string
    {
        if (! preg_match_all('/(?<![\w@.\x01\x02\x03])@([A-Za-z_]\w*)/', $masked, $matches, PREG_OFFSET_CAPTURE)) {
            return $masked;
        }

        $resumeAt = 0;

        foreach ($matches[0] as $index => [$text, $offset]) {
            if ($offset < $resumeAt) {
                continue;
            }

            $name = strtolower($matches[1][$index][0]);
            $end = $offset + strlen($text);
            $argumentsEnd = $this->argumentsEnd($masked, $end);

            $fill = match (true) {
                in_array($name, self::CONTROL_DIRECTIVES, true) => self::CONTROL,
                in_array($name, self::OUTPUT_DIRECTIVES, true), $argumentsEnd !== null => self::OUTPUT,
                default => null,
            };

            if ($fill === null) {
                continue;
            }

            $end = $argumentsEnd ?? $end;
            $masked = substr_replace($masked, str_repeat($fill, $end - $offset), $offset, $end - $offset);
            $resumeAt = $end;
        }

        return $masked;
    }

    /** Offset just past a directive's balanced `( … )`, or null if it has none. */
    private function argumentsEnd(string $masked, int $position): ?int
    {
        $length = strlen($masked);

        while ($position < $length && ($masked[$position] === ' ' || $masked[$position] === "\t")) {
            $position++;
        }

        if ($position >= $length || $masked[$position] !== '(') {
            return null;
        }

        $depth = 0;
        $quote = null;

        for ($i = $position; $i < $length; $i++) {
            $char = $masked[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $i + 1;
            }
        }

        return null;
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
     * The element tree, with text runs as leaves.
     *
     * Node 0 is the document. Blade conditionals routinely open a tag in one
     * branch and close it in another, so an unmatched close is ordinary and
     * must not unwind the whole stack; an element closed only implicitly (its
     * parent closed first) is kept but marked, because its end offset is a
     * guess and `--fix` must never splice on a guess.
     *
     * @param  array<int, array<string, mixed>>  $tokens
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $tokens): array
    {
        $length = strlen($this->masked);
        $nodes = [0 => [
            'name' => '#root', 'attrs' => '', 'start' => 0, 'contentStart' => 0, 'end' => $length,
            'closeStart' => $length, 'closed' => true, 'parent' => null, 'children' => [],
        ]];
        $stack = [0];
        $cursor = 0;

        foreach ($tokens as $token) {
            $this->attachText($nodes, $stack[count($stack) - 1], $cursor, $token['start']);
            $cursor = $token['end'];

            if ($token['type'] === 'close') {
                $depth = null;

                for ($i = count($stack) - 1; $i >= 1; $i--) {
                    if ($nodes[$stack[$i]]['name'] === $token['name']) {
                        $depth = $i;

                        break;
                    }
                }

                if ($depth === null) {
                    continue;
                }

                while (count($stack) > $depth + 1) {
                    $nodes[array_pop($stack)]['end'] = $token['start'];
                }

                $open = array_pop($stack);
                $nodes[$open]['end'] = $token['end'];
                $nodes[$open]['closeStart'] = $token['start'];
                $nodes[$open]['closed'] = true;

                continue;
            }

            $id = count($nodes);
            $parent = $stack[count($stack) - 1];
            $void = $token['type'] === 'void';

            $nodes[$id] = [
                'name' => $token['name'],
                'attrs' => $token['attrs'],
                'start' => $token['start'],
                'contentStart' => $token['end'],
                'end' => $void ? $token['end'] : null,
                'closeStart' => $void ? $token['end'] : null,
                'closed' => $void,
                'parent' => $parent,
                'children' => [],
            ];
            $nodes[$parent]['children'][] = $id;

            if (! $void) {
                $stack[] = $id;
            }
        }

        $this->attachText($nodes, $stack[count($stack) - 1], $cursor, $length);

        while (count($stack) > 1) {
            $nodes[array_pop($stack)]['end'] = $length;
        }

        return $nodes;
    }

    /** @param  array<int, array<string, mixed>>  $nodes */
    private function attachText(array &$nodes, int $parent, int $start, int $end): void
    {
        if ($end <= $start || trim(substr($this->masked, $start, $end - $start), " \t\r\n\0\x0B\x01") === '') {
            return;
        }

        $nodes[$parent]['children'][] = [$start, $end];
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
     * @param  array<int, int>  $ancestors  outermost first
     */
    private function areaFor(array $ancestors): string
    {
        for ($i = count($ancestors) - 1; $i >= 0; $i--) {
            $attrs = $this->nodes[$ancestors[$i]]['attrs'];

            if (preg_match('/\sid\s*=\s*(["\'])(.*?)\1/is', ' '.$attrs, $m)) {
                $slug = $this->slug($m[2]);

                if ($slug !== '') {
                    return $slug;
                }
            }

            if (preg_match('/\sclass\s*=\s*(["\'])(.*?)\1/is', ' '.$attrs, $m)) {
                foreach (preg_split('/\s+/', trim($m[2])) ?: [] as $candidate) {
                    $slug = $this->slug($candidate);

                    if ($slug !== '') {
                        return $slug;
                    }
                }
            }
        }

        for ($i = count($ancestors) - 1; $i >= 0; $i--) {
            if (in_array($this->nodes[$ancestors[$i]]['name'], self::LANDMARK_TAGS, true)) {
                return $this->nodes[$ancestors[$i]]['name'];
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
        $tag = $this->slug($tag, 30) ?: 'region';

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
    private function rawAttributes(int $start, int $openTagEnd, string $name): string
    {
        $tag = substr($this->source, $start, $openTagEnd - $start);
        $tag = rtrim(substr($tag, 1 + strlen($name)));

        if (str_ends_with($tag, '>')) {
            $tag = rtrim(substr($tag, 0, -1));
        }

        if (str_ends_with($tag, '/')) {
            $tag = rtrim(substr($tag, 0, -1));
        }

        return $tag;
    }

    private function lineAt(int $offset): int
    {
        return substr_count(substr($this->source, 0, $offset), "\n") + 1;
    }

    private function preview(string $inner): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($inner)));

        return mb_strlen($text) > 60 ? mb_substr($text, 0, 57).'…' : $text;
    }
}
