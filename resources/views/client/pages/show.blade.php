@extends('client.layouts.app')

@section('title', $metaTitle ?: $title)

{{-- Guarded: `@section($name, null)` makes Blade open an output buffer it never
     closes, because a null body means "the section content follows". --}}
@if($metaDescription)
    @section('meta_description', $metaDescription)
@endif

@push('styles')
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; color: #20242a; font-family: Arial, sans-serif; }
    img { max-width: 100%; }
</style>
@endpush

@section('content')
    <main id="client-page-{{ $page->id }}" translate="no" class="notranslate">
        {!! $html !!}
    </main>
@endsection
