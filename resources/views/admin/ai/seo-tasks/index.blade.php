@extends('admin.layouts.app')

@section('title', __('ai.tasks.title'))

@push('styles')
    <style>
        .seo-task-page { width: 100%; color: #2a3547; }
        .seo-task-page .text-muted { color: #5a6a85 !important; }
        .seo-task-page__intro { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; padding: .9rem 1rem; border-left: 3px solid #c01e25; border-radius: 0 .85rem .85rem 0; color: #526174; background: linear-gradient(90deg, #fff3f4, #fff); }
        .seo-task-page__intro-icon, .seo-task-stat__icon { display: inline-flex; align-items: center; justify-content: center; border-radius: .65rem; }
        .seo-task-page__intro-icon { width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem; color: #c01e25; background: #ffe7e9; }
        .seo-task-stat { position: relative; height: 100%; overflow: hidden; border: 1px solid #e2e8f0; border-radius: .9rem; background: linear-gradient(145deg, #fff, #f8faff); box-shadow: 0 .35rem 1.1rem rgba(44, 58, 87, .045); transition: transform .18s ease, box-shadow .18s ease; }
        .seo-task-stat:hover { transform: translateY(-2px); box-shadow: 0 .65rem 1.4rem rgba(42, 53, 71, .09); }
        .seo-task-stat__body { padding: 1rem 1.1rem; }
        .seo-task-stat__top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .seo-task-stat__label { color: #627391; font-size: .78rem; font-weight: 700; }
        .seo-task-stat__icon { width: 2rem; height: 2rem; color: #c01e25; background: #fff0f1; }
        .seo-task-stat--impressions .seo-task-stat__icon { color: #365a8c; background: #edf4ff; }
        .seo-task-stat--ctr .seo-task-stat__icon { color: #9a5800; background: #fff6e2; }
        .seo-task-stat--position .seo-task-stat__icon { color: #137333; background: #e6f9ed; }
        .seo-task-stat__value { margin-top: .35rem; color: #1d2939; font-size: 1.5rem; font-weight: 800; line-height: 1.15; letter-spacing: -.03em; }
        .seo-task-stat__change { margin-top: .35rem; font-size: .75rem; font-weight: 700; }
        .seo-task-card { overflow: hidden; border: 1px solid #e0e7ef; border-left: 4px solid #6c7a8b; border-radius: 1rem; box-shadow: 0 .4rem 1.25rem rgba(44, 58, 87, .055); transition: transform .18s ease, box-shadow .18s ease; }
        .seo-task-card--low_ctr { border-left-color: #d88a00; }
        .seo-task-card--striking_distance { border-left-color: #365a8c; }
        .seo-task-card--declining { border-left-color: #c5221f; }
        .seo-task-card--on_page { border-left-color: #526174; }
        .seo-task-card:hover { transform: translateY(-2px); box-shadow: 0 .75rem 1.75rem rgba(44, 58, 87, .10); }
        .seo-task-card__body { padding: 1.15rem 1.25rem; }
        .seo-task-card__heading { display: flex; gap: .85rem; align-items: flex-start; }
        .seo-task-card__heading > div { min-width: 0; flex: 1; }
        .seo-task-card__icon { display: inline-flex; width: 2.5rem; height: 2.5rem; flex: 0 0 2.5rem; align-items: center; justify-content: center; border-radius: .75rem; color: #435267; background: #e8eef5; }
        .seo-task-card--low_ctr .seo-task-card__icon { color: #9a5800; background: #fff6e2; }
        .seo-task-card--striking_distance .seo-task-card__icon { color: #365a8c; background: #edf4ff; }
        .seo-task-card--declining .seo-task-card__icon { color: #c5221f; background: #fce8e6; }
        .seo-task-card__title { color: #26364a; font-size: 1rem; font-weight: 800; }
        .seo-task-card__url { display: block; margin-top: .2rem; overflow: hidden; color: #365a8c; font-size: .78rem; text-decoration: underline; text-decoration-color: currentColor; text-underline-offset: 2px; text-overflow: ellipsis; white-space: nowrap; }
        .seo-task-card__url:hover { color: #243f68; }
        .seo-task-card__help { margin: 1rem 0; padding: .7rem .8rem; border-radius: .65rem; color: #526174; background: #f7f9fc; font-size: .82rem; }
        .seo-task-card__metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; }
        .seo-task-card__metrics > span { padding: .55rem .6rem; border: 1px solid #e3e9f0; border-radius: .6rem; color: #627391; background: #fbfcff; font-size: .7rem; font-weight: 700; }
        .seo-task-card__metrics > span strong { display: block; margin-top: .15rem; color: #35425b; font-size: .85rem; }
        .seo-task-card__issues { margin: 1rem 0 0; padding-left: 1.15rem; color: #526174; font-size: .8rem; }
        .seo-task-card__issues li + li { margin-top: .25rem; }
        .seo-task-card__footer { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #dce4ec; }
        .seo-task-card__queries { margin-top: .8rem; font-size: .8rem; }
        .seo-task-query { border: 1px solid #d9e2ec; color: #344256 !important; background: #f1f5f9 !important; }
        .seo-task-page [data-seo-advice-result].alert-light { border-color: #d8e1eb !important; color: #2a3547 !important; background: #f8fafc !important; }
        .seo-task-group { color: #26364a; }
        .seo-task-group--critical { color: #c5221f; }
        .seo-task-card.is-marked { opacity: .55; }
        .seo-task-setup { overflow: hidden; border: 1px solid #dce6f5; border-radius: 1rem; box-shadow: 0 .45rem 1.3rem rgba(44, 58, 87, .045); }
        .seo-task-setup__body { display: flex; align-items: center; gap: 1.1rem; padding: 1.35rem 1.5rem; background: linear-gradient(115deg, #fbfcff, #f2f7ff); }
        .seo-task-setup__icon { display: inline-flex; width: 3rem; height: 3rem; flex: 0 0 3rem; align-items: center; justify-content: center; border-radius: .9rem; color: #365a8c; background: #e4eeff; font-size: 1.35rem; }
        .seo-task-setup__content { min-width: 0; flex: 1; }
        .seo-task-setup__eyebrow { margin-bottom: .2rem; color: #627391; font-size: .75rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .seo-task-setup__title { margin: 0 0 .25rem; color: #26364a; font-size: 1.05rem; font-weight: 800; }
        .seo-task-setup__hint { margin: 0; color: #5a6a85; font-size: .85rem; line-height: 1.5; }
        .seo-task-setup__action { flex: 0 0 auto; white-space: nowrap; }
        [data-bs-theme="dark"] .seo-task-group { color: #f2f6fb; }
        [data-bs-theme="dark"] .seo-task-group--critical { color: #ffb9b6; }
        .seo-task-page a:focus-visible, .seo-task-page button:focus-visible { outline: 3px solid rgba(192, 30, 37, .35); outline-offset: 2px; }
        [data-bs-theme="dark"] .seo-task-page { color: #edf2f8; }
        [data-bs-theme="dark"] .seo-task-page .text-muted { color: #c4cfde !important; }
        [data-bs-theme="dark"] .seo-task-page__intro { color: #d4dfef; background: linear-gradient(90deg, rgba(192, 30, 37, .2), rgba(34, 50, 72, 0)); }
        [data-bs-theme="dark"] .seo-task-stat { border-color: #3b4c64; background: linear-gradient(145deg, #223248, #1d2b3f); }
        [data-bs-theme="dark"] .seo-task-stat__label, [data-bs-theme="dark"] .seo-task-card__help, [data-bs-theme="dark"] .seo-task-card__issues { color: #c4cfde; }
        [data-bs-theme="dark"] .seo-task-stat__value, [data-bs-theme="dark"] .seo-task-card__title, [data-bs-theme="dark"] .seo-task-card__metrics > span strong { color: #f2f6fb; }
        [data-bs-theme="dark"] .seo-task-card { border-color: #3b4c64; background: #203046; }
        [data-bs-theme="dark"] .seo-task-card__help, [data-bs-theme="dark"] .seo-task-card__metrics > span { border-color: #40536d; color: #c4cfde; background: #293a51; }
        [data-bs-theme="dark"] .seo-task-card__footer { border-color: #40536d; }
        [data-bs-theme="dark"] .seo-task-card__url { color: #b9d3ff; }
        [data-bs-theme="dark"] .seo-task-query { border-color: #40536d; color: #dce7f6 !important; background: #2b3b51 !important; }
        [data-bs-theme="dark"] .seo-task-page [data-seo-advice-result].alert-light { border-color: #40536d !important; color: #edf2f8 !important; background: #223248 !important; }
        [data-bs-theme="dark"] .seo-task-page .text-success { color: #98e3b4 !important; }
        [data-bs-theme="dark"] .seo-task-page .text-danger { color: #ffb9b6 !important; }
        [data-bs-theme="dark"] .seo-task-page .text-warning { color: #ffdc8a !important; }
        [data-bs-theme="dark"] .seo-task-setup { border-color: #3b4c64; }
        [data-bs-theme="dark"] .seo-task-setup__body { background: linear-gradient(115deg, #203046, #263a54); }
        [data-bs-theme="dark"] .seo-task-setup__icon { color: #c6ddff; background: #314a70; }
        [data-bs-theme="dark"] .seo-task-setup__title { color: #edf2f8; }
        [data-bs-theme="dark"] .seo-task-setup__eyebrow, [data-bs-theme="dark"] .seo-task-setup__hint { color: #c4cfde; }
        @media (max-width: 575.98px) {
            .seo-task-card__metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .seo-task-setup__body { align-items: flex-start; flex-wrap: wrap; padding: 1.1rem; }
            .seo-task-setup__action { margin-left: 4.1rem; }
        }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="__('ai.tasks.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.tasks.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @if(! $connected)
        <div data-tour="seo-tasks.setup" class="card seo-task-setup">
            <div class="seo-task-setup__body">
                <span class="seo-task-setup__icon"><i class="ti ti-brand-google" aria-hidden="true"></i></span>
                <div class="seo-task-setup__content">
                    <div class="seo-task-setup__eyebrow">Google Search Console</div>
                    <h2 class="seo-task-setup__title">{{ __('ai.tasks.not_connected') }}</h2>
                    <p class="seo-task-setup__hint">{{ __('ai.tasks.connect_hint') }}</p>
                </div>
                @if(auth()->user()?->can('settings.view') && auth()->user()?->can('settings.update'))
                    <a data-tour="seo-tasks.connect" class="btn btn-primary btn-sm seo-task-setup__action" href="{{ route('admin.settings.index', ['tab' => 'analytics']) }}">
                        {{ __('ai.tasks.connect') }}<i class="ti ti-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    @elseif($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @elseif($weekly['synced_through'] === null)
        <div class="alert alert-info" role="status"><i class="ti ti-clock me-1"></i>{{ __('ai.tasks.pending') }}</div>
    @else
        <div class="seo-task-page">
        <p class="seo-task-page__intro small">
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
            <div data-tour="seo-tasks.stats" class="row g-3 mb-4">
                @foreach($stats as [$key, $shown, $value, $previous, $higherIsBetter])
                    <div class="col-6 col-lg-3">
                        <div class="seo-task-stat seo-task-stat--{{ $key }}">
                            <div class="seo-task-stat__body">
                                <div class="seo-task-stat__top">
                                    <div class="seo-task-stat__label">{{ __('ai.tasks.'.$key) }}</div>
                                    <span class="seo-task-stat__icon"><i class="ti ti-chart-bar" aria-hidden="true"></i></span>
                                </div>
                                <div class="seo-task-stat__value">{{ $shown }}</div>
                                @if($value !== null && $previous)
                                    @php
                                        $change = ($value - $previous) / $previous * 100;
                                        $good = $higherIsBetter ? $change >= 0 : $change <= 0;
                                    @endphp
                                    <div class="seo-task-stat__change {{ $good ? 'text-success' : 'text-danger' }}">
                                        {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 0) }}% <span class="text-muted">{{ __('ai.tasks.vs_previous') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @php $currentPriority = null; @endphp
        @forelse($weekly['tasks'] as $task)
            @if(($task['priority'] ?? null) && $task['priority'] !== $currentPriority)
                @php $currentPriority = $task['priority']; @endphp
                <h2 class="h6 fw-bold mt-4 mb-3 seo-task-group seo-task-group--{{ $currentPriority }}">{{ __('ai.tasks.priorities.'.$currentPriority) }}</h2>
            @endif
            @php
                $entity = $task['entity'] ?? null;
                $metrics = $task['metrics'] ?? [];
                $badge = ['low_ctr' => 'warning', 'striking_distance' => 'primary', 'declining' => 'danger', 'on_page' => 'secondary'][$task['type']] ?? 'secondary';
                $typeIcon = ['low_ctr' => 'ti-click', 'striking_distance' => 'ti-trending-up', 'declining' => 'ti-trending-down', 'on_page' => 'ti-file-alert'][$task['type']] ?? 'ti-sparkles';
            @endphp
            <div data-tour="seo-tasks.task" class="card mb-3 seo-task-card seo-task-card--{{ $task['type'] }}" data-seo-task="{{ $task['id'] }}">
                <div class="seo-task-card__body">
                    <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                        <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}"><i class="ti {{ $typeIcon }} me-1" aria-hidden="true"></i>{{ __('ai.tasks.types.'.$task['type']) }}</span>
                        @if(($task['gain'] ?? 0) > 1)
                            <span class="badge bg-success-subtle text-success">{{ __('ai.tasks.gain', ['count' => $task['gain']]) }}</span>
                        @endif
                    </div>

                    <div class="seo-task-card__heading">
                        <span class="seo-task-card__icon"><i class="ti {{ $typeIcon }}" aria-hidden="true"></i></span>
                        <div>
                            <h3 class="seo-task-card__title mb-1">{{ $entity['title'] ?? (rawurldecode((string) parse_url((string) $task['page'], PHP_URL_PATH)) ?: $task['page']) }}</h3>
                            @if($task['page'])
                                <a class="seo-task-card__url" href="{{ $task['page'] }}" target="_blank" rel="noopener noreferrer">{{ $task['page'] }}</a>
                            @endif
                        </div>
                    </div>
                    <p class="seo-task-card__help">{{ __('ai.tasks.type_help.'.$task['type']) }}</p>

                    @if($task['type'] !== 'on_page')
                        <div class="seo-task-card__metrics">
                            <span>{{ __('ai.tasks.clicks') }}: <strong>{{ number_format($metrics['clicks'] ?? 0) }}</strong>@if($task['type'] === 'declining') <span class="text-muted">(← {{ number_format($metrics['previous_clicks'] ?? 0) }})</span>@endif</span>
                            <span>{{ __('ai.tasks.impressions') }}: <strong>{{ number_format($metrics['impressions'] ?? 0) }}</strong></span>
                            <span>{{ __('ai.tasks.ctr') }}: <strong>{{ number_format(($metrics['ctr'] ?? 0) * 100, 1) }}%</strong></span>
                            <span>{{ __('ai.tasks.position') }}: <strong>{{ $metrics['position'] ?? '–' }}</strong></span>
                        </div>
                    @endif

                    @if(! empty($task['issues']))
                        <ul class="seo-task-card__issues">
                            @foreach($task['issues'] as $issue)
                                <li>{{ __('ai.tasks.issues.'.$issue) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if(! empty($task['queries']))
                        <div class="seo-task-card__queries">
                            <span class="text-muted">{{ __('ai.tasks.queries') }}:</span>
                            @foreach($task['queries'] as $query)
                                <span class="badge fw-normal me-1 seo-task-query">{{ $query['query'] }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="seo-task-card__footer">
                        @if($task['edit_url'])
                            <a class="btn btn-primary btn-sm" href="{{ $task['edit_url'] }}"><i class="ti ti-edit me-1"></i>{{ __('ai.tasks.open') }}</a>
                        @else
                            <span class="small text-muted">{{ __('ai.tasks.no_editor') }}</span>
                        @endif
                        <button data-tour="seo-tasks.advice" type="button" class="btn btn-outline-primary btn-sm" data-seo-advice="{{ route('admin.ai.seo-tasks.advice', ['task' => $task['id']]) }}" title="{{ __('ai.tasks.advice_note') }}">
                            <i class="ti ti-sparkles me-1"></i>{{ __('ai.tasks.advice') }}
                        </button>
                        <span data-tour="seo-tasks.mark" class="ms-auto d-inline-flex gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm" data-seo-mark="done" data-url="{{ route('admin.ai.seo-tasks.status', ['task' => $task['id']]) }}"><i class="ti ti-check me-1"></i>{{ __('ai.tasks.done') }}</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-seo-mark="dismissed" data-url="{{ route('admin.ai.seo-tasks.status', ['task' => $task['id']]) }}"><i class="ti ti-eye-off me-1"></i>{{ __('ai.tasks.dismiss') }}</button>
                        </span>
                    </div>
                    <div class="alert alert-light border small mt-3 mb-0 d-none" data-seo-advice-result role="status"></div>
                </div>
            </div>
        @empty
            <div class="alert alert-success" role="status"><i class="ti ti-circle-check me-1"></i>{{ __('ai.tasks.empty') }}</div>
        @endforelse
        @if(($weekly['hidden'] ?? 0) > 0)
            <p class="small text-muted mt-3 mb-0"><i class="ti ti-eye-off me-1" aria-hidden="true"></i>{{ __('ai.tasks.hidden_note', ['count' => $weekly['hidden']]) }}</p>
        @endif
        </div>
    @endif
@endsection

@php $seoMarkText = ['undo' => __('ai.tasks.undo'), 'failed' => __('ai.errors.default')]; @endphp
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            // Done / dismiss: the card stays, dimmed, with an undo until the page is reloaded.
            const markText = @json($seoMarkText);
            async function mark(url, status) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({status}),
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok || !json.success) throw new Error(json.message || markText.failed);
                return json;
            }
            document.querySelectorAll('[data-seo-mark]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    const card = button.closest('[data-seo-task]');
                    const group = button.parentElement;
                    group.querySelectorAll('button').forEach((b) => { b.disabled = true; });
                    try {
                        const json = await mark(button.dataset.url, button.dataset.seoMark);
                        card.classList.add('is-marked');
                        const note = document.createElement('span');
                        note.className = 'small text-muted';
                        note.textContent = json.message + ' ';
                        const undo = document.createElement('button');
                        undo.type = 'button';
                        undo.className = 'btn btn-link btn-sm p-0';
                        undo.textContent = markText.undo;
                        undo.addEventListener('click', async function () {
                            undo.disabled = true;
                            try {
                                await mark(button.dataset.url, 'open');
                                card.classList.remove('is-marked');
                                note.remove();
                                group.querySelectorAll('button').forEach((b) => { b.disabled = false; });
                                group.classList.remove('d-none');
                            } catch (error) { undo.disabled = false; window.alert(error.message); }
                        });
                        note.appendChild(undo);
                        group.classList.add('d-none');
                        group.after(note);
                    } catch (error) {
                        group.querySelectorAll('button').forEach((b) => { b.disabled = false; });
                        window.alert(error.message);
                    }
                });
            });

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
