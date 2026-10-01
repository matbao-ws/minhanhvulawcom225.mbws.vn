@extends('admin.layouts.app')

@section('title', __('admin.menus.title'))

@section('content')
<x-admin.page-header :title="__('admin.menus.title')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
        <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.menus.title') }}</li>
    </x-slot:breadcrumbs>
    <x-slot:actions>
        @can('menus.create')
            <x-admin.action-button :href="route('admin.menus.create')" icon="plus">{{ __('admin.menus.create') }}</x-admin.action-button>
        @endcan
    </x-slot:actions>
</x-admin.page-header>


<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('admin.menus.fields.name') }}</th>
                        <th>{{ __('admin.menus.fields.key') }}</th>
                        <th>{{ __('admin.menus.fields.items_count') }}</th>
                        <th>{{ __('admin.menus.fields.status') }}</th>
                        <th class="text-end">{{ __('admin.menus.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($menus as $menu)
                    <tr>
                        <td class="fw-semibold">{{ $menu->name }}</td>
                        <td><code>{{ $menu->key }}</code></td>
                        <td>{{ $menu->items_count }}</td>
                        <td>
                            <span class="badge {{ $menu->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                {{ $menu->is_active ? __('admin.menus.fields.active') : __('admin.menus.fields.inactive') }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.menus.items.index', $menu) }}">
                                    <i class="ti ti-list-tree me-1"></i>{{ __('admin.menus.manage_items') }}
                                </a>
                                @can('menus.update')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.menus.edit', $menu) }}">{{ __('catalog.actions.edit') }}</a>
                                @endcan
                                @can('menus.delete')
                                    <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" class="d-inline js-delete-form" data-confirm-text="{{ __('admin.menus.delete_confirm') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">{{ __('catalog.actions.delete') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">{{ __('admin.menus.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($menus->hasPages())
            <div class="pt-3">{{ $menus->links() }}</div>
        @endif
    </div>
</div>
@endsection
