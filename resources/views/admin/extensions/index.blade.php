@extends('admin.layouts.app')

@section('title', __('admin.extensions.title'))

@section('content')
    @php
        $badges = [
            'loaded' => 'bg-success-subtle text-success',
            'ready' => 'bg-success-subtle text-success',
            'failed' => 'bg-danger-subtle text-danger',
            'invalid' => 'bg-danger-subtle text-danger',
            'incompatible' => 'bg-warning-subtle text-warning',
            'disabled' => 'bg-secondary-subtle text-secondary',
        ];
    @endphp

    <x-admin.page-header :title="__('admin.extensions.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.extensions.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @unless($enabled)
        <div class="alert alert-warning">{{ __('admin.extensions.globally_off') }}</div>
    @endunless

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-3">{{ __('admin.extensions.intro', ['version' => $coreVersion]) }}</p>

            @if($records === [])
                <div class="text-center text-muted py-4">{{ __('admin.extensions.empty') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('admin.extensions.columns.extension') }}</th>
                                <th>{{ __('admin.extensions.columns.version') }}</th>
                                <th>{{ __('admin.extensions.columns.requires_core') }}</th>
                                <th>{{ __('admin.extensions.columns.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                <tr data-extension="{{ $record['id'] }}">
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $record['manifest']?->name ?? $record['id'] }}</div>
                                        <div class="fs-2 text-muted">extensions/{{ $record['id'] }}</div>
                                        @if($record['manifest']?->description)
                                            <div class="fs-2 text-muted">{{ $record['manifest']->description }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $record['manifest']?->version ?? '—' }}</td>
                                    <td>{{ $record['manifest']?->requiresCore ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $badges[$record['status']] ?? 'bg-secondary-subtle text-secondary' }}">{{ __('admin.extensions.status.'.$record['status']) }}</span>
                                        @if($record['error'])
                                            <div class="fs-2 text-danger mt-1 text-break">{{ \Illuminate\Support\Str::limit($record['error'], 300) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="fs-2 text-muted mt-3">{{ __('admin.extensions.howto') }}</div>
        </div>
    </div>
@endsection
