<?php

namespace App\Support;

/**
 * The structure a cut theme needs before the inline editor's tools work on it.
 *
 * EditableRegionScanner answers "can this text be edited". This answers the questions a cut gets
 * wrong silently, because the page still looks right:
 *
 * - A document shell must load the editor: `@include('core.head')` before `</head>` and
 *   `@include('core.editor')` before `</body>`. Without them nothing is editable at all.
 * - A loop that repeats authored content (cards, steps, benefits, footer links…) must render each
 *   unit through `<x-client::list-item>`, as the outermost element of the loop body. Unwrapped,
 *   the run has a fixed length: "Nhân bản", move and delete cannot exist. Wrapped one level too
 *   deep, a copy lands inside the theme's column instead of beside it in the grid.
 *
 * A run that must stay fixed on purpose (a marquee that repeats its logos for the animation) says
 * so in a Blade comment right before the loop: `{{-- editor:fixed-run <reason> --}}`. Like
 * `data-editable-ignore`, a reason is required and every honoured one is listed by the audit.
 *
 * Text-level, like the rest of the audit: it reads the Blade source, not a render, so a loop is
 * judged by the keys written in it. A loop whose keys do not use its own variable repeats nothing
 * editable and is left alone, and so is every loop over database rows.
 */
class EditorStructureScanner
{
    public const FIXED_RUN_MARKER = 'editor:fixed-run';

    /**
     * @return array{
     *     findings: list<array{line: int, reason: string, preview: string}>,
     *     ignored: list<array{line: int, reason: string, preview: string}>,
     *     invalidIgnores: list<array{line: int, preview: string}>,
     *     runs: int,
     * }
     */
    public function audit(string $source): array
    {
        $result = ['findings' => [], 'ignored' => [], 'invalidIgnores' => [], 'runs' => 0];
        $code = $this->withoutComments($source);

        if (preg_match('~</head\s*>~i', $code) && ! preg_match('~@include\(\s*[\'"]core\.head[\'"]~', $code)) {
            $result['findings'][] = ['line' => $this->lineOf($source, '~</head\s*>~i'), 'reason' => 'layout-head', 'preview' => '</head>'];
        }
        if (preg_match('~</body\s*>~i', $code) && ! preg_match('~@include\(\s*[\'"]core\.editor[\'"]~', $code)) {
            $result['findings'][] = ['line' => $this->lineOf($source, '~</body\s*>~i'), 'reason' => 'layout-editor', 'preview' => '</body>'];
        }

        foreach ($this->databaseRegions($code) as $offset => $preview) {
            $result['findings'][] = ['line' => substr_count($code, "\n", 0, $offset) + 1, 'reason' => 'database-region', 'preview' => $preview];
        }

        foreach ($this->loops($source) as $loop) {
            if (! $this->repeatsEditableContent($loop['header'], $loop['body'])) {
                continue;
            }
            $result['runs']++;
            $entry = ['line' => $loop['line'], 'preview' => $this->preview($loop['header'])];

            if ($loop['marker'] !== null) {
                if ($loop['marker'] === '') {
                    $result['invalidIgnores'][] = $entry;
                } else {
                    $result['ignored'][] = $entry + ['reason' => $loop['marker']];

                    continue;
                }
            }

            $body = $this->withoutComments($loop['body']);
            if (! str_contains($body, '<x-client::list-item')) {
                $result['findings'][] = $entry + ['reason' => 'unwrapped-run'];
            } elseif (! preg_match('~^<x-client::list-item\b~', $this->firstElement($body))) {
                $result['findings'][] = $entry + ['reason' => 'nested-run'];
            }
        }

        return $result;
    }

