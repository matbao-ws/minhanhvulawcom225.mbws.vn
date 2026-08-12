@extends('client.layouts.app')

@section('title', $metaTitle ?: $title)
@section('meta_description', $metaDescription)

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
