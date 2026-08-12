@extends('admin.layouts.app')

@section('title', 'Thêm trang')

@section('content')
    <form method="POST" action="{{ route('admin.pages.store') }}" id="page-form">
        @csrf
        @include('admin.pages._form')
    </form>
@endsection
