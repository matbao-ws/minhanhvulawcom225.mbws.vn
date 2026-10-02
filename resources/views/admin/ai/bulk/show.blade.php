@extends('admin.layouts.app')

@section('title', __('ai.bulk.review_title'))

@section('content')
    <x-admin.page-header :title="__('ai.bulk.review_title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.ai.bulk.index') }}">{{ __('ai.bulk.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.bulk.review_title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $class)
        @if(session($key))
            <div class="alert alert-{{ $class }}" role="alert">{{ session($key) }}</div>
        @endif
    @endforeach
    @if($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @endif

    <div class="card">
        <div class="card-body d-flex flex-wrap gap-4 small">
            <span>{{ __('ai.bulk.status') }}: <strong>{{ __('ai.bulk.statuses.'.$job->status) }}</strong></span>
            <span>{{ __('ai.bulk.items') }}: <strong>{{ $job->items_total }}</strong></span>
            <span>{{ __('ai.bulk.credits') }}: <strong>{{ $job->credits_estimated }}</strong></span>
            @if($job->stop_reason)
                <span class="text-danger">{{ __('ai.errors.'.$job->stop_reason) }}</span>
            @endif
        </div>
    </div>

    @if(! $job->isFinished())
        <div class="alert alert-info" role="status">
            <span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.bulk.running') }}
            <a class="ms-2" href="{{ url()->current() }}">{{ __('ai.bulk.reload') }}</a>
        </div>
    @elseif($suggestions->isEmpty())
        <div class="alert alert-success" role="status">{{ __('ai.bulk.nothing_left') }}</div>
    @else
        <form method="POST" action="{{ route('admin.ai.bulk.apply', ['job' => $job->id]) }}">
            @csrf
            <p class="small text-muted">{{ __('ai.bulk.review_note') }}</p>
            <div class="d-flex align-items-center gap-3 mb-2">
                <label class="form-check mb-0"><input class="form-check-input me-1" type="checkbox" data-check-all checked>{{ __('ai.bulk.select_all') }}</label>
                <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="ti ti-check me-1"></i>{{ __('ai.bulk.apply') }}</button>
            </div>

            @foreach($suggestions as $suggestion)
                @php
                    $option = $suggestion->options[0] ?? ['title' => '', 'description' => ''];
                    $known = $titles->get($suggestion->entity_type.':'.$suggestion->entity_id);
                @endphp
                <div class="card mb-2">
                    <div class="card-body py-3">
                        <div class="d-flex gap-2 align-items-start">
                            <input class="form-check-input mt-1" type="checkbox" name="chosen[]" value="{{ $suggestion->id }}" checked data-choice aria-label="{{ __('ai.bulk.choose') }}">
                            <div class="flex-grow-1">
                                <div class="small text-muted mb-1">{{ __('ai.bulk.types.'.$suggestion->entity_type) }} · {{ $known['title'] ?? '#'.$suggestion->entity_id }}</div>
                                <input type="text" class="form-control form-control-sm mb-1" name="title[{{ $suggestion->id }}]" value="{{ $option['title'] }}" maxlength="{{ \App\Services\AiGateway\MetaSuggestionService::TITLE_MAX }}" aria-label="{{ __('ai.meta.meta_title') }}">
                                <textarea class="form-control form-control-sm" name="description[{{ $suggestion->id }}]" rows="2" maxlength="{{ \App\Services\AiGateway\MetaSuggestionService::DESCRIPTION_MAX }}" aria-label="{{ __('ai.meta.meta_description') }}">{{ $option['description'] }}</textarea>
                                @if(filled($suggestion->old_values['title'] ?? null) || filled($suggestion->old_values['description'] ?? null))
                                    <div class="small text-muted mt-1">{{ __('ai.bulk.current') }}: {{ $suggestion->old_values['title'] ?: '—' }} · {{ $suggestion->old_values['description'] ?: '—' }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>{{ __('ai.bulk.apply') }}</button>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelector('[data-check-all]')?.addEventListener('change', function () {
                document.querySelectorAll('[data-choice]').forEach((box) => { box.checked = this.checked; });
            });
        });
    </script>
@endpush
