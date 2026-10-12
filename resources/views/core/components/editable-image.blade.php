@props([
    'key',
    'src',
    'alt' => '',
    'appendable' => false,
    // Responsive pictures, kept as the theme wrote them until the shop replaces the image.
    'srcset' => null,
    'sizes' => null,
    // <picture> sources, in the theme's order: [['srcset' => …, 'media' => …, 'type' => …, 'sizes' => …], …]
    'sources' => [],
    'pictureClass' => null,
    // A lazy-loaded image: the attribute the theme's loader reads (data-src), and the placeholder src.
    'lazy' => null,
    'placeholder' => null,
])

@php
    $site = app(\App\Services\SiteContentService::class);
    $lists = app(\App\Services\SiteListService::class);
    // Replacing an image needs the media permission on top of content editing.
    $canEdit = \App\Support\ClientEditorChrome::editorActive() && auth()->user()->can('media.view');

    // Images an editor added after this one. Same shape, same classes — the
    // template still owns what an image in this slot looks like.
    $extraList = $appendable ? $key.'.extra' : null;
    $extraIds = $extraList ? $lists->items($extraList) : [];

    // The template passes an already-rendered src (typically asset(...)), so a
    // theme can keep its own markup untouched.
    $cleared = $site->isCleared($key);
    $stored = $cleared ? null : $site->value($key);
    $resolved = $stored ?: $src;

    // Once the shop has chosen a picture, every source the browser could pick from points at it,
    // so no breakpoint can bring the theme's artwork back. The elements and their order stay:
    // CSS like `picture > img` and the theme's scripts keep matching. Until then — or after a
    // restore — everything is printed exactly as the theme wrote it.
    $pick = static fn (?string $original): ?string => $original === null ? null : ($stored ? $stored : $original);
    // A lazy loader only runs for visitors; an editor needs the real picture at once.
    $lazyAttribute = $lazy && ! $canEdit ? $lazy : null;
    $imageAttributes = array_filter([
        'src' => $lazyAttribute ? ($placeholder ?? $resolved) : ($cleared ? $src : $resolved),
        $lazyAttribute => $lazyAttribute ? $resolved : null,
        ($lazyAttribute ? 'data-srcset' : 'srcset') => $pick($srcset),
        'sizes' => $sizes,
    ], static fn ($value, $name) => $name !== '' && $value !== null, ARRAY_FILTER_USE_BOTH);
    $sourceAttributes = array_map(static fn (array $source): array => array_filter([
        'media' => $source['media'] ?? null,
        'type' => $source['type'] ?? null,
        'sizes' => $source['sizes'] ?? null,
        ($lazyAttribute ? 'data-srcset' : 'srcset') => $pick($source['srcset'] ?? null),
    ], static fn ($value) => $value !== null), $sources);
    $printAttributes = static fn (array $attributes): string => implode(' ', array_map(
        static fn (string $name, string $value): string => $name.'="'.e($value).'"',
        array_keys($attributes),
        $attributes,
    ));
@endphp

{{-- Emptied on purpose: a visitor gets no image at all, an editor keeps one so
     the picker and the restore action stay reachable. --}}
@if(! $cleared || $canEdit)
    @if($sources !== [])
        <picture{!! $pictureClass ? ' class="'.e($pictureClass).'"' : '' !!}>
        @foreach($sourceAttributes as $source)
            <source {!! $printAttributes($source) !!}>
        @endforeach
    @endif
    <img
        {!! $printAttributes($imageAttributes) !!}
        alt="{{ $alt }}"
        {{ ($canEdit && $cleared ? $attributes->class(['client-block-cleared']) : $attributes)
            ->merge($canEdit ? array_filter([
                'data-block-key' => $key,
                'data-block-type' => \App\Models\SiteBlock::TYPE_IMAGE,
                'data-block-cleared' => $cleared ? 'true' : null,
                'data-append-list' => $extraList,
            ]) : []) }}
    >
    @if($sources !== [])</picture>@endif
@endif

@foreach($extraIds as $extraId)
    @php
        $extraKey = $lists->itemKey($extraList, $extraId, 'image');
        $extraCleared = $site->isCleared($extraKey);
        $extraSrc = $site->value($extraKey) ?: $src;
    @endphp

    @if(! $extraCleared || $canEdit)
        <img
            src="{{ $extraCleared ? $src : $extraSrc }}"
            alt="{{ $alt }}"
            {{ $attributes
                ->class(['client-block-cleared' => $canEdit && $extraCleared])
                ->merge($canEdit ? array_filter([
                    'data-block-key' => $extraKey,
                    'data-block-type' => \App\Models\SiteBlock::TYPE_IMAGE,
                    'data-block-cleared' => $extraCleared ? 'true' : null,
                    'data-append-list' => $extraList,
                    'data-list-key' => $extraList,
                    'data-list-item' => $extraId,
                ]) : []) }}
        >
    @endif
@endforeach
