@php
    $fallbackLocale = config('app.fallback_locale', config('app.locale', 'vi'));
    $name = old('name', $category->getTranslation('name', app()->getLocale(), false) ?: $category->getTranslation('name', $fallbackLocale, false));
    $description = old('description', $category->getTranslation('description', app()->getLocale(), false) ?: $category->getTranslation('description', $fallbackLocale, false));
    $cancelUrl = route('admin.post-categories.index');
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label" for="name">{{ __('admin.blog_categories.fields.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ $name }}" placeholder="{{ __('admin.blog_categories.name_placeholder') }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="slug">{{ __('admin.blog_categories.fields.slug') }}</label>
                <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="{{ __('admin.blog_categories.slug_placeholder') }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="parent_id">{{ __('catalog.fields.parent_category') }}</label>
                <select class="form-select" id="parent_id" name="parent_id">
                    <option value="">{{ __('catalog.common.none') }}</option>
                    @foreach($parentOptions as $parent)
                        @php
                            $parentName = $parent->getTranslation('name', app()->getLocale(), false) ?: $parent->getTranslation('name', $fallbackLocale, false);
                        @endphp
                        <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parent->id) @disabled($parent->id === $category->id)>
                            {!! str_repeat('&nbsp;&nbsp;', $parent->depth ?? 0) !!}{{ $parent->depth ? '↳ ' : '' }}{{ $parentName }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label" for="description">{{ __('admin.blog_categories.fields.description') }}</label>
                <textarea class="form-control d-none" id="description" name="description">{{ $description }}</textarea>
                <div id="description_editor" class="catalog-quill" data-target="description">{!! $description !!}</div>
            </div>
            <div class="col-12 mb-3">
                <input type="hidden" name="is_active" value="1">
                <div class="form-check">
                    <input class="form-check-input primary" type="checkbox" name="is_active" value="0" id="is_active" @checked(! (bool) old('is_active', $category->is_active))>
                    <label class="form-check-label" for="is_active">{{ __('admin.blog_categories.save_draft_help') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])

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
