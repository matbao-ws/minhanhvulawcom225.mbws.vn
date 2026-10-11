@extends('admin.layouts.app')

@section('title', __('admin.core_update.title'))

@push('styles')
    <style>
        .release-notes h2, .release-notes h3 { font-size: .9rem; font-weight: 600; margin: .75rem 0 .35rem; }
        .release-notes h2:first-child, .release-notes h3:first-child { margin-top: 0; }
        .release-notes ul { padding-left: 1.1rem; margin-bottom: .5rem; }
        .release-notes li { margin-bottom: .25rem; }
        .release-notes p:last-child, .release-notes ul:last-child { margin-bottom: 0; }
        .update-steps { list-style: none; padding: 0; margin: 0; }
        .update-steps li { display: flex; align-items: center; gap: .6rem; padding: .35rem 0; color: var(--bs-secondary-color); }
        .update-steps .dot { width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--bs-light); flex-shrink: 0; }
        .update-steps li.done { color: var(--bs-body-color); }
        .update-steps li.done .dot { background: var(--bs-success-bg-subtle); color: var(--bs-success); }
        .update-steps li.current { color: var(--bs-body-color); font-weight: 600; }
        .update-steps li.current .dot { background: var(--bs-primary-bg-subtle); color: var(--bs-primary); }
        .update-steps li.current .dot i { animation: update-spin 1.2s linear infinite; }
        @keyframes update-spin { to { transform: rotate(360deg); } }
        .update-clock { font-variant-numeric: tabular-nums; }
        .core-update-card { overflow: hidden; border: 1px solid #e4ebf4; border-radius: 1rem; box-shadow: 0 .45rem 1.3rem rgba(43, 57, 82, .045); }
        .core-update-card .card-body { padding: 1.35rem; }
        .core-update-card__eyebrow { color: #71809a; font-size: .75rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .core-update-card__version { margin: .2rem 0 1.15rem; color: #24344c; font-size: 2rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
        .core-update-state { display: flex; align-items: flex-start; gap: .75rem; padding: .9rem; border: 1px solid; border-radius: .8rem; }
        .core-update-state + .core-update-state { margin-top: .65rem; }
        .core-update-state__icon, .core-update-empty__icon { display: inline-flex; width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem; align-items: center; justify-content: center; border-radius: .7rem; font-size: 1.1rem; }
        .core-update-state__title { margin-bottom: .2rem; color: #344256; font-size: .875rem; font-weight: 800; }
        .core-update-state--warning { border-color: #f2d29b; background: #fff9ed; }
        .core-update-state--warning .core-update-state__icon { color: #a76200; background: #fff0cf; }
        .core-update-state--danger { border-color: #efc4c7; background: #fff5f5; }
        .core-update-state--danger .core-update-state__icon { color: #b4232a; background: #ffe5e7; }
        .core-update-state--success { border-color: #bce6cc; background: #f2fcf6; }
        .core-update-state--success .core-update-state__icon { color: #187a42; background: #ddf6e7; }
        .core-update-empty { display: flex; align-items: center; min-height: 100%; gap: 1rem; padding: 1.1rem; border: 1px dashed #d9e2ef; border-radius: .9rem; color: #5d6d84; background: #fbfcff; }
        .core-update-empty__icon { color: #365a8c; background: #eaf1ff; }
        .core-update-empty__title { margin: 0 0 .25rem; color: #344256; font-size: 1rem; font-weight: 800; }
        .core-update-empty__copy { margin: 0; font-size: .85rem; line-height: 1.5; }
        [data-bs-theme="dark"] .core-update-card { border-color: #3b4c64; background: #203046; }
        [data-bs-theme="dark"] .core-update-card__version, [data-bs-theme="dark"] .core-update-state__title, [data-bs-theme="dark"] .core-update-empty__title { color: #edf2f8; }
        [data-bs-theme="dark"] .core-update-card__eyebrow, [data-bs-theme="dark"] .core-update-empty { color: #c4cfde; }
        [data-bs-theme="dark"] .core-update-state--warning { border-color: #735f36; background: #3c3424; }
        [data-bs-theme="dark"] .core-update-state--danger { border-color: #70434a; background: #402a31; }
        [data-bs-theme="dark"] .core-update-state--success { border-color: #386a50; background: #243c31; }
        [data-bs-theme="dark"] .core-update-empty { border-color: #40536d; background: #293a51; }
        @media (max-width: 575.98px) { .core-update-empty { align-items: flex-start; } }
    </style>
@endpush

@section('content')
    @php
        $update = $available['update'] ?? null;
        $driftCount = $drift === null ? null : count($drift['modified']) + count($drift['missing']) + count($drift['added']);
        $busy = $progress['phase'] !== 'idle';
        $last = $progress['last'];
        $lastBadge = ['installed' => 'bg-success-subtle text-success', 'failed' => 'bg-danger-subtle text-danger', 'blocked' => 'bg-warning-subtle text-warning', 'rolled_back' => 'bg-secondary-subtle text-secondary'];
        $steps = ['queued', 'download', 'prepare', 'install', 'migrate', 'health'];
        // Stored in UTC; shown in the shop's time.
        $tz = 'Asia/Ho_Chi_Minh';
    @endphp

    <x-admin.page-header :title="__('admin.core_update.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.core_update.title') }}</li>
        </x-slot:breadcrumbs>
        @if($enabled)
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.core-update.check') }}">
                    @csrf
                    <x-admin.action-button data-tour="core-update.check" type="submit" icon="refresh" :disabled="$busy">{{ __('admin.core_update.check') }}</x-admin.action-button>
                </form>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    {{-- Progress of a queued or running request. Times come from the server: a reload keeps it. --}}
    <div id="update-progress" class="card mb-4 {{ $busy ? '' : 'd-none' }}"
         data-status-url="{{ route('admin.core-update.status') }}"
         data-phase="{{ $progress['phase'] }}"
         data-requested-at="{{ $progress['requested_at'] }}"
         data-started-at="{{ $progress['started_at'] }}"
         data-now="{{ $progress['now'] }}">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1" data-progress-title>
                        {{ $progress['action'] === 'rollback' ? __('admin.core_update.progress.title_rollback') : __('admin.core_update.progress.title', ['version' => $progress['target'] ?? '']) }}
                    </h5>
                    <div class="small text-muted" data-progress-hint>{{ __('admin.core_update.progress.hint') }}</div>
                </div>
                <div class="text-end">
                    <div class="fs-6 fw-bold update-clock" data-progress-clock>0:00</div>
                    <div class="small text-muted">{{ __('admin.core_update.progress.elapsed') }}</div>
                </div>
            </div>

            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <ul class="update-steps" data-progress-steps>
                        @foreach($steps as $step)
                            <li data-step="{{ $step }}"><span class="dot"><i class="ti ti-circle-dashed" aria-hidden="true"></i></span>{{ __('admin.core_update.progress.steps.'.$step) }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info small mb-0" data-progress-message role="status" aria-live="polite">{{ __('admin.core_update.progress.waiting') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div data-tour="core-update.current" class="card h-100 mb-0 core-update-card">
                <div class="card-body core-update-card__body">
                    <div class="core-update-card__eyebrow">{{ __('admin.core_update.current') }}</div>
                    <div class="core-update-card__version">{{ $version }}</div>

                    @if($driftCount === null)
                        <div class="core-update-state core-update-state--warning mb-2">
                            <span class="core-update-state__icon"><i class="ti ti-file-alert" aria-hidden="true"></i></span>
                            <div>
                            <div class="core-update-state__title">{{ __('admin.core_update.not_ready') }}</div>
                            @if($isSuperAdmin)
                                <div class="small mb-2">{{ __('admin.core_update.drift_missing') }}</div>
                                <form method="POST" action="{{ route('admin.core-update.baseline') }}" onsubmit="return confirm(@js(__('admin.core_update.baseline_confirm')))">
                                    @csrf
                                    <x-admin.action-button type="submit" size="sm" icon="checklist">{{ __('admin.core_update.baseline') }}</x-admin.action-button>
                                </form>
                            @else
                                <div class="small">{{ __('admin.core_update.drift_missing_admin') }}</div>
                            @endif
                            </div>
                        </div>
                    @elseif($driftCount > 0)
                        <div class="core-update-state core-update-state--danger mb-2">
                            <span class="core-update-state__icon"><i class="ti ti-shield-exclamation" aria-hidden="true"></i></span>
                            <div><div class="core-update-state__title">{{ __('admin.core_update.not_ready') }}</div>
                                <div class="small">{{ __('admin.core_update.drift_found', ['count' => $driftCount]) }} <span class="text-nowrap">({{ __('admin.core_update.code', ['code' => 'DRIFT']) }})</span></div>
                            </div>
                        </div>
                    @else
                        <div class="core-update-state core-update-state--success mb-2">
                            <span class="core-update-state__icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                            <div class="small">{{ __('admin.core_update.drift_none') }}</div>
                        </div>
                    @endif

                    @if(! empty($last['status']))
                        <hr>
                        <div class="small text-muted mb-1">{{ __('admin.core_update.last_run') }}</div>
                        <div class="small">
                            <span class="badge {{ $lastBadge[$last['status']] ?? 'bg-secondary-subtle text-secondary' }}">{{ __('admin.core_update.status.'.$last['status']) }}</span>
                            {{ $last['from'] ?? '' }} → {{ $last['to'] ?? '' }}
                            @if(! empty($last['updated_at']))· {{ \Illuminate\Support\Carbon::parse($last['updated_at'])->timezone($tz)->format('d/m/Y H:i') }}@endif
                        </div>
                        @foreach($problems as $problem)
                            <div class="small text-danger mt-2">
                                {{ $problem['message'] }}
                                <span class="text-muted text-nowrap">({{ __('admin.core_update.code', ['code' => $problem['code']]) }})</span>
                            </div>
                        @endforeach
                        @if(! empty($last['ref']))
                            <div class="small text-muted mt-2">{{ __('admin.core_update.reference', ['ref' => $last['ref']]) }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div data-tour="core-update.offer" class="card h-100 mb-0 core-update-card">
                <div class="card-body core-update-card__body">
                    @if(! $enabled)
                        <div class="core-update-empty">
                            <span class="core-update-empty__icon"><i class="ti ti-plug-connected-x" aria-hidden="true"></i></span>
                            <div>
                                <h5 class="core-update-empty__title">{{ __('admin.core_update.not_ready') }}</h5>
                                <p class="core-update-empty__copy">{{ __('admin.core_update.disabled') }} <span class="text-nowrap">({{ __('admin.core_update.code', ['code' => 'UPDATES_OFF']) }})</span></p>
                            </div>
                        </div>
                    @elseif($update)
                        <h5 class="fw-bold mb-1">{{ __('admin.core_update.available', ['version' => $update['version']]) }}</h5>
                        <div class="mb-3">
                            <span class="badge bg-primary-subtle text-primary">{{ __('admin.core_update.level.'.$update['level']) }}</span>
                            <span class="badge bg-light text-dark">{{ $update['channel'] }}</span>
                        </div>
                        <div class="border rounded bg-light-subtle p-3 mb-3" data-release-notes>
                            <div class="fw-semibold text-dark mb-2"><i class="ti ti-sparkles text-primary me-1" aria-hidden="true"></i>{{ __('admin.core_update.whats_new', ['version' => $update['version']]) }}</div>
                            @if(filled($update['notes'] ?? null))
                                {{-- Markdown from the signed release (or set on the gateway): raw HTML escaped, unsafe links dropped. --}}
                                <div class="release-notes small" style="max-height: 320px; overflow-y: auto;">
                                    {!! \Illuminate\Support\Str::markdown((string) $update['notes'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                                </div>
                            @else
                                <div class="small text-muted">{{ __('admin.core_update.no_notes') }}</div>
                            @endif
                        </div>
                        @if($update['database']['migration'] ?? false)
                            <p class="small text-muted mb-2">{{ __('admin.core_update.migration') }}</p>
                        @endif

                        <form method="POST" action="{{ route('admin.core-update.update') }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ $update['version'] }}">
                            @if($update['database']['backup_required'] ?? false)
                                <div class="alert alert-warning small py-2">{{ __('admin.core_update.backup_required') }}</div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="backup_done" value="1" id="backup_done" required>
                                    <label class="form-check-label small" for="backup_done">{{ __('admin.core_update.backup_done') }}</label>
                                </div>
                            @endif
                            <x-admin.action-button type="submit" icon="download" :disabled="$busy || ($driftCount ?? 1) > 0">
                                {{ __('admin.core_update.update_to', ['version' => $update['version']]) }}
                            </x-admin.action-button>
                            <div class="small text-muted mt-2">{{ __('admin.core_update.update_hint') }}</div>
                        </form>
                    @elseif(! empty($available['checked_at']))
                        <p class="mb-0"><i class="ti ti-circle-check text-success me-1" aria-hidden="true"></i>{{ __('admin.core_update.up_to_date') }}</p>
                    @endif

                    @if($enabled && ! $update && empty($available['checked_at']))
                        <div class="core-update-empty">
                            <span class="core-update-empty__icon"><i class="ti ti-refresh" aria-hidden="true"></i></span>
                            <div>
                                <h5 class="core-update-empty__title">{{ __('admin.core_update.never_checked') }}</h5>
                                <form method="POST" action="{{ route('admin.core-update.check') }}" class="mt-2">
                                    @csrf
                                    <x-admin.action-button type="submit" size="sm" icon="refresh" :disabled="$busy">{{ __('admin.core_update.check') }}</x-admin.action-button>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- What the running version brought: its notes ship with the core (docs/release-notes). --}}
                    @if(! $update && filled($currentNotes))
                        <div class="border rounded bg-light-subtle p-3 mt-3" data-current-release-notes>
                            <div class="fw-semibold text-dark mb-2"><i class="ti ti-sparkles text-primary me-1" aria-hidden="true"></i>{{ __('admin.core_update.whats_new_current', ['version' => $version]) }}</div>
                            <div class="release-notes small" style="max-height: 320px; overflow-y: auto;">
                                {!! \Illuminate\Support\Str::markdown((string) $currentNotes, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    @endif

                    @if($enabled)
                    <div class="small text-muted mt-3">
                        {{ ! empty($available['checked_at'])
                            ? __('admin.core_update.last_checked', ['time' => \Illuminate\Support\Carbon::parse($available['checked_at'])->timezone($tz)->format('d/m/Y H:i')])
                            : __('admin.core_update.never_checked') }}
                    </div>
                    @endif

                    @if($canRollback && $isSuperAdmin)
                        <hr>
                        <form method="POST" action="{{ route('admin.core-update.rollback') }}" onsubmit="return confirm(@js(__('admin.core_update.rollback_confirm')))">
                            @csrf
                            <x-admin.action-button data-tour="core-update.rollback" type="submit" variant="outline-secondary" size="sm" icon="arrow-back-up" :disabled="$busy">
                                {{ __('admin.core_update.rollback') }}
                            </x-admin.action-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($isSuperAdmin)
        {{-- Per website, like WordPress' DISALLOW_FILE_MODS: on by default. --}}
        <div data-tour="core-update.access" class="card mb-4 core-update-card" data-core-update-access>
            <div class="card-body core-update-card__body">
                <form method="POST" action="{{ route('admin.core-update.access') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="admin_allowed" value="0">
                    <div>
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="admin_allowed" name="admin_allowed" value="1" @checked($adminAllowed) onchange="this.form.submit()">
                            <label class="form-check-label fw-semibold" for="admin_allowed">{{ __('admin.core_update.access.label') }}</label>
                        </div>
                        <div class="small text-muted">{{ __('admin.core_update.access.hint') }}</div>
                    </div>
                    <noscript><x-admin.action-button type="submit" size="sm">{{ __('admin.core_update.access.save') }}</x-admin.action-button></noscript>
                </form>
            </div>
        </div>
    @endif

    @if($history !== [])
        <div data-tour="core-update.history" class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">{{ __('admin.core_update.history') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach($history as $entry)
                                @php($entryProblems = \App\Support\CoreUpdate\UpdateProblems::explain($entry['error'] ?? null))
                                <tr>
                                    <td class="text-nowrap small text-muted">{{ \Illuminate\Support\Carbon::parse($entry['at'])->timezone($tz)->format('d/m/Y H:i') }}</td>
                                    <td class="small">{{ __('admin.core_update.event.'.($entry['event'] ?? 'failed')) }}</td>
                                    <td class="small text-nowrap">{{ $entry['from'] ?? '' }} → {{ $entry['to'] ?? '' }}</td>
                                    <td class="small">
                                        @foreach($entryProblems as $problem)
                                            {{ $problem['message'] }}
                                            <span class="text-muted text-nowrap">({{ $problem['code'] }})</span>
                                        @endforeach
                                        @if(! empty($entry['ref']))<span class="text-muted text-nowrap">· {{ $entry['ref'] }}</span>@endif
                                        @if($entry['rolled_back'] ?? false) <span class="text-muted">· {{ __('admin.core_update.restored') }}</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <script>
        (function () {
            const card = document.getElementById('update-progress');
            if (!card) return;

            const t = @json(__('admin.core_update.progress'));
            const steps = ['queued', 'download', 'prepare', 'install', 'migrate', 'health'];
            const el = (sel) => card.querySelector(sel);
            // The server clock, not the browser's: elapsed and countdown match what cron sees.
            let skew = Date.parse(card.dataset.now) - Date.now();
            let phase = card.dataset.phase;
            let requestedAt = Date.parse(card.dataset.requestedAt || '') || null;
            let startedAt = Date.parse(card.dataset.startedAt || '') || null;
            let stepGroup = null;
            let stale = false;
            let maintenance = false;
            let timer = null;

            const fmt = (ms) => {
                const s = Math.max(0, Math.round(ms / 1000));
                return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
            };

            function paintSteps() {
                const current = phase === 'queued' ? 'queued' : (maintenance ? 'install' : (stepGroup || 'download'));
                const index = steps.indexOf(current);
                card.querySelectorAll('[data-step]').forEach((li, i) => {
                    li.classList.toggle('done', i < index);
                    li.classList.toggle('current', i === index);
                    li.querySelector('i').className = 'ti ' + (i < index ? 'ti-check' : (i === index ? 'ti-loader-2' : 'ti-circle-dashed'));
                });
            }

            function paintMessage() {
                const box = el('[data-progress-message]');
                const now = Date.now() + skew;
                let text, cls = 'alert-info';
                if (stale) {
                    text = phase === 'queued' ? t.stale_queued : t.stale_running;
                    cls = 'alert-warning';
                } else if (maintenance) {
                    text = t.maintenance;
                } else if (phase === 'queued' && requestedAt) {
                    // Cron fires at the top of each minute; give it a few seconds to pick the request up.
                    const expected = Math.ceil((requestedAt + 1000) / 60000) * 60000 + 10000;
                    text = expected > now ? t.starts_in.replace(':time', fmt(expected - now)) : t.starting;
                } else {
                    text = t.running_steps[stepGroup || 'download'] || t.running;
                }
                box.className = 'alert small mb-0 ' + cls;
                box.textContent = text;
            }

            function tick() {
                const since = requestedAt || startedAt;
                if (since) el('[data-progress-clock]').textContent = fmt(Date.now() + skew - since);
                paintMessage();
            }

            async function poll() {
                let response;
                try {
                    response = await fetch(card.dataset.statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                } catch (e) {
                    return; // network blip: next poll
                }
                if (response.status === 503) { // the website is in maintenance while the new code goes in
                    maintenance = true;
                    phase = 'running';
                    paintSteps();
                    return;
                }
                if (!response.ok) return;
                const data = await response.json();
                maintenance = false;
                skew = Date.parse(data.now) - Date.now();
                stale = data.stale;
                if (data.phase === 'idle') {
                    finish(data);
                    return;
                }
                phase = data.phase;
                stepGroup = data.step_group;
                requestedAt = Date.parse(data.requested_at || '') || requestedAt;
                startedAt = Date.parse(data.started_at || '') || startedAt;
                paintSteps();
            }

            function finish(data) {
                clearInterval(timer);
                const ok = ['installed', 'rolled_back'].includes(data.last?.status);
                card.querySelectorAll('[data-step]').forEach((li) => {
                    li.classList.toggle('done', ok);
                    li.classList.remove('current');
                    li.querySelector('i').className = 'ti ' + (ok ? 'ti-check' : 'ti-circle-dashed');
                });
                const box = el('[data-progress-message]');
                box.className = 'alert small mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                box.textContent = ok ? t.done.replace(':version', data.version) : t.failed;
                setTimeout(() => window.location.reload(), ok ? 2500 : 1500);
            }

            if (phase === 'idle') return;
            paintSteps();
            tick();
            timer = setInterval(tick, 1000);
            setInterval(() => { if (phase !== 'idle') poll(); }, 3000);
        })();
    </script>
@endsection
