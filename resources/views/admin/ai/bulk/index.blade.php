@extends('admin.layouts.app')

@section('title', __('ai.bulk.title'))

@section('content')
    <x-admin.page-header :title="__('ai.bulk.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.bulk.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $class)
        @if(session($key))
            <div class="alert alert-{{ $class }}" role="alert">{{ session($key) }}</div>
        @endif
    @endforeach

    <div class="card">
        <div class="card-body">
            <h2 class="h6 fw-semibold">{{ __('ai.bulk.meta_heading') }}</h2>
            <p class="text-muted small">{{ __('ai.bulk.intro') }}</p>

            @if($languages->count() > 1)
                <form method="GET" class="d-flex align-items-center gap-2 mb-3">
                    <label class="small text-muted" for="bulk-locale">{{ __('ai.bulk.locale') }}</label>
                    <select class="form-select form-select-sm w-auto" id="bulk-locale" name="content_locale" onchange="this.form.submit()">
                        @foreach($languages as $language)
                            <option value="{{ $language->code }}" @selected($language->code === $contentLocale)>{{ $language->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if(array_sum($counts) === 0)
                <div class="alert alert-success mb-0" role="status"><i class="ti ti-circle-check me-1"></i>{{ __('ai.bulk.all_good') }}</div>
            @else
                <form method="POST" action="{{ route('admin.ai.bulk.store') }}" data-bulk-form data-estimate="{{ route('admin.ai.bulk.estimate') }}">
                    @csrf
                    <input type="hidden" name="content_locale" value="{{ $contentLocale }}">
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        @foreach($counts as $type => $count)
                            @if($count > 0)
                                <label class="form-check border rounded px-3 py-2 mb-0">
                                    <input class="form-check-input me-1" type="checkbox" name="types[]" value="{{ $type }}" checked>
                                    {{ __('ai.bulk.types.'.$type) }}: <strong>{{ $count >= 100 ? '100+' : $count }}</strong>
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <p class="small text-muted">{{ __('ai.bulk.limit_note') }}</p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-outline-primary" data-bulk-estimate><i class="ti ti-calculator me-1"></i>{{ __('ai.bulk.estimate') }}</button>
                        <button type="submit" class="btn btn-primary d-none" data-bulk-run><i class="ti ti-player-play me-1"></i>{{ __('ai.bulk.run') }}</button>
                        <span class="small" data-bulk-result role="status"></span>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if($jobs->isNotEmpty())
        <div class="card">
            <div class="card-body">
                <h2 class="h6 fw-semibold">{{ __('ai.bulk.history') }}</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('ai.bulk.started_at') }}</th><th>{{ __('ai.bulk.items') }}</th><th>{{ __('ai.bulk.status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($jobs as $job)
                                <tr>
                                    <td>{{ $job->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $job->items_total }} · {{ strtoupper($job->locale) }}</td>
                                    <td>{{ __('ai.bulk.statuses.'.$job->status) }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.ai.bulk.show', ['job' => $job->id]) }}">{{ __('ai.bulk.open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection

@php
    $bulkText = [
        'estimate' => __('ai.bulk.estimate_result', ['count' => '__C__', 'credits' => '__K__', 'remaining' => '__R__']),
        'short' => __('ai.bulk.not_enough'),
        'failed' => __('ai.errors.default'),
    ];
@endphp
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-bulk-form]');
            if (!form) return;
            const result = form.querySelector('[data-bulk-result]');
            const run = form.querySelector('[data-bulk-run]');
            const text = @json($bulkText);

            form.querySelector('[data-bulk-estimate]').addEventListener('click', async function () {
                this.disabled = true;
                result.className = 'small text-muted';
                result.textContent = '…';
                run.classList.add('d-none');
                try {
                    const response = await fetch(form.dataset.estimate, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value},
                        body: new FormData(form),
                    });
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok || !json.success) throw new Error(json.message || text.failed);
                    const d = json.data;
                    result.className = 'small';
                    result.textContent = text.estimate.replace('__C__', d.count).replace('__K__', d.credits_estimated).replace('__R__', d.credits_remaining);
                    if (d.credits_estimated <= d.credits_remaining) {
                        run.classList.remove('d-none');
                    } else {
                        result.className = 'small text-danger';
                        result.textContent += ' ' + text.short;
                    }
                } catch (error) {
                    result.className = 'small text-danger';
                    result.textContent = error.message;
                } finally {
                    this.disabled = false;
                }
            });
            form.addEventListener('change', () => run.classList.add('d-none'));
        });
    </script>
@endpush
