@extends('admin.layouts.app')

@section('title', 'Chỉnh sửa trang')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <h4 class="fw-semibold mb-1 text-white">Chỉnh sửa trang</h4>
                    <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.pages.index') }}">Trang nội dung</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Chỉnh sửa trang</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.pages.update', $page) }}" id="page-form">
        @csrf @method('PUT')
        @include('admin.pages._form')
    </form>

    @if($revisions->isNotEmpty())
    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Phiên bản gần đây</h5>
            <div class="list-group">
                @foreach($revisions as $revision)
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                        <span>{{ $revision->created_at?->format('d/m/Y H:i:s') }} · {{ $revision->creator?->name ?: 'Hệ thống' }}</span>
                        <form method="POST" action="{{ route('admin.pages.revisions.restore', [$page, $revision]) }}">@csrf<button class="btn btn-sm btn-outline-warning" onclick="return confirm('Khôi phục phiên bản này?')">Khôi phục</button></form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection
