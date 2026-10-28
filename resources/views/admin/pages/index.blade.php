@extends('admin.layouts.app')

@section('title', __('admin.pages.title'))

@section('content')
<x-admin.page-header :title="__('admin.pages.title')">
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
        <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.pages.title') }}</li>
    </x-slot:breadcrumbs>
    <x-slot:actions>
        <x-admin.action-button data-tour="pages.create" :href="route('admin.pages.create')" icon="plus">{{ __('admin.pages.create') }}</x-admin.action-button>
    </x-slot:actions>
</x-admin.page-header>


<div class="card">
    <div class="card-body">
        <form data-tour="pages.filters" method="GET" data-responsive-filters class="row g-2 mb-4">
            <div class="col-md-6"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.pages.search_placeholder') }}"></div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">{{ __('admin.pages.all_statuses') }}</option>
                    <option value="1" @selected(request('status') === '1')>{{ __('admin.pages.published') }}</option>
                    <option value="0" @selected(request('status') === '0')>{{ __('admin.pages.draft') }}</option>
                </select>
            </div>
            <div class="col-md-3"><x-admin.action-button type="submit" variant="outline-primary" icon="filter" class="w-100">{{ __('admin.pages.filter') }}</x-admin.action-button></div>
        </form>

        {{-- Filters, pages and delete update this part only (admin-ajax.js). --}}
        <div data-ajax-list="pages">
        <div data-tour="pages.table" class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>{{ __('admin.pages.fields.title') }}</th><th>{{ __('admin.pages.fields.slug') }}</th><th>{{ __('admin.pages.fields.status') }}</th><th>{{ __('admin.pages.fields.updated_at') }}</th><th class="text-end">{{ __('catalog.fields.actions') }}</th></tr></thead>
                <tbody>
                @forelse($pages as $page)
                    <tr>
                        <td class="fw-semibold">{{ $page->getTranslation('title', app()->getLocale(), false) ?: $page->getTranslation('title', app(\App\Services\LanguageRegistry::class)->fallbackLocale(), false) }}</td>
                        <td><code>{{ $page->slug }}</code></td>
                        <td><span class="badge {{ $page->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $page->is_active ? __('admin.pages.published') : __('admin.pages.draft') }}</span></td>
                        <td>{{ $page->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            @if($page->is_active && $page->published_at && !$page->published_at->isFuture())
                                <a data-tour="pages.view"
                                    class="btn btn-sm btn-outline-secondary"
                                    href="{{ route('client.pages.show', ['locale' => app()->getLocale(), 'slug' => $page->canonicalSlug(app()->getLocale())]) }}"
                                    target="_blank"
                                    rel="noopener"
                                >{{ __('admin.pages.view_page') }}</a>
                            @endif
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.pages.edit', $page) }}">{{ __('admin.pages.edit_action') }}</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="d-inline js-delete-form" data-confirm-text="{{ __('admin.pages.delete_confirm') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('catalog.actions.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">{{ __('admin.pages.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.shared.pagination', ['paginator' => $pages])
        </div>
    </div>
</div>
@endsection
