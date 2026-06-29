@extends('admin.layouts.app')

@section('title', 'Quản lý giá Addons - Super Admin')

@section('content')
    <!-- Header Card -->
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">Cấu hình giá bán tính năng (Addons Manager)</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.addons.index') }}">Addons</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Quản lý cấu hình</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Addons Configuration Table -->
    <div class="card shadow-sm border border-light-subtle rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-semibold text-dark mb-0">Quản lý giá & mô tả tính năng phụ trợ</h5>
            </div>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted">
                            <th class="ps-4 fw-semibold small">Mã code</th>
                            <th class="fw-semibold small" style="width: 250px;">Tên Addon</th>
                            <th class="fw-semibold small">Mô tả hiển thị</th>
                            <th class="fw-semibold small" style="width: 220px;">Giá bán (VND)</th>
                            <th class="pe-4 text-end fw-semibold small" style="width: 150px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($addons as $addon)
                            <tr>
                                <td class="ps-4 font-monospace text-primary fw-semibold">
                                    {{ $addon->code }}
                                </td>
                                <td>
                                    <h6 class="mb-0 fw-bold text-dark">{{ $addon->name }}</h6>
                                </td>
                                <td>
                                    <form action="{{ route('admin.addons.update-addon', $addon) }}" method="POST" id="form-addon-{{ $addon->id }}">
                                        @csrf
                                        <textarea class="form-control text-dark small" name="description" rows="2" style="resize: vertical; font-size: 13px;" required>{{ $addon->description }}</textarea>
                                </td>
                                <td>
                                        <div class="input-group">
                                            <input type="number" class="form-control text-dark fw-semibold" name="price" value="{{ (int)$addon->price }}" min="0" required>
                                            <span class="input-group-text small">đ</span>
                                        </div>
                                </td>
                                <td class="pe-4 text-end">
                                        <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            Lưu lại
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
