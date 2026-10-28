@props([
    'list',
    'item',
    // The theme's own element for one unit: `li` inside a list, `article` for a card.
    'tag' => 'div',
])

@php
    /**
     * One box of a repeatable storefront region.
     *
     * Wraps the markup the template defines for an item and, for an authorized
     * admin, attaches the control that removes it.
     */
    $canEdit = \App\Support\ClientEditorChrome::editorActive();
@endphp

{{-- The wrapper carries the theme's own utility classes (card, flex child, grid
     item, …) so it has to exist for visitors too. Emitting it only for an editor
     would give the same page a different flex/grid structure after login —
     exactly the layout break this whole component set exists to avoid. Only the
     admin-only control below is conditional. --}}
{{-- Block containers only: the remove button inside must stay valid HTML (no <a>, no <tr>). --}}
@php($tag = in_array($tag, ['div', 'li', 'article', 'section', 'figure'], true) ? $tag : 'div')
<{{ $tag }}
    {{ $attributes->class($canEdit ? ['client-list-item'] : []) }}
    @if($canEdit)
        data-list-key="{{ $list }}"
        data-list-item="{{ $item }}"
    @endif
>
    @if($canEdit)
        <button type="button" class="client-list-remove" data-client-list-remove
                title="Xóa mục này" aria-label="Xóa mục này">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
        </button>
    @endif
    {{ $slot }}
</{{ $tag }}>
