@extends('admin.layouts.app')

@section('title', __('admin.languages.title'))

@section('content')
    <x-admin.page-header :title="__('admin.languages.title')" :subtitle="__('admin.languages.subtitle')" />

    <div class="alert alert-info">
        {{ __('admin.languages.intro') }}
    </div>

    <div data-tour="languages.preferences" class="card">
        <div class="card-body">
            <h5 class="mb-2">{{ __('admin.languages.preferences') }}</h5>
            <p class="text-muted mb-3">{{ __('admin.languages.preferences_hint') }}</p>
            <form method="POST" action="{{ route('admin.languages.preferences') }}" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div data-tour="languages.default" class="col-md-5">
                    <label class="form-label" for="default_locale">{{ __('admin.languages.default_locale') }}</label>
                    <select class="form-select" id="default_locale" name="default_locale" required>
                        @foreach($languages->where('is_active', true) as $language)
                            <option value="{{ $language->code }}" @selected(old('default_locale', $languages->firstWhere('is_default', true)?->code) === $language->code)>
                                {{ $language->native_name }} ({{ strtoupper($language->code) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div data-tour="languages.fallback" class="col-md-5">
                    <label class="form-label" for="fallback_locale">{{ __('admin.languages.fallback_locale') }}</label>
                    <select class="form-select" id="fallback_locale" name="fallback_locale" required>
                        @foreach($languages->where('is_active', true) as $language)
                            <option value="{{ $language->code }}" @selected(old('fallback_locale', $languages->firstWhere('is_content_fallback', true)?->code) === $language->code)>
                                {{ $language->native_name }} ({{ strtoupper($language->code) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button data-tour="languages.save-preferences" class="btn btn-primary w-100">{{ __('admin.languages.save_preferences') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div data-tour="languages.add" class="card">
        <div class="card-body">
            <h5 class="mb-3">{{ __('admin.languages.add') }}</h5>
            <form method="POST" action="{{ route('admin.languages.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2"><label class="form-label">{{ __('admin.languages.code') }}</label><input class="form-control" name="code" placeholder="zh" maxlength="16" required></div>
                <div class="col-md-2"><label class="form-label">{{ __('admin.languages.english_name') }}</label><input class="form-control" name="name" placeholder="Chinese" required></div>
                <div class="col-md-2"><label class="form-label">{{ __('admin.languages.native_name') }}</label><input class="form-control" name="native_name" placeholder="中文" required></div>
                <div class="col-md-2"><label class="form-label">Regional</label><input class="form-control" name="regional" placeholder="zh_CN"></div>
                <div class="col-md-1"><label class="form-label">{{ __('admin.languages.sort_order') }}</label><input type="number" min="0" class="form-control" name="sort_order" value="20"></div>
                <div class="col-md-1"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="is_active" value="1" checked id="new_language_active"><label class="form-check-label" for="new_language_active">{{ __('admin.languages.active') }}</label></div></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('admin.languages.add') }}</button></div>
            </form>
        </div>
    </div>

    <div data-tour="languages.list" class="card">
        <div class="card-body">
            <h5 class="mb-3">{{ __('admin.languages.list') }}</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>{{ __('admin.languages.code_short') }}</th><th>{{ __('admin.languages.name') }}</th><th>Regional</th><th>{{ __('admin.languages.status') }}</th><th>{{ __('admin.languages.default') }}</th><th>{{ __('admin.languages.fallback_locale') }}</th><th class="text-end">{{ __('admin.languages.save') }}</th></tr></thead>
                    <tbody>
                    @foreach($languages as $language)
                        <tr>
                            <td colspan="7" class="p-0 border-0">
                                <form method="POST" action="{{ route('admin.languages.update', $language) }}" class="row g-2 align-items-center py-2 mx-0 border-bottom">
                                    @csrf @method('PUT')
                                    <div class="col-md-1"><input class="form-control" name="code" value="{{ $language->code }}" required></div>
                                    <div class="col-md-2"><input class="form-control mb-1" name="name" value="{{ $language->name }}" required><input class="form-control" name="native_name" value="{{ $language->native_name }}" required></div>
                                    <div class="col-md-2"><input class="form-control mb-1" name="regional" value="{{ $language->regional }}"><input class="form-control" name="flag_path" value="{{ $language->flag_path }}" placeholder="{{ __('admin.languages.flag_path') }}"></div>
                                    <div class="col-md-1"><input type="number" min="0" class="form-control" name="sort_order" value="{{ $language->sort_order }}"></div>
                                    <div class="col-md-1 text-center"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($language->is_active)></div>
                                    <div class="col-md-1 text-center">@if($language->is_default)<span class="badge bg-primary">{{ __('admin.languages.default') }}</span>@else<span class="text-muted">—</span>@endif</div>
                                    <div class="col-md-2 text-center">@if($language->is_content_fallback)<span class="badge bg-info-subtle text-info">Fallback</span>@else<span class="text-muted">—</span>@endif</div>
                                    <div class="col-md-2 text-end"><button data-tour="languages.row-save" class="btn btn-outline-primary">{{ __('admin.languages.save_code', ['code' => strtoupper($language->code)]) }}</button></div>
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
