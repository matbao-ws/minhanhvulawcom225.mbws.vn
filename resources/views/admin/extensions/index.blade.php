@extends('admin.layouts.app')

@section('title', __('admin.extensions.title'))

@section('content')
    @php
        $styles = [
            'loaded' => ['bg-success-subtle text-success', 'circle-check'],
            'ready' => ['bg-success-subtle text-success', 'circle-check'],
            'failed' => ['bg-danger-subtle text-danger', 'alert-triangle'],
            'invalid' => ['bg-danger-subtle text-danger', 'file-alert'],
            'incompatible' => ['bg-warning-subtle text-warning', 'versions'],
            'disabled' => ['bg-secondary-subtle text-secondary', 'player-pause'],
        ];
        $count = fn (array $statuses) => count(array_filter($records, fn ($r) => in_array($r['status'], $statuses, true)));
        $stats = [
            ['label' => __('admin.extensions.stats.total'), 'value' => count($records), 'icon' => 'puzzle', 'class' => 'text-primary bg-primary-subtle'],
            ['label' => __('admin.extensions.stats.running'), 'value' => $count(['loaded', 'ready']), 'icon' => 'circle-check', 'class' => 'text-success bg-success-subtle'],
            ['label' => __('admin.extensions.stats.attention'), 'value' => $count(['failed', 'invalid', 'incompatible']), 'icon' => 'alert-triangle', 'class' => 'text-danger bg-danger-subtle'],
            ['label' => __('admin.extensions.stats.disabled'), 'value' => $count(['disabled']), 'icon' => 'player-pause', 'class' => 'text-secondary bg-secondary-subtle'],
        ];
        $commands = [
            ['php artisan extension:list', __('admin.extensions.commands.list')],
            ['php artisan extension:disable <id> --reason="..."', __('admin.extensions.commands.disable')],
            ['php artisan extension:enable <id>', __('admin.extensions.commands.enable')],
            ['php artisan extension:check', __('admin.extensions.commands.check')],
            ['EXTENSIONS_ENABLED=false', __('admin.extensions.commands.safe_mode')],
        ];
    @endphp

    <x-admin.page-header :title="__('admin.extensions.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.extensions.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @unless($enabled)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="ti ti-power fs-5" aria-hidden="true"></i>
            <div>{{ __('admin.extensions.globally_off') }}</div>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        @foreach($stats as $stat)
            <div class="col-6 col-lg-3">
                <div class="card h-100 mb-0">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle {{ $stat['class'] }}" style="width: 44px; height: 44px;">
                            <i class="ti ti-{{ $stat['icon'] }} fs-6" aria-hidden="true"></i>
                        </span>
                        <div>
                            <div class="fs-6 fw-bold text-dark lh-1">{{ $stat['value'] }}</div>
                            <div class="fs-2 text-muted mt-1">{{ $stat['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-0">
                <div class="card-header bg-transparent border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title fw-semibold mb-0">{{ __('admin.extensions.list_title') }}</h5>
                    <span class="badge bg-light text-dark fw-semibold">{{ __('admin.extensions.core_badge', ['version' => $coreVersion]) }}</span>
                </div>

                @if($records === [])
                    <div class="card-body text-center py-5">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width: 64px; height: 64px;">
                            <i class="ti ti-puzzle fs-8" aria-hidden="true"></i>
                        </span>
                        <h5 class="fw-semibold text-dark mb-2">{{ __('admin.extensions.empty') }}</h5>
                        <p class="text-muted mx-auto mb-4" style="max-width: 480px;">{{ __('admin.extensions.empty_hint') }}</p>
                        <div class="d-inline-block text-start bg-light rounded px-3 py-2">
                            <code class="text-dark">php artisan extension:make &lt;id&gt;</code>
                        </div>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($records as $record)
                            @php([$badge, $icon] = $styles[$record['status']] ?? ['bg-secondary-subtle text-secondary', 'circle'])
                            <div class="list-group-item px-4 py-3" data-extension="{{ $record['id'] }}">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div class="d-flex gap-3">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-primary flex-shrink-0" style="width: 40px; height: 40px;">
                                            <i class="ti ti-puzzle fs-5" aria-hidden="true"></i>
                                        </span>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $record['manifest']?->name ?? $record['id'] }}</div>
                                            <div class="fs-2 text-muted">
                                                <code>{{ $record['id'] }}</code>
                                                @if($record['manifest'])
                                                    · v{{ $record['manifest']->version }}
                                                    · {{ __('admin.extensions.requires', ['constraint' => $record['manifest']->requiresCore]) }}
                                                @endif
                                            </div>
                                            @if($record['manifest']?->description)
                                                <div class="fs-2 text-muted mt-1">{{ $record['manifest']->description }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="badge {{ $badge }} d-inline-flex align-items-center gap-1">
                                        <i class="ti ti-{{ $icon }}" aria-hidden="true"></i>{{ __('admin.extensions.status.'.$record['status']) }}
                                    </span>
                                </div>
                                @if($record['error'])
                                    <div class="alert {{ $record['status'] === 'disabled' ? 'alert-secondary' : 'alert-danger' }} fs-2 py-2 px-3 mt-3 mb-0 text-break">
                                        {{ $record['status'] === 'disabled' ? __('admin.extensions.reason') : __('admin.extensions.error') }}:
                                        {{ \Illuminate\Support\Str::limit($record['error'], 300) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold text-dark mb-2"><i class="ti ti-info-circle me-1 text-primary" aria-hidden="true"></i>{{ __('admin.extensions.about_title') }}</h6>
                    <p class="fs-2 text-muted mb-0">{{ __('admin.extensions.intro') }}</p>
                </div>
            </div>
            <div class="card mb-0">
                <div class="card-body">
                    <h6 class="fw-semibold text-dark mb-1"><i class="ti ti-terminal-2 me-1 text-primary" aria-hidden="true"></i>{{ __('admin.extensions.commands_title') }}</h6>
                    <p class="fs-2 text-muted mb-3">{{ __('admin.extensions.commands_hint') }}</p>
                    @foreach($commands as [$command, $description])
                        <div class="mb-2">
                            <code class="d-block bg-light text-dark rounded px-2 py-1 fs-2 text-break">{{ $command }}</code>
                            <div class="fs-2 text-muted mt-1">{{ $description }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
