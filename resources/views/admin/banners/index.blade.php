@extends('admin.layouts.app')

@section('title', __('admin.banners.title'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.banners.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.banners.title') }}</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('admin.banners.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
                        <i class="ti ti-plus fs-4"></i>{{ __('admin.banners.create') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Notification -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-check fs-5"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Banners List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.banners.fields.position') }}</label>
                    <select name="position" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="home_main" @selected(request('position') === 'home_main')>{{ __('admin.banners.positions.home_main') }}</option>
                        <option value="home_sidebar" @selected(request('position') === 'home_sidebar')>{{ __('admin.banners.positions.home_sidebar') }}</option>
                        <option value="promotional" @selected(request('position') === 'promotional')>{{ __('admin.banners.positions.promotional') }}</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.banners.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected(request('status') === '1')>{{ __('admin.banners.fields.active') }}</option>
                        <option value="0" @selected(request('status') === '0')>{{ __('admin.banners.fields.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="ti ti-search fs-5 me-1"></i>{{ __('catalog.actions.search') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap text-muted">
                            <th class="ps-4 fw-semibold small" style="width: 180px;">{{ __('admin.banners.fields.image') }}</th>
                            <th class="fw-semibold small">{{ __('admin.banners.fields.title') }}</th>
                            <th class="fw-semibold small">{{ __('admin.banners.fields.position') }}</th>
                            <th class="fw-semibold small" style="width: 120px;">{{ __('admin.banners.fields.sort_order') }}</th>
                            <th class="fw-semibold small" style="width: 150px;">{{ __('admin.banners.fields.status') }}</th>
                            <th class="pe-4 text-end" style="width: 120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($banners as $banner)
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <div class="rounded border p-1 bg-light d-inline-block">
                                        <img src="{{ $banner->image_url }}" 
                                             alt="{{ $banner->title }}" 
                                             class="rounded" 
                                             width="140" height="70" 
                                             style="object-fit: cover; max-width: 140px;">
                                    </div>
                                </td>
                                <td>
                                    <h6 class="fw-semibold mb-1 fs-3">
                                        <a href="{{ route('admin.banners.edit', $banner) }}" class="text-decoration-none text-dark hover-primary">
                                            {{ $banner->title ?: __('admin.not_configured') }}
                                        </a>
                                    </h6>
                                    @if($banner->link_url)
                                        <a href="{{ $banner->link_url }}" target="_blank" class="text-muted small text-decoration-none d-block text-truncate" style="max-width: 300px;">
                                            <i class="ti ti-link me-1"></i>{{ $banner->link_url }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">
                                        {{ __('admin.banners.positions.' . $banner->position) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark fs-3">{{ $banner->sort_order }}</span>
                                </td>
                                <td>
                                    @if($banner->is_active)
                                        <span class="badge bg-success-subtle text-success fw-semibold fs-2">
                                            {{ __('admin.banners.fields.active') }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning fw-semibold fs-2">
                                            {{ __('admin.banners.fields.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                            <i class="ti ti-edit fs-4"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="d-inline js-delete-form" data-confirm-title="{{ __('admin.banners.confirm_delete') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('catalog.actions.delete') }}">
                                                <i class="ti ti-trash fs-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="ti ti-photo-off fs-8 text-muted mb-2 d-inline-block"></i>
                                    <p class="text-muted mb-0 fs-3">Không tìm thấy banner nào.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($banners->hasPages())
                <div class="p-4 border-top">
                    {{ $banners->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
