@props([
    'key',
    'sections' => [],
    'view' => 'client.sections.',
])

@php
    /**
     * An ordered run of sections the template authored.
     *
     * The template declares which sections exist and what each one looks like;
     * the database only ever stores a permutation of that declaration. An editor
     * can put "values" above "hero", not invent a section or drop one into a place
     * the design never had — which is the line between this and a page builder.
     *
     * Nesting works by giving each level its own key. A section partial may render
     * another section-list under a key derived from its own name, and because a
     * list only ever reorders the ids it owns, a child can never escape into a
     * sibling parent. That scoping is the whole safety property: without it,
     * "move up" at the wrong level would relocate a block across the page.
     */
    $lists = app(\App\Services\SiteListService::class);
    $canEdit = (bool) auth()->user()?->canEditClientContent();

    $declared = array_values(array_filter(
        array_map('strval', $sections),
        fn (string $name): bool => $lists->isValidId($name),
    ));

    // Stored order, restricted to what the template still declares: a section
    // removed from the Blade must disappear even if an old row still names it.
    $ordered = array_values(array_intersect($lists->items($key, $declared), $declared));

    // A section added to the template after the order was saved goes to the end
    // rather than vanishing.
    $ordered = array_merge($ordered, array_values(array_diff($declared, $ordered)));
@endphp

@foreach($ordered as $name)
    {{-- The wrapper exists for visitors too. Emitting it only for an editor would
         give the same page a different DOM after login, which is how a flex or
         grid parent silently changes shape. --}}
    <div
        @class(['client-section', 'client-section--editable' => $canEdit])
        @if($canEdit)
            data-section-list="{{ $key }}"
            data-section-name="{{ $name }}"
        @endif
    >
        @includeIf($view.$name)
    </div>
@endforeach
