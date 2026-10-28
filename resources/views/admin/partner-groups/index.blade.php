@extends('admin.layouts.app')

@section('title', __('partners.groups.title'))

@section('content')
    <x-admin.page-header :title="__('partners.groups.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.partners.index') }}">{{ __('partners.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('partners.groups.title') }}</li>
        </x-slot:breadcrumbs>

        @can('partners.create')
            <x-slot:actions>
                <x-admin.action-button data-tour="partner-groups.create" :href="route('admin.partner-groups.create')" icon="plus">
                    {{ __('partners.groups.create') }}
                </x-admin.action-button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <p class="text-muted mb-3">{{ __('partners.groups.intro') }}</p>

    @php
        $canUpdateGroups = (bool) auth()->user()?->can('partners.update');
        $canDeleteGroups = (bool) auth()->user()?->can('partners.delete');
    @endphp
    <div class="card">
        <div class="card-body p-0" data-ajax-list="partner-groups">
            <div data-tour="partner-groups.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap text-muted">
                            <th class="ps-4 fw-semibold small">{{ __('partners.groups.fields.name') }}</th>
                            <th class="fw-semibold small">{{ __('partners.groups.fields.key') }}</th>
                            <th class="fw-semibold small text-center">{{ __('partners.groups.fields.partners_count') }}</th>
                            <th class="fw-semibold small text-center">{{ __('partners.groups.fields.sort_order') }}</th>
                            <th class="pe-4 text-end" style="width: 160px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $group)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('admin.partners.index', ['group' => $group->id]) }}" class="fw-semibold text-dark text-decoration-none hover-primary">{{ $group->name }}</a>
                                </td>
                                <td><code>{{ $group->key }}</code></td>
                                <td class="text-center">{{ $group->partners_count }}</td>
                                <td class="text-center">{{ $group->sort_order }}</td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <x-admin.action-button :href="route('admin.partners.index', ['group' => $group->id])" variant="outline-secondary" size="sm" icon="photo" :title="__('partners.groups.view_logos')" aria-label="{{ __('partners.groups.view_logos') }}" />
                                        @if($canUpdateGroups)
                                            <x-admin.action-button :href="route('admin.partner-groups.edit', $group)" variant="outline-primary" size="sm" icon="edit" :title="__('catalog.actions.edit')" aria-label="{{ __('catalog.actions.edit') }}" />
                                        @endif
                                        @if($canDeleteGroups)
                                            <form method="POST" action="{{ route('admin.partner-groups.destroy', $group) }}" class="d-inline js-delete-form" data-confirm-title="{{ __('partners.groups.confirm_delete', ['name' => $group->name]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-admin.action-button type="submit" variant="outline-danger" size="sm" icon="trash" :title="__('catalog.actions.delete')" aria-label="{{ __('catalog.actions.delete') }}" />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">@include('admin.shared.empty-state', [
                                'emptyImage' => asset('admin-assets/images/icons/emptydata.png'),
                                'emptyMessage' => __('partners.groups.empty'),
                            ])</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
