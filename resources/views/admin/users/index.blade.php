@extends('admin.layouts.app')

@section('title', __('admin.users.title'))

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.users.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.users.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="d-flex gap-2 align-items-center justify-content-end mb-4">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1 fs-4"></i>{{ __('admin.users.create') }}
        </a>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.users.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.users.fields.role') }}</label>
                    <select name="role_id" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.users.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected((string) request('status') === '1')>{{ __('admin.users.fields.active') }}</option>
                        <option value="0" @selected((string) request('status') === '0')>{{ __('admin.users.fields.suspended') }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit" title="{{ __('catalog.actions.search') }}">
                        <i class="ti ti-search fs-5"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-nowrap">
                        <th>{{ __('admin.users.fields.name') }}</th>
                        <th>{{ __('admin.users.fields.role') }}</th>
                        <th>{{ __('admin.users.fields.status') }}</th>
                        <th>{{ __('admin.users.fields.last_login') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $userItem)
                        <tr class="text-nowrap">
                            <td class="text-wrap">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $userItem->avatar_url ?: asset('admin-assets/images/profile/user-1.jpg') }}" 
                                         alt="{{ $userItem->name }}" 
                                         class="rounded-circle border border-2 border-primary-subtle" 
                                         width="45" height="45" 
                                         style="object-fit: cover;">
                                    <div>
                                        <h6 class="fw-semibold mb-0 fs-3">{{ $userItem->name }}</h6>
                                        <span class="text-muted fs-2">{{ $userItem->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($userItem->role?->name === 'Admin')
                                    <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">Admin</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary fw-semibold fs-2">{{ $userItem->role?->name ?? 'User' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($userItem->is_active)
                                    <span class="badge bg-success-subtle text-success fw-semibold fs-2">{{ __('admin.users.fields.active') }}</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger fw-semibold fs-2">{{ __('admin.users.fields.suspended') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="fs-2 text-muted">
                                    {{ $userItem->last_login_at ? $userItem->last_login_at->format('d-m-Y H:i:s') : __('admin.users.fields.never_logged_in') }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <!-- Edit Link -->
                                    <a href="{{ route('admin.users.edit', $userItem) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                        <i class="ti ti-edit fs-4"></i>
                                    </a>

                                    <!-- Delete Link (prevent self deletion) -->
                                    @if($userItem->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $userItem) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('{{ __('admin.users.confirm_delete') }}')" 
                                                    title="{{ __('catalog.actions.delete') }}">
                                                <i class="ti ti-trash fs-4"></i>
                                            </button>
                                        </form>
                                    @else
                                        <button class="btn btn-sm btn-outline-light text-muted cursor-not-allowed" disabled title="{{ __('admin.users.fields.current_user') }}">
                                            <i class="ti ti-trash fs-4"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <iconify-icon icon="solar:shield-user-broken" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                                <p class="text-muted mb-0 fs-3">{{ __('admin.users.not_found') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="card-footer bg-transparent border-top">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
