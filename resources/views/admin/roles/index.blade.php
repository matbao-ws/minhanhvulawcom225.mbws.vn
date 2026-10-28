@extends('admin.layouts.app')

@section('title', __('admin.roles.title'))

@section('content')
        <x-admin.page-header :title="__('admin.roles.title')">
            <x-slot:breadcrumbs>
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.roles.title') }}</li>
            </x-slot:breadcrumbs>
            <x-slot:actions>
                <x-admin.action-button data-tour="roles.create" :href="route('admin.roles.create')" icon="plus">{{ __('admin.roles.create') }}</x-admin.action-button>
            </x-slot:actions>
        </x-admin.page-header>


    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('admin.header.close') }}"></button>
        </div>
    @endif

    <div class="card">
        {{-- Filters, pages and delete update this part only (admin-ajax.js). --}}
        <div class="card-body p-0" data-ajax-list="roles">
            <div data-tour="roles.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.roles.fields.name') }}</th>
                            <th>{{ __('admin.roles.fields.users_count') }}</th>
                            <th data-tour="roles.permissions">{{ __('admin.roles.fields.permissions') }}</th>
                            <th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            @php
                                $isSystemRole = $role->is_system;
                            @endphp
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark fs-3">{{ $role->name }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-dark fw-semibold fs-2">{{ __('admin.roles.fields.accounts', ['count' => $role->users_count]) }}</span>
                                </td>
                                <td class="text-wrap" style="max-width: 400px;">
                                    @if(in_array('*', $role->permissions ?? []))
                                        <span class="badge bg-danger-subtle text-danger fw-semibold fs-2">{{ __('admin.roles.fields.all_permissions') }}</span>
                                    @elseif(empty($role->permissions))
                                        <span class="badge bg-secondary-subtle text-dark fs-2">{{ __('admin.roles.fields.no_permissions') }}</span>
                                    @else
                                        {{-- One badge per module rather than per code: a broad role holds
                                             dozens of granular codes and would otherwise flood the cell. --}}
                                        @php
                                            $modules = \App\Support\PermissionRegistry::modules();
                                            $held = collect($role->permissions)
                                                ->map(fn (string $code): array => explode('.', $code, 2))
                                                ->filter(fn (array $parts): bool => count($parts) === 2)
                                                ->groupBy(fn (array $parts): string => $parts[0]);
                                        @endphp
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($held as $module => $parts)
                                                <span class="badge bg-primary-subtle text-primary fw-semibold fs-2">
                                                    {{ Lang::has('admin.roles.modules.'.$module) ? __('admin.roles.modules.'.$module) : ($modules[$module]['label'] ?? $module) }}:
                                                    {{ collect($parts)->map(fn (array $p): string => $modules[$module]['action_labels'][$p[1]] ?? __('admin.roles.actions.'.$p[1]))->implode(', ') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if(!$isSystemRole)
                                        <div data-tour="roles.row-actions" class="d-flex justify-content-end gap-2">
                                            @can('roles.update')
                                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                                <i class="ti ti-edit fs-4"></i>
                                            </a>
                                            @endcan
                                            @can('roles.delete')
                                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="js-delete-form" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('catalog.actions.delete') }}">
                                                    <i class="ti ti-trash fs-4"></i>
                                                </button>
                                            </form>
                                            @endcan
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

            @if($roles->total() > 0)
                <div class="px-4 py-3 border-top">
                    @include('admin.shared.pagination', ['paginator' => $roles])
                </div>
            @endif
        </div>
    </div>
@endsection