    /**
     * Inline regions wrapped around database content.
     *
     * A product name, a post title or a partner logo belongs to its admin screen. Wrapped in an
     * editable region, an inline edit is stored in `site_blocks` under a key of its own and shadows
     * the record from then on: the admin screen and the API keep the old value, the storefront
     * shows the new one, and renaming the product no longer changes the page. Model access
     * (`$product->name`, `$post->id`) in a region's key, source or fallback content is that
     * mistake. The editor's own helpers (`$loop`, `$lists`, `$site`) are not records.
     *
     * @return array<int, string> offset => preview
     */
    private function databaseRegions(string $code): array
    {
        $found = [];
        $model = '/\$(?!loop\b|lists\b|site\b|this\b|attributes\b|slot\b)[A-Za-z_]\w*\s*(?:\?->|->)/';

        preg_match_all('~<x-client::editable(-image)?\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)(/?)>~s', $code, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($tags as $tag) {
            $attributes = $tag[2][0];
            $inner = '';
            if ($tag[1][0] === '' && $tag[3][0] === '') {
                $close = strpos($code, '</x-client::editable>', $tag[0][1]);
                $start = $tag[0][1] + strlen($tag[0][0]);
                $inner = $close === false ? '' : substr($code, $start, $close - $start);
            }
            // Bound attributes (`:key`, `:src`) are PHP; a plain attribute reaches PHP only through {{ }}.
            preg_match_all('/(?:^|\s):[\w.-]+\s*=\s*"([^"]*)"|\{\{(.*?)\}\}|\{!!(.*?)!!\}/s', $attributes.' '.$inner, $expressions);
            $php = implode(' ', array_merge($expressions[1], $expressions[2], $expressions[3]));

            if (preg_match($model, $php)) {
                $found[$tag[0][1]] = $this->preview($tag[0][0]);
            }
        }

        return $found;
    }

    /**
     * Every `@foreach`/`@forelse`, innermost first, with the comment marker written right before it.
     *
     * @return list<array{line: int, header: string, body: string, marker: ?string}>
     */
    private function loops(string $source): array
    {
        preg_match_all('/@(foreach|forelse)\s*\(|@(endforeach|endforelse|empty)\b/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $loops = [];
        $open = [];

        foreach ($matches as $match) {
            $offset = $match[0][1];

            if (($match[1][0] ?? '') !== '') {
                $headerEnd = $this->balancedEnd($source, (int) strpos($source, '(', $offset));
                $open[] = [
                    'start' => $offset,
                    'header' => substr($source, $offset, $headerEnd - $offset),
                    'bodyStart' => $headerEnd,
                    'bodyEnd' => null,
                ];

                continue;
            }

            if ($open === []) {
                continue;
            }

            // `@empty` ends a forelse body; only `@end…` closes the loop.
            if ($match[2][0] === 'empty') {
                $open[count($open) - 1]['bodyEnd'] ??= $offset;

                continue;
            }

            $loop = array_pop($open);
            $bodyEnd = $loop['bodyEnd'] ?? $offset;
            $loops[] = [
                'line' => substr_count($source, "\n", 0, $loop['start']) + 1,
                'header' => $loop['header'],
                'body' => substr($source, $loop['bodyStart'], $bodyEnd - $loop['bodyStart']),
                'marker' => $this->markerBefore($source, $loop['start']),
            ];
        }

        return $loops;
    }

    /** Offset just past the parenthesis that closes the one at `$open`. */
    private function balancedEnd(string $source, int $open): int
    {
        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            if ($source[$i] === '(') {
                $depth++;
            } elseif ($source[$i] === ')' && --$depth === 0) {
                return $i + 1;
            }
        }

        return $length;
    }

    /** A loop variable (`as $card`, `as $slot => [$icon, $label]`) used in a region key inside the body. */
    private function repeatsEditableContent(string $header, string $body): bool
    {
        // The last ` as `: an inline array before it may hold any text.
        if (! preg_match_all('/\sas\s/i', $header, $separators, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        preg_match_all('/\$[A-Za-z_]\w*/', substr($header, (int) end($separators[0])[1]), $variables);
        preg_match_all('/<x-client::editable(?:-image)?\b[^>]*?\bkey\s*=\s*"([^"]*)"/s', $body, $keys);

        foreach ($keys[1] as $key) {
            foreach ($variables[0] as $variable) {
                if (preg_match('/'.preg_quote($variable, '/').'\b/', $key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** null: no marker; '': a marker without its reason; otherwise the reason. */
    private function markerBefore(string $source, int $offset): ?string
    {
        $before = rtrim(substr($source, 0, $offset));

        // The last comment only: its body may not run past a closing `--}}`.
        if (! preg_match('/\{\{--\s*'.preg_quote(self::FIXED_RUN_MARKER, '/').'\b((?:(?!--\}\}).)*)--\}\}$/s', $before, $match)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', $match[1]) ?? '');
    }

    /** The first tag of a loop body, past comments, @php blocks and whitespace. */
    private function firstElement(string $body): string
    {
        $body = preg_replace('/@php\b(?!\s*\().*?@endphp/s', '', $body) ?? $body;
        $body = preg_replace('/@php\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/s', '', $body) ?? $body;

        return preg_match('/<[^\s>]+/', $body, $match) ? $match[0] : '';
    }

    private function withoutComments(string $source): string
    {
        return preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'], '', $source) ?? $source;
    }

    private function lineOf(string $source, string $pattern): int
    {
        return preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) ? substr_count($source, "\n", 0, $match[0][1]) + 1 : 1;
    }

    private function preview(string $header): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $header) ?? $header);

        return mb_strlen($flat) > 70 ? mb_substr($flat, 0, 70).'…' : $flat;
    }
}
