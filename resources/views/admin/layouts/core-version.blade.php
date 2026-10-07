@php
    $coreVersionUser = auth()->user();
    $coreVersion = app(\App\Support\CoreManifest::class)->version();
    $coreVersionCanUpdate = app(\App\Support\CoreUpdate\CoreUpdateAccess::class)->allows($coreVersionUser);
    // Read from the last check's file, no gateway call per page.
    $coreVersionUpdate = $coreVersionCanUpdate
        ? (app(\App\Support\CoreUpdate\CoreUpdateService::class)->available()['update']['version'] ?? null)
        : null;
@endphp

{{-- Which core this website runs: the first thing to look at after an update. --}}
<li class="nav-item d-flex align-items-center gap-2 me-2 fs-2 text-nowrap" data-core-version="{{ $coreVersion }}">
    @if($coreVersionCanUpdate)
        <a href="{{ route('admin.core-update.index') }}" class="text-muted d-none d-md-inline">{{ __('admin.sidebar.core_version', ['version' => $coreVersion]) }}</a>
        @if($coreVersionUpdate && $coreVersionUpdate !== $coreVersion)
            <a href="{{ route('admin.core-update.index') }}" class="badge bg-primary-subtle text-primary text-decoration-none" data-core-update-available="{{ $coreVersionUpdate }}">{{ __('admin.sidebar.core_update_available', ['version' => $coreVersionUpdate]) }}</a>
        @endif
    @else
        <span class="text-muted d-none d-md-inline">{{ __('admin.sidebar.core_version', ['version' => $coreVersion]) }}</span>
    @endif
</li>
