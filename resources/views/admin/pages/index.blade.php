@extends('admin.layouts.app')

@section('title', 'Trang nội dung')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
            <div class="card-body px-4 py-3">
                <h4 class="fw-semibold mb-1 text-white">Trang nội dung</h4>
                <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                        <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Trang nội dung</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.pages.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Thêm trang</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" data-responsive-filters class="row g-2 mb-4">
            <div class="col-md-6"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm theo tiêu đề hoặc slug"></div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" @selected(request('status') === '1')>Đã xuất bản</option>
                    <option value="0" @selected(request('status') === '0')>Bản nháp</option>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-outline-primary w-100">Lọc</button></div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Tiêu đề</th><th>Slug</th><th>Trạng thái</th><th>Cập nhật</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                @forelse($pages as $page)
                    <tr>
                        <td class="fw-semibold">{{ $page->getTranslation('title', app()->getLocale(), false) ?: $page->getTranslation('title', app(\App\Services\LanguageRegistry::class)->fallbackLocale(), false) }}</td>
                        <td><code>{{ $page->slug }}</code></td>
                        <td><span class="badge {{ $page->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $page->is_active ? 'Đã xuất bản' : 'Bản nháp' }}</span></td>
                        <td>{{ $page->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            @if($page->is_active && $page->published_at && !$page->published_at->isFuture())
                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="{{ route('client.pages.show', ['locale' => app()->getLocale(), 'slug' => $page->canonicalSlug(app()->getLocale())]) }}"
                                    target="_blank"
                                    rel="noopener"
                                >Xem trang</a>
                            @endif
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.pages.edit', $page) }}">Chỉnh sửa</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="d-inline js-delete-form" data-confirm-text="Trang sẽ được chuyển vào thùng rác.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">Chưa có trang nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $pages->links() }}
    </div>
</div>
@endsection
