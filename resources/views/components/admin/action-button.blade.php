@props([
    'href' => null,
    'icon' => null,
    'variant' => 'primary',
    'type' => 'button',
    'size' => null,
])

@php
    $classes = ['btn', "btn-{$variant}"];

    if ($size) {
        $classes[] = "btn-{$size}";
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <i class="ti ti-{{ $icon }} {{ $slot->isNotEmpty() ? 'me-1' : '' }} fs-4" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <i class="ti ti-{{ $icon }} {{ $slot->isNotEmpty() ? 'me-1' : '' }} fs-4" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </button>
@endif
