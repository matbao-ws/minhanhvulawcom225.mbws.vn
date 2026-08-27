@extends('client.layouts.app')

@section('title', $metaTitle ?: $title)

{{-- Guarded: `@section($name, null)` makes Blade open an output buffer it never
     closes, because a null body means "the section content follows". --}}
@if($metaDescription)
    @section('meta_description', $metaDescription)
@endif

@section('content')
    {{-- data-client-editable-root is what the inline editor looks for. A theme
         layout that renders page content in its own wrapper only has to carry
         this attribute; it does not have to reproduce the id convention. It is
         emitted only for an admin who may edit — a guest gets no hook at all. --}}
    <main id="client-page-{{ $page->id }}"
          @if(auth()->user()?->canEditClientContent()) data-client-editable-root @endif
          translate="no" class="notranslate">
        {!! $html !!}
    </main>
@endsection
