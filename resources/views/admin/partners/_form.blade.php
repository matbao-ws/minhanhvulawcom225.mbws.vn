{{-- Partner form (create + edit). Needs $partner and $groups. --}}
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('catalog.actions.close') }}"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="row">
            <div data-tour="partner-form.logo" class="col-12 mb-4">
                <label class="form-label fw-semibold" for="image_file">{{ __('partners.fields.logo') }} @if(! $partner->exists)<span class="text-danger">*</span>@endif</label>
                <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*" data-media-folder="{{ \App\Services\Partners\PartnerService::MEDIA_FOLDER }}">
                <input type="hidden" name="image_url" value="{{ old('image_url') }}">
                <div class="form-text">{{ __('partners.logo_help') }}</div>
                <div class="mt-2 {{ old('image_url', $partner->logo_path) ? '' : 'd-none' }}" data-media-preview>
                    <div class="rounded border p-2 bg-light d-inline-flex align-items-center justify-content-center" style="min-width: 160px; height: 80px;">
                        <img src="{{ old('image_url') ?: ($partner->logo_path ?: '') }}" alt="{{ $partner->name }}" style="max-width: 200px; max-height: 64px; object-fit: contain;" data-media-preview-image>
                    </div>
                </div>
            </div>

            <div data-tour="partner-form.name" class="col-md-6 mb-3">
                <label class="form-label fw-semibold" for="name">{{ __('partners.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $partner->name) }}" maxlength="255" required placeholder="{{ __('partners.name_placeholder') }}">
                <div class="form-text">{{ __('partners.name_help') }}</div>
            </div>

            <div data-tour="partner-form.group" class="col-md-6 mb-3">
                <label class="form-label fw-semibold" for="partner_group_id">{{ __('partners.fields.group') }} <span class="text-danger">*</span></label>
                <select class="form-select @error('partner_group_id') is-invalid @enderror" id="partner_group_id" name="partner_group_id" required>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" @selected((int) old('partner_group_id', $partner->partner_group_id) === $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('partners.group_help') }}</div>
            </div>

            <div data-tour="partner-form.url" class="col-12 mb-3">
                <label class="form-label fw-semibold" for="url">{{ __('partners.fields.url') }}</label>
                <input type="text" class="form-control @error('url') is-invalid @enderror" id="url" name="url" value="{{ old('url', $partner->url) }}" maxlength="2048" placeholder="https://example.com">
                <div class="form-text">{{ __('partners.url_help') }}</div>
            </div>

            <div data-tour="partner-form.status" class="col-12 mb-2">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $partner->is_active))>
                    <label class="form-check-label fw-semibold" for="is_active">{{ __('partners.fields.show_on_site') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.partners.index', $partner->partner_group_id ? ['group' => $partner->partner_group_id] : [])])
