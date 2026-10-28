@props([
    'key',
    'tag' => 'div',
    'html' => false,
    'appendable' => false,
])

@php
    $site = app(\App\Services\SiteContentService::class);
    $lists = app(\App\Services\SiteListService::class);
    $canEdit = \App\Support\ClientEditorChrome::editorActive();

    /**
     * Boxes an editor appended after this one, or copies made with "Nhân bản".
     *
     * The template owns where a region sits and what it looks like; this only
     * decides how many boxes of that same shape stand there. An added box is a
     * sibling carrying the authored tag and classes, so it cannot introduce a
     * layout the design never had. Every region may be copied; `appendable`
     * only adds the hovering "+".
     */
    $extraList = $key.'.extra';
    $extraIds = $lists->items($extraList);

    $describe = function (string $blockKey) use ($site, $html, $tag): array {
        $stored = $site->value($blockKey, null, (bool) config('multilingual.inline_edit_fallback', true));
        $format = $site->format($blockKey);
        $cleared = $site->isCleared($blockKey);
        // A region authored as plain text becomes HTML the moment the inline
        // toolbar formats it, and must keep rendering as HTML afterwards.
        $rendersHtml = $html
            || $site->type($blockKey) === \App\Models\SiteBlock::TYPE_HTML
            || $format !== null;

        return [
            'stored' => $stored,
            'format' => $format,
            'align' => $site->align($blockKey),
            'cleared' => $cleared,
            'rendersHtml' => $rendersHtml,
            'type' => $rendersHtml ? \App\Models\SiteBlock::TYPE_HTML : \App\Models\SiteBlock::TYPE_TEXT,
            'renderTag' => $format ?: $tag,
        ];
    };

    $editorAttributes = function (array $block, string $blockKey, ?string $listKey = null, ?string $itemId = null)
        use ($canEdit, $attributes, $tag, $extraList, $appendable) {
        $bag = $attributes->class([
            // A saved heading keeps a public class so its visual hierarchy
            // survives a theme reset that flattens heading sizes.
            'client-content-heading-'.$block['format'] => in_array($block['format'], ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true),
            'client-content-format-p' => $block['format'] === \App\Models\SiteBlock::FORMAT_PARAGRAPH,
            'client-content-list-'.$block['format'] => in_array($block['format'], ['ul', 'ol'], true),
            // The alignment token, for every visitor (core.head maps it).
            'client-align-'.$block['align'] => $block['align'] !== null,
            // A copy of an inline region (a span in a heading) goes on its own line.
            'client-copy-line' => $listKey !== null && in_array($block['renderTag'], ['span', 'a', 'strong', 'b', 'em', 'i', 'small', 'label'], true),
        ]);

        if (! $canEdit) {
            return $bag;
        }

        return $bag
            ->class(['client-block-cleared' => $block['cleared']])
            ->merge(array_filter([
                'data-block-key' => $blockKey,
                'data-block-type' => $block['type'],
                'data-block-format' => $block['format'],
                'data-block-align' => $block['align'],
                // The editor needs the authored Blade tag to drop a saved heading
                // override without disturbing the theme's own styling.
                'data-block-base-tag' => $tag,
                'data-block-cleared' => $block['cleared'] ? 'true' : null,
                // Where the hovering "+" appends. Absent means this region takes
                // no extra boxes.
                'data-append-list' => $appendable ? $extraList : null,
                // Where "Nhân bản" puts a copy of the authored region: first
                // after it. A copy is itself a list item and copies as one.
                'data-copy-list' => $listKey === null ? $extraList : null,
                // Present only on a box an editor added, which is the only kind
                // that may be deleted outright.
                'data-list-key' => $listKey,
                'data-list-item' => $itemId,
            ]));
    };

    /*
     * The inner markup, built here rather than with nested @if/@else in the tag:
     * Blade needs whitespace between `@endif` and `@else`, and that whitespace was
     * rendered inside every saved region as a trailing space.
     */
    $content = function (array $block, $fallback): string {
        if ($block['cleared']) {
            return '';
        }
        if ($block['stored'] === null) {
            return (string) $fallback;
        }

        return $block['rendersHtml'] ? trim($block['stored']) : e(trim($block['stored']));
    };

    $anchor = $describe($key);
@endphp

{{-- Emptied on purpose renders nothing at all, unless an admin is editing and
     needs a handle to restore it. --}}
@if(! $anchor['cleared'] || $canEdit)
    <{{ $anchor['renderTag'] }} {{ $editorAttributes($anchor, $key) }}>{!! $content($anchor, $slot) !!}</{{ $anchor['renderTag'] }}>
@endif

@foreach($extraIds as $extraId)
    @php
        $extraKey = $lists->itemKey($extraList, $extraId, 'text');
        $extra = $describe($extraKey);
    @endphp

    @if(! $extra['cleared'] || $canEdit)
        <{{ $extra['renderTag'] }} {{ $editorAttributes($extra, $extraKey, $extraList, $extraId) }}>{!! $content($extra, '') !!}</{{ $extra['renderTag'] }}>
    @endif
@endforeach
