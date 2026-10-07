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
                    <x-admin.action-button type="submit" icon="refresh" :disabled="$busy">{{ __('admin.core_update.check') }}</x-admin.action-button>
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
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="small text-muted mb-1">{{ __('admin.core_update.current') }}</div>
                    <div class="fs-3 fw-bold mb-3">{{ $version }}</div>

                    @if($driftCount === null)
                        <div class="alert alert-warning small py-2 mb-2">
                            <div class="fw-semibold mb-1">{{ __('admin.core_update.not_ready') }}</div>
                            @if($isSuperAdmin)
                                <div class="mb-2">{{ __('admin.core_update.drift_missing') }}</div>
                                <form method="POST" action="{{ route('admin.core-update.baseline') }}" onsubmit="return confirm(@js(__('admin.core_update.baseline_confirm')))">
                                    @csrf
                                    <x-admin.action-button type="submit" size="sm" icon="checklist">{{ __('admin.core_update.baseline') }}</x-admin.action-button>
                                </form>
                            @else
                                <div>{{ __('admin.core_update.drift_missing_admin') }}</div>
                            @endif
                        </div>
                    @elseif($driftCount > 0)
                        <div class="alert alert-danger small py-2 mb-2">
                            <div class="fw-semibold mb-1">{{ __('admin.core_update.not_ready') }}</div>
                            {{ __('admin.core_update.drift_found', ['count' => $driftCount]) }}
                            <span class="text-nowrap">({{ __('admin.core_update.code', ['code' => 'DRIFT']) }})</span>
                        </div>
                    @else
                        <div class="small text-success mb-2"><i class="ti ti-circle-check me-1" aria-hidden="true"></i>{{ __('admin.core_update.drift_none') }}</div>
                    @endif

                    @unless($enabled)
                        <div class="alert alert-secondary small py-2 mb-0">
                            {{ __('admin.core_update.disabled') }}
                            <span class="text-nowrap">({{ __('admin.core_update.code', ['code' => 'UPDATES_OFF']) }})</span>
                        </div>
                    @endunless

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
            <div class="card h-100 mb-0">
                <div class="card-body">
                    @if($update)
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

                    <div class="small text-muted mt-3">
                        {{ ! empty($available['checked_at'])
                            ? __('admin.core_update.last_checked', ['time' => \Illuminate\Support\Carbon::parse($available['checked_at'])->timezone($tz)->format('d/m/Y H:i')])
                            : __('admin.core_update.never_checked') }}
                    </div>

                    @if($canRollback && $isSuperAdmin)
                        <hr>
                        <form method="POST" action="{{ route('admin.core-update.rollback') }}" onsubmit="return confirm(@js(__('admin.core_update.rollback_confirm')))">
                            @csrf
                            <x-admin.action-button type="submit" variant="outline-secondary" size="sm" icon="arrow-back-up" :disabled="$busy">
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
        <div class="card mb-4" data-core-update-access>
            <div class="card-body">
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
        <div class="card">
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
