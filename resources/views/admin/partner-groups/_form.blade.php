{{-- Partner group form (create + edit). Needs $group. The key is chosen once and then only shown. --}}
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
            <div data-tour="partner-group-form.name" class="col-md-8 mb-3">
                <label class="form-label fw-semibold" for="name">{{ __('partners.groups.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $group->name) }}" maxlength="255" required placeholder="{{ __('partners.groups.name_placeholder') }}">
                <div class="form-text">{{ __('partners.groups.name_help') }}</div>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold" for="sort_order">{{ __('partners.groups.fields.sort_order') }}</label>
                <input type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $group->sort_order) }}">
            </div>

            <div data-tour="partner-group-form.key" class="col-12 mb-2">
                <label class="form-label fw-semibold" for="key">{{ __('partners.groups.fields.key') }}</label>
                @if($group->exists)
                    <input type="text" class="form-control" id="key" value="{{ $group->key }}" readonly>
                    <div class="form-text">{{ __('partners.groups.key_locked') }}</div>
                @else
                    <input type="text" class="form-control @error('key') is-invalid @enderror" id="key" name="key" value="{{ old('key') }}" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="{{ __('partners.groups.key_placeholder') }}">
                    <div class="form-text">{{ __('partners.groups.key_help') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.partner-groups.index')])
