@extends('admin.layouts.app')

@section('title', __('ai.bulk.title'))

@push('styles')
    <style>
        .ai-bulk-intro {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px 20px;
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 4px solid #3e66fb;
            background: #fff;
            box-shadow: 0 1px 4px rgba(42, 53, 71, 0.08);
        }
        .ai-bulk-intro-text {
            flex: 1 1 320px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            color: #2a3547;
            font-size: 15px;
            font-weight: 500;
            line-height: 1.5;
        }
        .ai-bulk-intro-text i {
            color: #3e66fb;
            font-size: 20px;
            line-height: 1.2;
        }
        .ai-bulk-intro label {
            margin: 0;
            color: #2a3547 !important;
        }
        /* Each kind keeps its own colour; red is left for errors only. */
        .ai-bulk-card,
        .is-meta {
            --kind-color: #3e66fb;
            --kind-soft: #ecf2ff;
        }
        .ai-bulk-card {
            border: 1px solid #e5eaf2;
            border-top: 3px solid var(--kind-color);
            box-shadow: none;
        }
        .is-seo-fix {
            --kind-color: #b06000;
            --kind-soft: #fef7e0;
        }
        .is-alt {
            --kind-color: #007b83;
            --kind-soft: #e4f7fb;
        }
        .ai-bulk-history {
            border-top: 1px solid #e5eaf2;
        }
        .ai-bulk-card .card-body {
            padding: 22px;
        }
        .ai-bulk-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .ai-bulk-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--kind-soft);
            color: var(--kind-color);
            font-size: 22px;
        }
        .ai-bulk-head h2 {
            margin: 0;
            font-size: 16.5px !important;
            line-height: 1.35;
            color: #1f2633 !important;
        }
        .ai-bulk-help {
            margin-bottom: 16px;
            color: #3d4a5c;
            font-size: 14.5px;
            line-height: 1.55;
        }
        .ai-bulk-types {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 12px;
        }
        .ai-bulk-type {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            padding: 8px 12px;
            border: 1px solid #e5eaf2;
            border-radius: 8px;
            cursor: pointer;
            color: #2a3547 !important;
            font-size: 14.5px !important;
        }
        .ai-bulk-type:hover {
            border-color: var(--kind-color);
            background: var(--kind-soft);
        }
        .ai-bulk-type .form-check-input:checked {
            background-color: var(--kind-color);
            border-color: var(--kind-color);
        }
        .ai-bulk-type .form-check-input {
            margin: 0;
            float: none;
        }
        .ai-bulk-pill {
            margin-left: auto;
            padding: 2px 10px;
            border-radius: 999px;
            background: var(--kind-soft);
            color: var(--kind-color);
            font-size: 13.5px;
            font-weight: 700;
        }
        .ai-bulk-count {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--kind-soft);
            color: var(--kind-color);
            font-size: 14.5px;
            font-weight: 700;
        }
        .ai-bulk-limit {
            display: flex;
            gap: 6px;
            align-items: flex-start;
            margin-bottom: 14px;
            color: #46546a;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .ai-bulk-limit i {
            font-size: 16px;
            line-height: 1.3;
        }
        .ai-bulk-done {
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 10px 12px;
            border-radius: 8px;
            background: #e6f9ed;
            color: #137333;
            font-size: 14.5px;
            font-weight: 600;
        }
        .ai-bulk-result:empty {
            display: none;
        }
        .ai-bulk-result {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f4f7fb;
            color: #2a3547;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
        }
        .ai-bulk-result.is-ok {
            background: #e6f9ed;
            color: #137333;
        }
        .ai-bulk-result.is-error {
            background: #fce8e6;
            color: #a3181f;
        }
        .ai-bulk-history h2 {
            font-size: 16.5px !important;
            color: #1f2633 !important;
        }
    </style>
