@props([
    'list',
    'defaults' => [],
    'label' => 'Thêm mục',
])

@php
    /**
     * The "add one more" control for a repeatable region (site_lists).
     *
     * Carries the ids the theme ships with, so a region nobody has edited yet grows from its
     * designed length rather than restarting at one. The new box is a sibling with the same
     * markup as the others: the template decides how it looks, the database only its id.
     * Rendered only for an authorized admin; edit mode decides whether it is visible.
     */
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
@endphp

@if($canEdit)
    <button
        type="button"
        {{ $attributes->class(['client-list-add']) }}
        data-client-list-add
        data-list-key="{{ $list }}"
        data-list-defaults="{{ json_encode(array_values($defaults)) }}"
    >
        {{-- Inline SVG rather than an icon font: the storefront of an arbitrary
             project may not load one, and a missing glyph makes the control invisible. --}}
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <span>{{ $label }}</span>
    </button>
@endif
