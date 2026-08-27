@props([
    'key',
    'tag' => 'div',
    'html' => false,
    'appendable' => true,
])

@php
    $site = app(\App\Services\SiteContentService::class);
    $lists = app(\App\Services\SiteListService::class);
    $canEdit = (bool) auth()->user()?->canEditClientContent();

    /**
     * Boxes an editor appended after this one.
     *
     * The template owns where a region sits and what it looks like; this only
     * decides how many boxes of that same shape stand there. An added box is a
     * sibling carrying the authored tag and classes, so it cannot introduce a
     * layout the design never had.
     */
    $extraList = $appendable ? $key.'.extra' : null;
    $extraIds = $extraList ? $lists->items($extraList) : [];

    $describe = function (string $blockKey) use ($site, $html, $tag): array {
        $stored = $site->value($blockKey);
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
            'cleared' => $cleared,
            'rendersHtml' => $rendersHtml,
            'type' => $rendersHtml ? \App\Models\SiteBlock::TYPE_HTML : \App\Models\SiteBlock::TYPE_TEXT,
            'renderTag' => $format ?: $tag,
        ];
    };

    $editorAttributes = function (array $block, string $blockKey, ?string $listKey = null, ?string $itemId = null)
        use ($canEdit, $attributes, $tag, $extraList) {
        $bag = $attributes->class([
            // A saved heading keeps a public class so its visual hierarchy
            // survives a theme reset that flattens heading sizes.
            'client-content-heading-'.$block['format'] => $block['format'] !== null
                && $block['format'] !== \App\Models\SiteBlock::FORMAT_PARAGRAPH,
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
                // The editor needs the authored Blade tag to drop a saved heading
                // override without disturbing the theme's own styling.
                'data-block-base-tag' => $tag,
                'data-block-cleared' => $block['cleared'] ? 'true' : null,
                // Where the hovering "+" appends. Absent means this region takes
                // no extra boxes.
                'data-append-list' => $extraList,
                // Present only on a box an editor added, which is the only kind
                // that may be deleted outright.
                'data-list-key' => $listKey,
                'data-list-item' => $itemId,
            ]));
    };

    $anchor = $describe($key);
@endphp

{{-- Emptied on purpose renders nothing at all, unless an admin is editing and
     needs a handle to restore it. --}}
@if(! $anchor['cleared'] || $canEdit)
    <{{ $anchor['renderTag'] }} {{ $editorAttributes($anchor, $key) }}>@if($anchor['cleared'])@elseif($anchor['stored'] !== null)@if($anchor['rendersHtml']){!! trim($anchor['stored']) !!}@else{{ trim($anchor['stored']) }}@endif @else{{ $slot }}@endif</{{ $anchor['renderTag'] }}>
@endif

@foreach($extraIds as $extraId)
    @php
        $extraKey = $lists->itemKey($extraList, $extraId, 'text');
        $extra = $describe($extraKey);
    @endphp

    @if(! $extra['cleared'] || $canEdit)
        <{{ $extra['renderTag'] }} {{ $editorAttributes($extra, $extraKey, $extraList, $extraId) }}>@if($extra['cleared'])@elseif($extra['stored'] !== null)@if($extra['rendersHtml']){!! trim($extra['stored']) !!}@else{{ trim($extra['stored']) }}@endif @endif</{{ $extra['renderTag'] }}>
    @endif
@endforeach
