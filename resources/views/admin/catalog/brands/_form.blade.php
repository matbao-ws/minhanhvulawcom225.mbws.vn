@php
    $fallbackLocale = config('app.fallback_locale', config('app.locale'));
    $name = old('name', $brand->getTranslation('name', app()->getLocale(), false) ?: $brand->getTranslation('name', $fallbackLocale, false));
    $description = old('description', $brand->getTranslation('description', app()->getLocale(), false) ?: $brand->getTranslation('description', $fallbackLocale, false));
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label" for="name">{{ __('catalog.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ $name }}" placeholder="{{ __('catalog.placeholders.brand_name') }}" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="slug">{{ __('catalog.fields.slug') }}</label>
                <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $brand->slug) }}" placeholder="{{ __('catalog.placeholders.brand_slug') }}">
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label" for="image_file">{{ __('catalog.fields.image') }}</label>
                <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*">
                @if($brand->image_url)
                    <div class="mt-2">
                        <img src="{{ $brand->image_url }}" alt="{{ $name }}" class="rounded border object-fit-cover" width="72" height="72" onerror="this.onerror=null;this.src='{{ asset('admin-assets/js/icons/404.png') }}';">
                    </div>
                @endif
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label" for="description">{{ __('catalog.fields.description') }}</label>
                <textarea class="form-control d-none" id="description" name="description">{{ $description }}</textarea>
                <div id="description_editor" class="catalog-quill" data-target="description">{!! $description !!}</div>
            </div>
            <div class="col-12 mb-3">
                <input type="hidden" name="is_active" value="1">
                <div class="form-check">
                    <input class="form-check-input primary" type="checkbox" name="is_active" value="0" id="is_active" @checked(! (bool) old('is_active', $brand->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('catalog.fields.save_draft') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.brands.index')])

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/quill/dist/quill.snow.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('admin-assets/libs/quill/dist/quill.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.catalog-quill').forEach(function (editorElement) {
                if (editorElement.id === 'quick_description_editor') return;
                const target = document.getElementById(editorElement.dataset.target);
                if (!target) return;

                const quill = new Quill(editorElement, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['link', 'clean']
                        ]
                    }
                });
                editorElement.__quill = quill;

                const form = editorElement.closest('form');
                if (form) {
                    form.addEventListener('submit', function () {
                        target.value = quill.root.innerHTML;
                    });
                }
            });
        });
    </script>
@endpush
