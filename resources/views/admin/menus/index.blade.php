@extends('admin.layouts.app')

@section('title', __('admin.menus.title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-semibold mb-1">{{ __('admin.menus.title') }}</h4>
        <p class="text-muted mb-0">{{ __('admin.menus.subtitle') }}</p>
    </div>
    @can('menus.create')
        <a href="{{ route('admin.menus.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('admin.menus.create') }}</a>
    @endcan
</div>

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
