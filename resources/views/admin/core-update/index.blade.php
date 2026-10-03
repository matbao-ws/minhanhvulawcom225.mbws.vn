@extends('admin.layouts.app')

@section('title', __('admin.core_update.title'))

@section('content')
    @php
        $update = $available['update'] ?? null;
        $driftCount = $drift === null ? null : count($drift['modified']) + count($drift['missing']) + count($drift['added']);
        $stale = $requested && \Illuminate\Support\Carbon::parse($requested['requested_at'])->lt(now()->subMinutes(5));
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
                    <x-admin.action-button type="submit" icon="refresh">{{ __('admin.core_update.check') }}</x-admin.action-button>
                </form>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="small text-muted mb-1">{{ __('admin.core_update.current') }}</div>
                    <div class="fs-3 fw-bold mb-3">{{ $version }}</div>

                    @if($driftCount === null)
                        <div class="alert alert-warning small py-2 mb-2">{{ __('admin.core_update.drift_missing') }}</div>
                    @elseif($driftCount > 0)
                        <div class="alert alert-danger small py-2 mb-2">{{ __('admin.core_update.drift_found', ['count' => $driftCount]) }}</div>
                    @else
                        <div class="small text-success mb-2"><i class="ti ti-circle-check me-1" aria-hidden="true"></i>{{ __('admin.core_update.drift_none') }}</div>
                    @endif

                    @unless($enabled)
                        <div class="alert alert-secondary small py-2 mb-0">{{ __('admin.core_update.disabled') }}</div>
                    @endunless

                    @if(! empty($state['status']))
                        <hr>
                        <div class="small text-muted mb-1">{{ __('admin.core_update.last_run') }}</div>
                        <div class="small">
                            <span class="badge {{ $state['status'] === 'installed' ? 'bg-success-subtle text-success' : ($state['status'] === 'failed' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">
                                {{ __('admin.core_update.status.'.$state['status']) }}
                            </span>
                            {{ $state['from'] ?? '' }} → {{ $state['to'] ?? '' }}
                            · {{ \Illuminate\Support\Carbon::parse($state['updated_at'])->format('d/m/Y H:i') }}
                        </div>
                        @if(! empty($state['error']))
                            <div class="small text-danger mt-1 text-break">{{ \Illuminate\Support\Str::limit($state['error'], 300) }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    @if($requested)
                        <div class="alert {{ $stale ? 'alert-warning' : 'alert-info' }} small py-2">
                            {{ __('admin.core_update.pending', ['action' => $requested['action'].(isset($requested['version']) ? ' '.$requested['version'] : ''), 'time' => \Illuminate\Support\Carbon::parse($requested['requested_at'])->format('H:i')]) }}
                            @if($stale)<div class="mt-1">{{ __('admin.core_update.pending_stale') }}</div>@endif
                        </div>
                    @endif

                    @if($update)
                        <h5 class="fw-bold mb-1">{{ __('admin.core_update.available', ['version' => $update['version']]) }}</h5>
                        <div class="mb-3">
                            <span class="badge bg-primary-subtle text-primary">{{ __('admin.core_update.level.'.$update['level']) }}</span>
                            <span class="badge bg-light text-dark">{{ $update['channel'] }}</span>
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
                            <x-admin.action-button type="submit" icon="download" :disabled="(bool) $requested || ($driftCount ?? 1) > 0">
                                {{ __('admin.core_update.update_to', ['version' => $update['version']]) }}
                            </x-admin.action-button>
                        </form>
                    @elseif(! empty($available['checked_at']))
                        <p class="mb-0"><i class="ti ti-circle-check text-success me-1" aria-hidden="true"></i>{{ __('admin.core_update.up_to_date') }}</p>
                    @endif

                    <div class="small text-muted mt-3">
                        {{ ! empty($available['checked_at'])
                            ? __('admin.core_update.last_checked', ['time' => \Illuminate\Support\Carbon::parse($available['checked_at'])->format('d/m/Y H:i')])
                            : __('admin.core_update.never_checked') }}
                    </div>

                    @if($canRollback)
                        <hr>
                        <form method="POST" action="{{ route('admin.core-update.rollback') }}" onsubmit="return confirm(@js(__('admin.core_update.rollback_confirm')))">
                            @csrf
                            <x-admin.action-button type="submit" variant="outline-secondary" size="sm" icon="arrow-back-up" :disabled="(bool) $requested">
                                {{ __('admin.core_update.rollback') }}
                            </x-admin.action-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($history !== [])
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">{{ __('admin.core_update.history') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach($history as $entry)
                                <tr>
                                    <td class="text-nowrap small text-muted">{{ \Illuminate\Support\Carbon::parse($entry['at'])->format('d/m/Y H:i') }}</td>
                                    <td class="small">{{ __('admin.core_update.event.'.($entry['event'] ?? 'failed')) }}</td>
                                    <td class="small text-nowrap">{{ $entry['from'] ?? '' }} → {{ $entry['to'] ?? '' }}</td>
                                    <td class="small text-break">
                                        @if(! empty($entry['error'])){{ \Illuminate\Support\Str::limit($entry['error'], 160) }}@endif
                                        @if($entry['rolled_back'] ?? false) <span class="text-muted">({{ __('admin.core_update.restored') }})</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
