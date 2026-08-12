@extends('admin.layouts.app')

@section('title', 'Chỉnh sửa trang')

@section('content')
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
