@extends('admin.layouts.app')

@section('title', __('admin.roles.title'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.roles.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.roles.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center justify-content-end mb-4">
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1 fs-4"></i> {{ __('admin.roles.create') }}
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.roles.fields.name') }}</th>
                            <th>{{ __('admin.roles.fields.users_count') }}</th>
                            <th>{{ __('admin.roles.fields.permissions') }}</th>
                            <th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            @php
                                $isSuper = $role->name === 'Superadmin';
                            @endphp
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark fs-3">{{ $role->name }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-dark fw-semibold fs-2">{{ __('admin.roles.fields.accounts', ['count' => $role->users_count]) }}</span>
                                </td>
                                <td class="text-wrap" style="max-width: 400px;">
                                    @if($isSuper || in_array('*', $role->permissions ?? []))
                                        <span class="badge bg-danger-subtle text-danger fw-semibold fs-2">{{ __('admin.roles.fields.all_permissions') }}</span>
                                    @elseif(empty($role->permissions))
                                        <span class="badge bg-secondary-subtle text-dark fs-2">{{ __('admin.roles.fields.no_permissions') }}</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($role->permissions as $p)
                                                <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">{{ __('admin.roles.permissions.' . $p) }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if(!$isSuper)
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                                <i class="ti ti-edit fs-4"></i>
                                            </a>
                                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="js-delete-form" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('catalog.actions.delete') }}">
                                                    <i class="ti ti-trash fs-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-dark small fw-semibold">{{ __('admin.roles.fields.default_system') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-dark">
                                    {{ __('admin.roles.not_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($roles->hasPages())
                <div class="px-4 py-3 border-top">
                    {{ $roles->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
