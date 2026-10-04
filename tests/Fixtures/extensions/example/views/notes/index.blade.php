@extends('admin.layouts.app')

@section('title', __('example::notes.title'))

@section('content')
    <x-admin.page-header :title="__('example::notes.title')" />

    @can('example.manage')
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('ext.example.notes.store') }}">
                    @csrf
                    <label class="form-label" for="note-body">{{ __('example::notes.new') }}</label>
                    <textarea id="note-body" name="body" class="form-control mb-3" rows="3" required maxlength="1000"></textarea>
                    <button type="submit" class="btn btn-primary">{{ __('example::notes.save') }}</button>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="card-body">
            @forelse($notes as $note)
                <div class="border-bottom py-2" data-note>
                    <div>{{ $note->body }}</div>
                    <div class="fs-2 text-muted">{{ $note->author?->name ?? '—' }} · {{ $note->created_at->format('d/m/Y H:i') }}</div>
                </div>
            @empty
                <div class="text-muted">{{ __('example::notes.empty') }}</div>
            @endforelse
        </div>
    </div>
@endsection
