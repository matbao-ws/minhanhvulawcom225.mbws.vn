@extends('admin.layouts.app')

@section('title', __('ai.draft.title'))

@section('content')
    <x-admin.page-header :title="__('ai.draft.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.posts.index') }}">{{ __('admin.menu.blog') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.draft.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @php($mode = old('mode', 'draft'))

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info small mb-4">
                <i class="ti ti-info-circle me-1"></i>{{ __('ai.draft.intro') }}
            </div>

            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($mode === 'draft') active @endif" data-bs-toggle="tab" data-bs-target="#ai-draft-brief" type="button" role="tab">
                        <i class="ti ti-file-pencil me-1"></i>{{ __('ai.draft.tab_brief') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($mode === 'notes') active @endif" data-bs-toggle="tab" data-bs-target="#ai-draft-notes" type="button" role="tab">
                        <i class="ti ti-notes me-1"></i>{{ __('ai.draft.tab_notes') }}
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade @if($mode === 'draft') show active @endif" id="ai-draft-brief" role="tabpanel">
                    <form method="POST" action="{{ route('admin.ai.post-drafts.store') }}" class="js-ai-draft-form">
                        @csrf
                        <input type="hidden" name="mode" value="draft">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="keyword">{{ __('ai.draft.keyword') }} <span class="text-danger">*</span></label>
                                <input class="form-control @error('keyword') is-invalid @enderror" id="keyword" name="keyword" maxlength="120" value="{{ old('keyword') }}" placeholder="{{ __('ai.draft.keyword_hint') }}" required>
                                @error('keyword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="audience">{{ __('ai.draft.audience') }} <span class="text-danger">*</span></label>
                                <input class="form-control @error('audience') is-invalid @enderror" id="audience" name="audience" maxlength="200" value="{{ old('audience') }}" placeholder="{{ __('ai.draft.audience_hint') }}" required>
                                @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="business_info">{{ __('ai.draft.business') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('business_info') is-invalid @enderror" id="business_info" name="business_info" rows="3" maxlength="3000" placeholder="{{ __('ai.draft.business_hint') }}" required>{{ old('business_info', $businessInfo) }}</textarea>
                            @error('business_info')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="key_points">{{ __('ai.draft.key_points') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('key_points') is-invalid @enderror" id="key_points" name="key_points" rows="4" maxlength="4000" placeholder="{{ __('ai.draft.key_points_hint') }}" required>{{ old('key_points') }}</textarea>
                            @error('key_points')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="products">{{ __('ai.draft.products') }}</label>
                                <textarea class="form-control" id="products" name="products" rows="3" maxlength="3000" placeholder="{{ __('ai.draft.products_hint') }}">{{ old('products') }}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="sources">{{ __('ai.draft.sources') }}</label>
                                <textarea class="form-control" id="sources" name="sources" rows="3" maxlength="3000" placeholder="{{ __('ai.draft.sources_hint') }}">{{ old('sources') }}</textarea>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tone">{{ __('ai.draft.tone') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="tone" name="tone" required>
                                    @foreach($tones as $tone)
                                        <option value="{{ $tone }}" @selected(old('tone', $tones[0]) === $tone)>{{ $tone }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="target_words">{{ __('ai.draft.length') }}</label>
                                <input type="number" class="form-control @error('target_words') is-invalid @enderror" id="target_words" name="target_words" min="{{ $minWords }}" max="{{ $maxWords }}" step="100" value="{{ old('target_words', 800) }}" required>
                                @error('target_words')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary js-ai-draft-submit">
                            <i class="ti ti-sparkles me-1"></i>{{ __('ai.draft.submit') }} <span class="small opacity-75">({{ __('ai.draft.cost_brief') }})</span>
                        </button>
                    </form>
                </div>

                <div class="tab-pane fade @if($mode === 'notes') show active @endif" id="ai-draft-notes" role="tabpanel">
                    <form method="POST" action="{{ route('admin.ai.post-drafts.store') }}" class="js-ai-draft-form">
                        @csrf
                        <input type="hidden" name="mode" value="notes">
                        <div class="mb-3">
                            <label class="form-label" for="notes_title">{{ __('ai.draft.post_title') }}</label>
                            <input class="form-control" id="notes_title" name="title" maxlength="200" value="{{ old('title') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notes">{{ __('ai.draft.notes') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="10" maxlength="15000" placeholder="{{ __('ai.draft.notes_hint') }}" required>{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="notes_tone">{{ __('ai.draft.tone') }}</label>
                                <select class="form-select" id="notes_tone" name="tone">
                                    <option value="">{{ __('ai.draft.tone_from_notes') }}</option>
                                    @foreach($tones as $tone)
                                        <option value="{{ $tone }}" @selected(old('tone') === $tone)>{{ $tone }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="notes_words">{{ __('ai.draft.length') }}</label>
                                <input type="number" class="form-control" id="notes_words" name="target_words" min="{{ $minWords }}" max="{{ $maxWords }}" step="100" value="{{ old('target_words', 800) }}" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary js-ai-draft-submit">
                            <i class="ti ti-sparkles me-1"></i>{{ __('ai.draft.submit') }} <span class="small opacity-75">({{ __('ai.draft.cost_notes') }})</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.js-ai-draft-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                // One request, up to a minute: block a second click that would spend credits twice.
                const button = form.querySelector('.js-ai-draft-submit');
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + @json(__('ai.draft.writing'));
            });
        });
    </script>
@endpush
