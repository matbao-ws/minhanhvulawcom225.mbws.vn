<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-4">
                <label class="form-label fw-semibold text-dark" for="name">{{ __('admin.roles.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-dark" id="name" name="name" value="{{ old('name', $role->name) }}" placeholder="{{ __('admin.roles.fields.name_placeholder') }}" required>
            </div>
            
            <div class="col-md-12">
                <label class="form-label fw-semibold text-dark mb-3">{{ __('admin.roles.fields.select_permissions') }}</label>
                <div class="row g-3">
                    @foreach($permissions as $key => $label)
                        <div class="col-md-6 col-lg-4">
                            <div class="p-3 border rounded bg-light d-flex align-items-center">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}" id="perm_{{ $key }}"
                                        @checked(in_array($key, old('permissions', $role->permissions ?? [])) || in_array('*', $role->permissions ?? []))>
                                    <label class="form-check-label fw-semibold text-dark ms-2" for="perm_{{ $key }}">{{ __('admin.roles.permissions.' . $key) }}</label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.roles.index')])
