@extends('admin.layouts.app')

@section('title', 'Thêm trang')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <h4 class="fw-semibold mb-1 text-white">Thêm trang</h4>
                    <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.pages.index') }}">Trang nội dung</a></li>
                            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Thêm trang</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.pages.store') }}" id="page-form">
        @csrf
        @include('admin.pages._form')
    </form>
@endsection
