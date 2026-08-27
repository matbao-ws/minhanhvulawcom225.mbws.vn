@props([
    'key',
    'src',
    'alt' => '',
    'appendable' => true,
])

@php
    $site = app(\App\Services\SiteContentService::class);
    $lists = app(\App\Services\SiteListService::class);
    // Replacing an image needs the media permission on top of content editing.
    $canEdit = auth()->user()?->canEditClientContent() && auth()->user()->can('media.view');

    // Images an editor added after this one. Same shape, same classes — the
    // template still owns what an image in this slot looks like.
    $extraList = $appendable ? $key.'.extra' : null;
    $extraIds = $extraList ? $lists->items($extraList) : [];

    // The template passes an already-rendered src (typically asset(...)), so a
    // theme can keep its own markup untouched.
    $cleared = $site->isCleared($key);
    $resolved = $site->value($key) ?: $src;
@endphp

{{-- Emptied on purpose: a visitor gets no image at all, an editor keeps one so
     the picker and the restore action stay reachable. --}}
@if(! $cleared || $canEdit)
    <img
        src="{{ $cleared ? $src : $resolved }}"
        alt="{{ $alt }}"
        {{ $attributes
            ->class(['client-block-cleared' => $canEdit && $cleared])
            ->merge($canEdit ? array_filter([
                'data-block-key' => $key,
                'data-block-type' => \App\Models\SiteBlock::TYPE_IMAGE,
                'data-block-cleared' => $cleared ? 'true' : null,
                'data-append-list' => $extraList,
            ]) : []) }}
    >
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
