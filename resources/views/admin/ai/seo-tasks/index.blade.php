@extends('admin.layouts.app')

@section('title', __('ai.tasks.title'))

@section('content')
    <x-admin.page-header :title="__('ai.tasks.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.tasks.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @if(! $connected)
        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <i class="ti ti-plug-connected-x fs-6 text-muted"></i>
                <span class="me-auto">{{ __('ai.tasks.not_connected') }}</span>
                @can('settings.view')
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.settings.index') }}">{{ __('ai.tasks.connect') }}</a>
                @endcan
            </div>
        </div>
    @elseif($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @elseif($weekly['synced_through'] === null)
        <div class="alert alert-info" role="status"><i class="ti ti-clock me-1"></i>{{ __('ai.tasks.pending') }}</div>
    @else
        <p class="text-muted small mb-3">
            {{ __('ai.tasks.intro') }}
            <span class="text-nowrap">· {{ __('ai.tasks.synced_through', ['date' => \Illuminate\Support\Carbon::parse($weekly['synced_through'])->format('d/m/Y')]) }}</span>
        </p>

        @if($weekly['totals'])
            @php
                $now = $weekly['totals']['current'];
                $before = $weekly['totals']['previous'];
                $stats = [
                    ['clicks', number_format($now['clicks']), $now['clicks'], $before['clicks'], true],
                    ['impressions', number_format($now['impressions']), $now['impressions'], $before['impressions'], true],
                    ['ctr', number_format($now['ctr'] * 100, 1).'%', $now['ctr'], $before['ctr'], true],
                    // A lower position is better.
                    ['position', $now['position'] ?? '–', $now['position'], $before['position'], false],
                ];
            @endphp
            <div class="row g-3 mb-4">
                @foreach($stats as [$key, $shown, $value, $previous, $higherIsBetter])
                    <div class="col-6 col-lg-3">
                        <div class="card h-100 mb-0">
                            <div class="card-body py-3">
                                <div class="small text-muted">{{ __('ai.tasks.'.$key) }}</div>
                                <div class="fs-5 fw-semibold">{{ $shown }}</div>
                                @if($value !== null && $previous)
                                    @php
                                        $change = ($value - $previous) / $previous * 100;
                                        $good = $higherIsBetter ? $change >= 0 : $change <= 0;
                                    @endphp
                                    <div class="small {{ $good ? 'text-success' : 'text-danger' }}">
                                        {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 0) }}% <span class="text-muted">{{ __('ai.tasks.vs_previous') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @forelse($weekly['tasks'] as $task)
            @php
                $entity = $task['entity'] ?? null;
                $metrics = $task['metrics'] ?? [];
                $badge = ['low_ctr' => 'warning', 'striking_distance' => 'primary', 'declining' => 'danger', 'on_page' => 'secondary'][$task['type']] ?? 'secondary';
            @endphp
            <div class="card mb-3" data-seo-task="{{ $task['id'] }}">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                        <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}">{{ __('ai.tasks.types.'.$task['type']) }}</span>
                        @if(($task['gain'] ?? 0) > 1)
                            <span class="badge bg-success-subtle text-success">{{ __('ai.tasks.gain', ['count' => $task['gain']]) }}</span>
                        @endif
                    </div>

                    <h3 class="h6 fw-semibold mb-1">{{ $entity['title'] ?? (rawurldecode((string) parse_url((string) $task['page'], PHP_URL_PATH)) ?: $task['page']) }}</h3>
                    @if($task['page'])
                        <a class="small text-muted text-break" href="{{ $task['page'] }}" target="_blank" rel="noopener noreferrer">{{ $task['page'] }}</a>
                    @endif
                    <p class="small text-muted mt-2 mb-2">{{ __('ai.tasks.type_help.'.$task['type']) }}</p>

                    @if($task['type'] !== 'on_page')
                        <div class="d-flex flex-wrap gap-3 small mb-2">
                            <span>{{ __('ai.tasks.clicks') }}: <strong>{{ number_format($metrics['clicks'] ?? 0) }}</strong>@if($task['type'] === 'declining') <span class="text-muted">(← {{ number_format($metrics['previous_clicks'] ?? 0) }})</span>@endif</span>
                            <span>{{ __('ai.tasks.impressions') }}: <strong>{{ number_format($metrics['impressions'] ?? 0) }}</strong></span>
                            <span>{{ __('ai.tasks.ctr') }}: <strong>{{ number_format(($metrics['ctr'] ?? 0) * 100, 1) }}%</strong></span>
                            <span>{{ __('ai.tasks.position') }}: <strong>{{ $metrics['position'] ?? '–' }}</strong></span>
                        </div>
                    @endif

                    @if(! empty($task['issues']))
                        <ul class="small mb-2 ps-3">
                            @foreach($task['issues'] as $issue)
                                <li>{{ __('ai.tasks.issues.'.$issue) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if(! empty($task['queries']))
                        <div class="small mb-2">
                            <span class="text-muted">{{ __('ai.tasks.queries') }}:</span>
                            @foreach($task['queries'] as $query)
                                <span class="badge bg-light text-dark fw-normal me-1">{{ $query['query'] }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        @if($task['edit_url'])
                            <a class="btn btn-primary btn-sm" href="{{ $task['edit_url'] }}"><i class="ti ti-edit me-1"></i>{{ __('ai.tasks.open') }}</a>
                        @else
                            <span class="small text-muted">{{ __('ai.tasks.no_editor') }}</span>
                        @endif
                        <button type="button" class="btn btn-outline-primary btn-sm" data-seo-advice="{{ route('admin.ai.seo-tasks.advice', ['task' => $task['id']]) }}" title="{{ __('ai.tasks.advice_note') }}">
                            <i class="ti ti-sparkles me-1"></i>{{ __('ai.tasks.advice') }}
                        </button>
                    </div>
                    <div class="alert alert-light border small mt-3 mb-0 d-none" data-seo-advice-result role="status"></div>
                </div>
            </div>
        @empty
            <div class="alert alert-success" role="status"><i class="ti ti-circle-check me-1"></i>{{ __('ai.tasks.empty') }}</div>
        @endforelse
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            document.querySelectorAll('[data-seo-advice]').forEach(function (button) {
                const box = button.closest('[data-seo-task]').querySelector('[data-seo-advice-result]');

                button.addEventListener('click', async function () {
                    button.disabled = true;
                    box.classList.remove('d-none', 'alert-danger');
                    box.classList.add('alert-light');
                    box.textContent = '…';

                    try {
                        const response = await fetch(button.dataset.seoAdvice, {
                            method: 'POST',
                            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                        });
                        const json = await response.json().catch(() => ({}));
                        if (!response.ok || !json.success) {
                            throw new Error(json.message || @json(__('ai.errors.default')));
                        }

                        // Model output is plain text: written with textContent only.
                        box.textContent = '';
                        const summary = document.createElement('p');
                        summary.className = 'mb-2';
                        summary.textContent = json.data.advice.summary;
                        const list = document.createElement('ol');
                        list.className = 'mb-0 ps-3';
                        json.data.advice.steps.forEach(function (step) {
                            const item = document.createElement('li');
                            item.textContent = step;
                            list.appendChild(item);
                        });
                        box.append(summary, list);
                    } catch (error) {
                        box.classList.remove('alert-light');
                        box.classList.add('alert-danger');
                        box.textContent = error.message;
                        button.disabled = false;
                    }
                });
            });
        });
    </script>
@endpush
