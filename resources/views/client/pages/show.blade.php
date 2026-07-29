@extends('client.layouts.app')

@section('title', $metaTitle ?: $title)
@section('meta_description', $metaDescription)

@push('styles')
<style id="client-page-css">
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; color: #20242a; font-family: Arial, sans-serif; }
    img { max-width: 100%; }
    {!! $css !!}
</style>
@endpush

@section('content')
    <main id="client-page-{{ $page->id }}">
        {!! $html !!}
    </main>
@endsection
