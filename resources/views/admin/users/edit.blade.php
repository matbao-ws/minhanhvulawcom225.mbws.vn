@extends('admin.layouts.app')

@section('title', __('admin.users.edit'))

@section('content')
    <div class="card card-body py-3">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="d-sm-flex align-items-center justify-space-between">
                    <h4 class="mb-4 mb-sm-0 card-title">{{ __('admin.users.edit') }}: {{ $user->name }}</h4>
                    <nav aria-label="breadcrumb" class="ms-auto">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item d-flex align-items-center">
                                <a class="text-muted text-decoration-none d-flex" href="{{ route('admin.dashboard') }}">
                                    <iconify-icon icon="solar:home-2-line-duotone" class="fs-6"></iconify-icon>
                                </a>
                            </li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">{{ __('admin.users.title') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">
                                <span class="badge fw-medium fs-2 bg-primary-subtle text-primary">{{ __('admin.users.edit') }}</span>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-form-with-sticky-actions" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.users._form')
    </form>
@endsection

@include('admin.users._form-assets')
