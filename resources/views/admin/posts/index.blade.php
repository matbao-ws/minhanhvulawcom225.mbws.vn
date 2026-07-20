@extends('admin.layouts.app')

@section('title', __('admin.posts.title'))

@section('content')
    <!-- Header Card -->
        <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #141e30 0%, #243b55 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.posts.title') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.posts.title') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
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

    <!-- Posts Filter & List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.posts.search_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.posts.fields.category') }}</label>
                    <select name="category_id" class="form-select">
                        <option value="">{{ __('admin.all') }} {{ mb_strtolower(__('admin.posts.fields.category')) }}</option>
                        @foreach($categories as $category)
                            @php
                                $fallbackLocale = config('app.fallback_locale', config('app.locale', 'vi'));
                                $catName = $category->getTranslation('name', app()->getLocale(), false) ?: $category->getTranslation('name', $fallbackLocale, false);
                            @endphp
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                                {!! str_repeat('&nbsp;&nbsp;', $category->depth ?? 0) !!}{{ $category->depth ? '↳ ' : '' }}{{ $catName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">{{ __('admin.posts.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected(request('status') === '1')>{{ __('admin.posts.fields.active') }}</option>
                        <option value="0" @selected(request('status') === '0')>{{ __('admin.posts.fields.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit" title="{{ __('catalog.actions.search') }}">
                        <i class="ti ti-search fs-5"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.posts.fields.title') }}</th>
                            <th>{{ __('admin.posts.fields.category') }}</th>
                            <th>{{ __('admin.posts.fields.published_at') }}</th>
                            <th>{{ __('admin.posts.fields.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                            @php
                                $fallbackLocale = config('app.fallback_locale', config('app.locale', 'vi'));
                                $title = $post->getTranslation('title', app()->getLocale(), false) ?: $post->getTranslation('title', $fallbackLocale, false);
                                $catName = $post->category ? ($post->category->getTranslation('name', app()->getLocale(), false) ?: $post->category->getTranslation('name', $fallbackLocale, false)) : __('admin.posts.uncategorized');
                            @endphp
                            <tr class="text-nowrap">
                                <td class="ps-4 text-wrap" style="min-width: 250px;">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="{{ $post->image_url ?: asset('admin-assets/js/icons/empty.png') }}" 
                                             onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/empty.png') }}';"
                                             alt="{{ $title }}" 
                                             class="rounded-2 object-fit-cover" 
                                             width="45" height="45" 
                                             style="min-width: 45px;">
                                        <div>
                                            <h6 class="fw-semibold mb-1">
                                                <a href="{{ route('admin.posts.edit', $post) }}" class="text-decoration-none text-dark hover-primary">{{ $title }}</a>
                                            </h6>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold">{{ $catName }}</span>
                                </td>
                                <td>
                                    <span class="text-muted" style="font-size: 14.5px !important;">
                                        {{ $post->published_at ? $post->published_at->format('d-m-Y H:i') : __('admin.posts.fields.inactive') }}
                                    </span>
                                </td>
                                <td>
                                    @if($post->is_active)
                                        <span class="badge bg-success-subtle text-success fw-semibold">
                                            {{ __('admin.posts.fields.active') }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning fw-semibold">
                                            {{ __('admin.posts.fields.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                            <i class="ti ti-edit fs-4"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('{{ __('admin.posts.confirm_delete') }}')" 
                                                    title="{{ __('catalog.actions.delete') }}">
                                                <i class="ti ti-trash fs-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <iconify-icon icon="solar:document-bold-duotone" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                                    <p class="text-muted mb-0 fs-3">{{ __('admin.posts.not_found') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($posts->hasPages())
                <div class="card-footer bg-transparent border-top py-3">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
