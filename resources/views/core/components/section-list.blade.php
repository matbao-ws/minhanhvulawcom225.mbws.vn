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
     * the database only ever stores a permutation of that declaration, plus which
     * of the shapes a section already knows how to render it should use. An editor
     * can put "values" above "hero", or make "hero" dark and centred — not invent
     * a section, drop one into a place the design never had, or write CSS.
     *
     * Nesting works by giving each level its own key. A section partial may render
     * another section-list under a key derived from its own name, and because a
     * list only ever reorders the ids it owns, a child can never escape into a
     * sibling parent. That scoping is the whole safety property: without it,
     * "move up" at the wrong level would relocate a block across the page.
     */
    $lists = app(\App\Services\SiteListService::class);
    $sectionState = app(\App\Services\SiteSectionService::class);
    $registry = app(\App\Support\SectionRegistry::class);
    $canEdit = \App\Support\ClientEditorChrome::editorActive();

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
    @php
        $hidden = $sectionState->isHidden($key, $name);
        $variant = $sectionState->variant($key, $name);
        $settings = $sectionState->settings($key, $name);
        $presentation = $sectionState->presentation($key, $name);

        /*
         * Appearance classes render for visitors too — a variant or a background is
         * content, not editor chrome. Only the edit hooks below are conditional.
         */
        $classes = array_merge(
            ['client-section'],
            $presentation['classes'],
            array_filter([$registry->variantClass($name, $variant)]),
            $canEdit ? ['client-section--editable'] : [],
            $canEdit && $hidden ? ['client-section--hidden'] : [],
        );

        $style = collect($presentation['styles'])
            ->map(fn (string $value, string $property): string => $property.':'.$value)
            ->implode(';');
    @endphp

    {{-- A section an editor hid is gone for visitors, the same way an emptied
         region is. It stays in the page for an editor, dimmed, because the only
         other handle on it would be the outline panel and a section nobody can see
         is a section nobody can bring back. --}}
    @if(! $hidden || $canEdit)
        {{-- The wrapper exists for visitors too. Emitting it only for an editor would
             give the same page a different DOM after login, which is how a flex or
             grid parent silently changes shape. --}}
        <div
            @class($classes)
            @if($style !== '') style="{{ $style }}" @endif
            @if($canEdit)
                data-section-list="{{ $key }}"
                data-section-name="{{ $name }}"
                {{-- The human name for the outline panel. Falls back to the partial
                     name, so a section the registry does not declare still reads as
                     something rather than as a slug. --}}
                data-section-label="{{ $registry->label($name) }}"
                {{-- Present only when the registry gives this section something to
                     change, so the editor never offers a settings panel that would
                     open empty. --}}
                @if($registry->has($name))
                    data-section-configurable="1"
                    data-section-variant="{{ $variant }}"
                    data-section-hidden="{{ $hidden ? 'true' : 'false' }}"
                    data-section-settings="{{ json_encode($settings, JSON_UNESCAPED_UNICODE) }}"
                @endif
            @endif
        >
            @includeIf($view.$name, ['sectionVariant' => $variant, 'sectionSettings' => $settings])
        </div>
    @endif
@endforeach
