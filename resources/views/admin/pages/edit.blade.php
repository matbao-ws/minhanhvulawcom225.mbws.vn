@extends('admin.layouts.app')

@section('title', __('admin.pages.edit'))

@section('content')
    <x-admin.page-header :title="__('admin.pages.edit')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.pages.index') }}">{{ __('admin.pages.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.pages.edit') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.pages.update', $page) }}" id="page-form">
        @csrf @method('PUT')
        @include('admin.pages._form')
    </form>

    @if($revisions->isNotEmpty())
    <div data-tour="page-form.revisions" class="card">
        <div class="card-body">
            <h5 class="mb-3">{{ __('admin.pages.revisions') }}</h5>
            <div class="list-group">
                @foreach($revisions as $revision)
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                        <span>{{ $revision->created_at?->format('d/m/Y H:i:s') }} · {{ $revision->creator?->name ?: __('admin.pages.system') }}</span>
                        <form method="POST" action="{{ route('admin.pages.revisions.restore', [$page, $revision]) }}">@csrf<button class="btn btn-sm btn-outline-warning" onclick="return confirm(@js(__('admin.pages.restore_confirm')))">{{ __('admin.pages.restore') }}</button></form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection
