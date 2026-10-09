@php
    $coreVersionUser = auth()->user();
    $coreVersion = app(\App\Support\CoreManifest::class)->version();
    $coreVersionCanUpdate = app(\App\Support\CoreUpdate\CoreUpdateAccess::class)->allows($coreVersionUser);
    $coreVersionService = app(\App\Support\CoreUpdate\CoreUpdateService::class);
    // Read from the last check's file, no gateway call per page.
    $coreVersionUpdate = $coreVersionCanUpdate ? ($coreVersionService->available()['update']['version'] ?? null) : null;
    // A queued or running update shows on every admin page, not only on the update page: it runs
    // on the server (cron), so leaving that page never stops it. The update page tracks it itself.
    $coreVersionProgress = $coreVersionCanUpdate && ! request()->routeIs('admin.core-update.*')
        ? $coreVersionService->progress()
        : null;
    $coreVersionBusy = $coreVersionProgress !== null && $coreVersionProgress['phase'] !== 'idle';
    $coreVersionLabels = $coreVersionBusy ? [
        'queued' => __('admin.core_update.header.queued', ['version' => $coreVersionProgress['target'] ?? '']),
        'running' => __('admin.core_update.header.running'),
        'rollback' => __('admin.core_update.header.rollback'),
        'done' => __('admin.core_update.header.done'),
        'failed' => __('admin.core_update.header.failed'),
        'steps' => __('admin.core_update.progress.steps'),
    ] : [];
    $coreVersionText = ! $coreVersionBusy ? null : match (true) {
        $coreVersionProgress['action'] === 'rollback' => $coreVersionLabels['rollback'],
        $coreVersionProgress['phase'] === 'queued' => $coreVersionLabels['queued'],
        default => $coreVersionLabels['running'].' '.($coreVersionLabels['steps'][$coreVersionProgress['step_group'] ?? 'install'] ?? ''),
    };
@endphp

{{-- Which core this website runs: the first thing to look at after an update. --}}
<li class="nav-item d-flex align-items-center gap-2 me-2 fs-2 text-nowrap" data-core-version="{{ $coreVersion }}">
    @if($coreVersionCanUpdate)
        <a href="{{ route('admin.core-update.index') }}" class="text-muted d-none d-md-inline">{{ __('admin.sidebar.core_version', ['version' => $coreVersion]) }}</a>
        @if($coreVersionBusy)
            <a href="{{ route('admin.core-update.index') }}" class="badge bg-warning-subtle text-warning text-decoration-none d-inline-flex align-items-center gap-1"
               data-core-update-progress
               data-status-url="{{ route('admin.core-update.status') }}"
               data-labels='@json($coreVersionLabels)'>
                <span class="spinner-border spinner-border-sm" style="width: .7rem; height: .7rem;" aria-hidden="true"></span>
                <span data-core-update-progress-text>{{ $coreVersionText }}</span>
            </a>
        @elseif($coreVersionUpdate && $coreVersionUpdate !== $coreVersion)
            <a href="{{ route('admin.core-update.index') }}" class="badge bg-primary-subtle text-primary text-decoration-none" data-core-update-available="{{ $coreVersionUpdate }}">{{ __('admin.sidebar.core_update_available', ['version' => $coreVersionUpdate]) }}</a>
        @endif
    @else
        <span class="text-muted d-none d-md-inline">{{ __('admin.sidebar.core_version', ['version' => $coreVersion]) }}</span>
    @endif
</li>

@if($coreVersionBusy)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // The header renders twice (vertical and horizontal layouts): one poller for both.
            if (window.coreUpdateProgressPolling) return;
            const badges = [...document.querySelectorAll('[data-core-update-progress]')];
            if (!badges.length) return;
            window.coreUpdateProgressPolling = true;
            const labels = JSON.parse(badges[0].dataset.labels);
            const statusUrl = badges[0].dataset.statusUrl;
            let timer = null;

            const show = (value) => badges.forEach((badge) => {
                badge.querySelector('[data-core-update-progress-text]').textContent = value;
            });

            const finish = (ok, version) => {
                clearInterval(timer);
                badges.forEach((badge) => {
                    badge.querySelector('.spinner-border')?.remove();
                    badge.classList.remove('bg-warning-subtle', 'text-warning');
                    badge.classList.add(ok ? 'bg-success-subtle' : 'bg-danger-subtle', ok ? 'text-success' : 'text-danger');
                });
                show(ok ? labels.done.replace(':version', version || '') : labels.failed);
            };

            const poll = async () => {
                let data;
                try {
                    const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    // 503 while the site is in maintenance for the file swap: keep waiting.
                    if (!response.ok) return;
                    data = await response.json();
                } catch (e) {
                    return;
                }
                if (data.phase === 'idle') {
                    finish(data.last?.status === 'installed', data.last?.to || data.version);
                    return;
                }
                show(data.action === 'rollback' ? labels.rollback
                    : data.phase === 'queued' ? labels.queued
                    : labels.running + ' ' + (labels.steps[data.step_group] || ''));
            };

            timer = setInterval(poll, 5000);
        });
    </script>
@endif
