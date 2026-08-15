<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold" for="name">{{ __('admin.menus.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ old('name', $menu->name) }}" placeholder="{{ __('admin.menus.fields.name_placeholder') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="key">{{ __('admin.menus.fields.key') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('key') is-invalid @enderror" id="key" name="key"
                       value="{{ old('key', $menu->key) }}" placeholder="primary" required>
                @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('admin.menus.fields.key_help') }}</div>
            </div>

            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $menu->is_active))>
                    <label class="form-check-label fw-semibold" for="is_active">{{ __('admin.menus.fields.active') }}</label>
                </div>
                <div class="form-text">{{ __('admin.menus.fields.active_help') }}</div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.menus.index')])