@endpush

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

    <div class="ai-bulk-intro">
        <div class="ai-bulk-intro-text"><i class="ti ti-sparkles" aria-hidden="true"></i><span>{{ __('ai.bulk.intro') }}</span></div>
        @if($languages->count() > 1)
            <form data-tour="ai-bulk.locale" method="GET" class="d-flex align-items-center gap-2">
                <label for="bulk-locale">{{ __('ai.bulk.locale') }}</label>
                <select class="form-select form-select-sm w-auto" id="bulk-locale" name="content_locale" onchange="this.form.submit()">
                    @foreach($languages as $language)
                        <option value="{{ $language->code }}" @selected($language->code === $contentLocale)>{{ $language->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @php
        $kinds = [
            'meta' => ['icon' => 'ti-tags', 'count' => array_sum($counts['meta'])],
            'seo_fix' => ['icon' => 'ti-chart-arrows-vertical', 'count' => $counts['seo_fix']],
            'alt' => ['icon' => 'ti-photo-check', 'count' => $counts['alt']],
        ];
        $statusBadges = ['queued' => 'warning', 'running' => 'info', 'completed' => 'success', 'stopped' => 'danger'];
    @endphp

    <div class="row g-3 mb-4">
        @foreach($kinds as $kind => $info)
            <div data-tour="ai-bulk.kind-{{ str_replace('_', '-', $kind) }}" class="col-lg-4">
                <div class="card ai-bulk-card is-{{ str_replace('_', '-', $kind) }} h-100 mb-0">
                    <div class="card-body d-flex flex-column">
                        <div class="ai-bulk-head">
                            <span class="ai-bulk-icon"><i class="ti {{ $info['icon'] }}" aria-hidden="true"></i></span>
                            <h2 class="fw-semibold">{{ __('ai.bulk.kinds.'.$kind.'.title') }}</h2>
                        </div>
                        <div class="ai-bulk-help">{{ __('ai.bulk.kinds.'.$kind.'.help') }}</div>

                        @if($info['count'] === 0)
                            <div class="ai-bulk-done mt-auto"><i class="ti ti-circle-check" aria-hidden="true"></i>{{ __('ai.bulk.kinds.'.$kind.'.none') }}</div>
                        @else
                            <form method="POST" action="{{ route('admin.ai.bulk.store') }}" class="mt-auto" data-bulk-form data-estimate="{{ route('admin.ai.bulk.estimate') }}">
                                @csrf
                                <input type="hidden" name="kind" value="{{ $kind }}">
                                <input type="hidden" name="content_locale" value="{{ $contentLocale }}">
                                @if($kind === 'meta')
                                    <div class="ai-bulk-types">
                                        @foreach($counts['meta'] as $type => $count)
                                            @if($count > 0)
                                                <label class="ai-bulk-type">
                                                    <input class="form-check-input" type="checkbox" name="types[]" value="{{ $type }}" checked>
                                                    <span>{{ __('ai.bulk.types.'.$type) }}</span>
                                                    <span class="ai-bulk-pill">{{ $count >= 100 ? '100+' : $count }}</span>
                                                </label>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <div class="ai-bulk-count"><i class="ti ti-alert-circle" aria-hidden="true"></i>{{ __('ai.bulk.kinds.'.$kind.'.count', ['count' => $info['count']]) }}</div>
                                @endif
                                <div class="ai-bulk-limit"><i class="ti ti-info-circle" aria-hidden="true"></i><span>{{ __('ai.bulk.kinds.'.$kind.'.limit') }}</span></div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <button data-tour="ai-bulk.estimate" type="button" class="btn btn-outline-info btn-sm" data-bulk-estimate><i class="ti ti-calculator me-1"></i>{{ __('ai.bulk.estimate') }}</button>
                                    <button type="submit" class="btn btn-success btn-sm d-none" data-bulk-run><i class="ti ti-player-play me-1"></i>{{ __('ai.bulk.run') }}</button>
                                </div>
                                <div class="ai-bulk-result" data-bulk-result role="status"></div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($jobs->isNotEmpty())
        <div data-tour="ai-bulk.history" class="card ai-bulk-card ai-bulk-history">
            <div class="card-body">
                <h2 class="fw-semibold mb-3"><i class="ti ti-history me-1" aria-hidden="true"></i>{{ __('ai.bulk.history') }}</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ __('ai.bulk.started_at') }}</th><th>{{ __('ai.bulk.kind') }}</th><th>{{ __('ai.bulk.items') }}</th><th>{{ __('ai.bulk.status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($jobs as $job)
                                <tr>
                                    <td class="text-nowrap">{{ $job->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="fw-semibold is-{{ str_replace('_', '-', $job->kind()) }}"><i class="ti {{ $kinds[$job->kind()]['icon'] ?? 'ti-sparkles' }} me-1" style="color: var(--kind-color)" aria-hidden="true"></i>{{ __('ai.bulk.kinds.'.$job->kind().'.title') }}</td>
                                    <td>{{ $job->items_total }} <span class="badge bg-primary ms-1">{{ strtoupper($job->locale) }}</span></td>
                                    <td><span class="badge bg-{{ $statusBadges[$job->status] ?? 'info' }}">{{ __('ai.bulk.statuses.'.$job->status) }}</span></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-info" href="{{ route('admin.ai.bulk.show', ['job' => $job->id]) }}">{{ __('ai.bulk.open') }}</a></td>
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
            const text = @json($bulkText);

            document.querySelectorAll('[data-bulk-form]').forEach(function (form) {
                const result = form.querySelector('[data-bulk-result]');
                const run = form.querySelector('[data-bulk-run]');

                form.querySelector('[data-bulk-estimate]').addEventListener('click', async function () {
                    this.disabled = true;
                    result.className = 'ai-bulk-result';
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
                        result.className = 'ai-bulk-result';
                        result.textContent = text.estimate.replace('__C__', d.count).replace('__K__', d.credits_estimated).replace('__R__', d.credits_remaining);
                        if (d.credits_estimated <= d.credits_remaining) {
                            result.className = 'ai-bulk-result is-ok';
                            run.classList.remove('d-none');
                        } else {
                            result.className = 'ai-bulk-result is-error';
                            result.textContent += ' ' + text.short;
                        }
                    } catch (error) {
                        result.className = 'ai-bulk-result is-error';
                        result.textContent = error.message;
                    } finally {
                        this.disabled = false;
                    }
                });
                form.addEventListener('change', () => run.classList.add('d-none'));
            });
        });
    </script>
@endpush
