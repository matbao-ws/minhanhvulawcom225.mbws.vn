@php
    $cancelUrl = route('admin.users.index');
@endphp

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('admin.users.sections.general') }}</h4>

                <!-- Name Field -->
                <div class="mb-4">
                    <label class="form-label" for="name">{{ __('admin.users.fields.name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
                           value="{{ old('name', $user->name) }}" placeholder="{{ __('admin.users.placeholders.name_placeholder') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Email Field -->
                <div class="mb-4">
                    <label class="form-label" for="email">{{ __('admin.users.fields.email') }} <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" 
                           value="{{ old('email', $user->email) }}" placeholder="email@example.com" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password Field -->
                <div class="mb-4">
                    <label class="form-label" for="password">{{ __('admin.users.fields.password') }} @if(!$user->exists)<span class="text-danger">*</span>@endif</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" 
                           placeholder="{{ __('admin.users.placeholders.password_placeholder') }}" {{ !$user->exists ? 'required' : '' }}>
                    @if($user->exists)
                        <p class="fs-2 text-muted mb-0 mt-1">{{ __('admin.users.fields.password_help') }}</p>
                    @endif
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        @include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])
    </div>

    <!-- Sidebar Card Info -->
    <div class="col-lg-4">
        <!-- Avatar card -->
        <div class="card">
            <div class="card-body text-center">
                <h4 class="card-title mb-7 text-start">{{ __('admin.users.fields.avatar') }}</h4>
                
                <!-- Hidden file input -->
                <input type="file" name="avatar_file" id="user_avatar_file" class="d-none" accept="image/*">
                
                <!-- Drag and drop preview container -->
                <div id="user_avatar_preview_container" class="position-relative mx-auto rounded-circle border border-2 border-dashed d-flex align-items-center justify-content-center bg-light cursor-pointer mb-3 overflow-hidden" 
                     style="width: 150px; height: 150px; cursor: pointer; border-style: dashed !important;" 
                     onclick="document.getElementById('user_avatar_file').click()">
                    
                    <img id="user_avatar_preview" src="{{ old('avatar_url', $user->avatar_url) ?: asset('admin-assets/images/profile/user-1.jpg') }}" 
                         class="img-fluid w-100 h-100" 
                         style="object-fit: cover;">
                    
                    <div id="user_avatar_placeholder" class="position-absolute w-100 h-100 start-0 top-0 d-flex flex-column align-items-center justify-content-center bg-dark bg-opacity-50 text-white opacity-0 hover-opacity-100 transition-all">
                        <iconify-icon icon="solar:camera-add-bold-duotone" class="fs-8"></iconify-icon>
                        <span class="fs-2 mt-1">{{ __('admin.users.placeholders.change_avatar') }}</span>
                    </div>
                </div>
                
                <p class="fs-2 text-muted mb-0">{{ __('admin.users.placeholders.avatar_help') }}</p>
                @error('avatar_file')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Role & Status Card -->
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('admin.users.sections.role_status') }}</h4>
                
                <!-- Role Select -->
                <div class="mb-3">
                    <label class="form-label" for="role_id">{{ __('admin.users.fields.role') }} <span class="text-danger">*</span></label>
                    <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Status Checkbox -->
                <div class="mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input primary" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label fw-semibold" for="is_active">
                            {{ __('admin.users.fields.status_active') }}
                        </label>
                    </div>
                    <p class="fs-2 text-muted mt-1 mb-0">{{ __('admin.users.fields.status_help') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Unsaved Changes Modal -->
<div class="modal fade" id="unsavedChangesModal" tabindex="-1" aria-labelledby="unsavedChangesModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title text-warning fw-semibold" id="unsavedChangesModalLabel">
                    <i class="ti ti-alert-triangle me-1"></i>{{ __('admin.users.unsaved.title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ __('admin.users.unsaved.body') }}
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('catalog.actions.cancel') }}</button>
                <div class="d-flex gap-2">
                    <button type="button" id="btn-discard-changes" class="btn btn-danger">{{ __('admin.users.unsaved.discard') }}</button>
                    <button type="button" id="btn-save-draft" class="btn btn-warning text-dark">{{ __('admin.users.unsaved.save_draft') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-opacity-100:hover {
        opacity: 1 !important;
    }
    .transition-all {
        transition: all 0.2s ease-in-out;
    }
</style>
