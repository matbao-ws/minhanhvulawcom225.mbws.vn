@extends('client.layouts.app')

@section('title', $metaTitle ?: $title)

{{-- Guarded: `@section($name, null)` makes Blade open an output buffer it never
     closes, because a null body means "the section content follows". --}}
@if($metaDescription)
    @section('meta_description', $metaDescription)
@endif

@php($editing = \App\Support\ClientEditorChrome::editorActive())

@section('content')
    {{-- data-client-editable-root is what the inline editor looks for. A theme
         layout that renders page content in its own wrapper only has to carry
         this attribute; it does not have to reproduce the id convention. It is
         emitted only for an admin who may edit — a guest gets no hook at all. --}}
    <main id="client-page-{{ $page->id }}"
          @if($editing) data-client-editable-root @endif
          translate="no" class="notranslate">
        @if(trim((string) $html) !== '')
            {!! $html !!}
        @elseif($editing)
            {{-- A page nobody has written yet.

                 Edit mode makes this whole element contenteditable, but an empty
                 one is zero pixels tall and holds no text node, so there is
                 nowhere to click and nowhere to put the caret. The page looks
                 like a feature that does not exist. One paragraph is enough to
                 give the caret a home; from there the editor is an ordinary
                 document editor — Enter starts a new paragraph, the toolbar sets
                 headings and lists, the media picker inserts images.

                 It cannot be saved by accident: savePageBody() returns early
                 unless the page is dirty, and dirtiness only comes from a real
                 editing event. Opening and closing edit mode writes nothing.

                 A visitor gets none of this. A page with no content is empty,
                 and inventing a sentence for them would be inventing content. --}}
            <p>Nhập nội dung trang tại đây.</p>
        @endif
    </main>
@endsection
