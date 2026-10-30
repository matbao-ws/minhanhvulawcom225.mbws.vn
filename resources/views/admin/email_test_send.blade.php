{{-- TEMPORARY test page (branch test/email-send-page); delete with EmailTestSendController. --}}
@extends('admin.layouts.app')

@section('title', __('admin.email_test_send.title'))

@section('content')
    <x-admin.page-header :title="__('admin.email_test_send.heading')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('admin.email_test_send.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    <div class="alert alert-warning">
        {!! __('admin.email_test_send.intro', ['route' => e(__('admin.email_test_send.routes_'.$route)), 'url' => e(route('admin.email-design.index'))]) !!}
    </div>

    @if(session('results'))
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">{{ __('admin.email_test_send.results') }}</h5>
                <ul class="list-group">
                    @foreach(session('results') as [$email, $ok, $detail])
                        <li class="list-group-item d-flex gap-3 align-items-start">
                            <span class="badge {{ $ok ? 'bg-success' : 'bg-danger' }}">{{ $ok ? __('admin.email_test_send.sent') : __('admin.email_test_send.failed') }}</span>
                            <span><b>{{ __("admin.email_design.emails.names.{$email}") }}</b><br><span class="text-muted small">{{ $detail }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.email-test-send.send') }}" class="card shadow-sm">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="to">{{ __('admin.email_test_send.to') }}</label>
                <input type="text" class="form-control @error('to') is-invalid @enderror" id="to" name="to" value="{{ old('to', 'pductoandev@gmail.com, phanbinh150504@gmail.com') }}">
                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <span class="form-label fw-semibold d-block">{{ __('admin.email_test_send.templates') }}</span>
                @foreach($emails as $email)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="emails[]" value="{{ $email }}" id="test_{{ $email }}" @checked(in_array($email, old('emails', $emails), true))>
                        <label class="form-check-label" for="test_{{ $email }}">{{ __("admin.email_design.emails.names.{$email}") }}</label>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary">{{ __('admin.email_test_send.submit') }}</button>
        </div>
    </form>
@endsection
