@extends('admin.layouts.app')

@section('title', __('admin.posts.title'))

@section('content')
    <!-- Header Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md">
                            <h4 class="fw-semibold mb-1 text-white">{{ __('admin.posts.title') }}</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                                    <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.posts.title') }}</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="col-md-auto">
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <button type="button" class="btn btn-outline-light fw-semibold" data-bs-toggle="modal" data-bs-target="#importWordPressPostsModal">
                                    <i class="ti ti-brand-wordpress me-1" aria-hidden="true"></i>Import WordPress XML
                                </button>
                                <a href="{{ route('admin.posts.create') }}" class="btn btn-light fw-semibold">
                                    <i class="ti ti-plus me-1" aria-hidden="true"></i>{{ __('admin.posts.create') }}
                                </a>
                            </div>
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

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="fw-semibold">{{ session('warning') }}</div>
            @if(session('import_errors'))
                <ul class="mb-0 mt-2">
                    @foreach(array_slice(session('import_errors'), 0, 20) as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Posts Filter & List Card -->
    <div class="card">
        <div class="card-body border-bottom p-4">
            <form method="GET" data-responsive-filters class="row g-2 align-items-end">
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
                                $fallbackLocale = app(\App\Services\LanguageRegistry::class)->fallbackLocale();
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
            @include('admin.shared.bulk-actions', [
                'bulkFormId' => 'bulk-posts-form',
                'bulkActionUrl' => route('admin.posts.bulk'),
                'bulkItemLabel' => 'bài viết',
            ])
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4" style="width: 44px;"><input type="checkbox" class="form-check-input" data-bulk-select-all="bulk-posts-form" aria-label="Chọn tất cả bài viết"></th>
                            <th class="ps-4">{{ __('admin.posts.fields.title') }}</th>
                            <th>{{ __('admin.posts.fields.category') }}</th>
                            <th>{{ __('admin.posts.fields.published_at') }}</th>
                            <th>{{ __('admin.posts.placeholders.seo_score') }}</th>
                            <th>{{ __('admin.posts.fields.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                            @php
                                $fallbackLocale = app(\App\Services\LanguageRegistry::class)->fallbackLocale();
                                $title = $post->getTranslation('title', app()->getLocale(), false) ?: $post->getTranslation('title', $fallbackLocale, false);
                                $catName = $post->category ? ($post->category->getTranslation('name', app()->getLocale(), false) ?: $post->category->getTranslation('name', $fallbackLocale, false)) : __('admin.posts.uncategorized');
                            @endphp
                            <tr class="text-nowrap">
                                <td class="ps-4"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $post->id }}" form="bulk-posts-form" data-bulk-select="bulk-posts-form" aria-label="Chọn {{ $title }}"></td>
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
                                    @php
                                        $seoScore = $post->seo_score;
                                        $seoClass = $seoScore === null
                                            ? 'bg-secondary-subtle text-secondary'
                                            : ($seoScore >= 80 ? 'bg-success-subtle text-success' : ($seoScore >= 50 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'));
                                    @endphp
                                    <span class="badge {{ $seoClass }} fw-semibold">
                                        {{ $seoScore === null ? 'Chưa phân tích' : $seoScore.'/100' }}
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
                                <td colspan="7" class="text-center py-5">
                                    <img src="{{ asset('admin-assets/images/icons/empty-post.png') }}"
                                         alt="{{ __('admin.posts.not_found') }}"
                                         width="220"
                                         class="img-fluid d-block mx-auto mb-3">
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

    <div class="modal fade" id="importWordPressPostsModal" tabindex="-1" aria-labelledby="importWordPressPostsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.posts.import-wordpress') }}" class="modal-content" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importWordPressPostsModalLabel">
                        <i class="ti ti-brand-wordpress me-1 text-primary"></i>Import bài viết WordPress
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        Chọn file <strong>WordPress eXtended RSS (WXR)</strong> được tải từ Công cụ → Xuất dữ liệu → Bài viết.
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="wordpress_import_file">File WordPress XML <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="wordpress_import_file" name="import_file" accept=".xml,application/xml,text/xml" required>
                        <div class="form-text">Dung lượng tối đa 20MB. Ảnh đại diện và ảnh trong nội dung sẽ được tải về media local.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="wordpress_duplicate_action">Khi trùng Slug <span class="text-danger">*</span></label>
                        <select class="form-select" id="wordpress_duplicate_action" name="duplicate_action" required>
                            <option value="skip" selected>Bỏ qua bài đã tồn tại (an toàn)</option>
                            <option value="update">Cập nhật bài đang có bằng dữ liệu WordPress</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-upload me-1"></i>Bắt đầu import
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
