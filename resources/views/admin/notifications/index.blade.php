@extends('admin.layouts.app')

@section('title', 'Thông báo hệ thống')

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">Thông báo hệ thống</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Thông báo</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification List Card -->
    <div class="card rounded-4 border border-light-subtle shadow-sm overflow-hidden">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                    <h5 class="fw-semibold text-dark mb-0">Tất cả thông báo</h5>
                </div>
                <form method="GET" action="{{ route('admin.notifications.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted mb-1">Tìm kiếm</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <iconify-icon icon="solar:magnifer-line-duotone" class="fs-5 text-muted"></iconify-icon>
                            </span>
                            <input type="search" name="q" class="form-control border-start-0" value="{{ request('q') }}"
                                placeholder="Tìm theo tiêu đề, nội dung, mã đơn hàng, email...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Loại thông báo</label>
                        <select name="type" class="form-select">
                            <option value="">Tất cả loại</option>
                            <option value="orders" @selected(request('type') === 'orders')>Đơn hàng mới</option>
                            <option value="reviews" @selected(request('type') === 'reviews')>Đánh giá sản phẩm</option>
                            <option value="users" @selected(request('type') === 'users')>Thành viên mới</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">
                            Tìm kiếm
                        </button>
                        @if(request('q') || request('type'))
                            <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-danger d-flex align-items-center justify-content-center px-3" title="Xóa bộ lọc">
                                <iconify-icon icon="solar:trash-bin-trash-line-duotone" class="fs-5"></iconify-icon>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
            
            <div class="list-group list-group-flush">
                @forelse($notifications as $notification)
                    <a href="{{ $notification->link }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-4 transition-all">
                        <span class="flex-shrink-0 {{ $notification->bg_color }} rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <iconify-icon icon="{{ $notification->icon }}" class="fs-6"></iconify-icon>
                        </span>
                        <div class="w-100">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="fw-bold mb-0 text-dark">{{ $notification->title }}</h6>
                                <span class="text-muted small">{{ $notification->time ? $notification->time->diffForHumans() : '' }}</span>
                            </div>
                            <p class="text-muted mb-0 small">{{ $notification->message }}</p>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 text-muted">
                        <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-13 text-muted mb-3 d-inline-block"></iconify-icon>
                        <p class="mb-0 fs-4 text-muted">Không có thông báo nào trong hệ thống</p>
                    </div>
                @endforelse
            </div>
            @if($notifications->hasPages())
                <div class="card-footer bg-transparent border-top p-4">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
