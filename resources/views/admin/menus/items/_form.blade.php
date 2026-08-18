@php
    $languages = app(\App\Services\LanguageRegistry::class);
    $fallbackLocale = $languages->fallbackLocale();
    $currentType = old('type', $item->type ?: \App\Models\MenuItem::TYPE_PAGE);
@endphp

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold">{{ __('admin.menus.items.fields.label') }} <span class="text-danger">*</span></label>
                <div class="row g-2">
                    @foreach($languages->codes() as $code)
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text text-uppercase">{{ $code }}</span>
                                <input type="text"
                                       class="form-control @error('label.'.$code) is-invalid @enderror"
                                       name="label[{{ $code }}]"
                                       value="{{ old('label.'.$code, $item->getTranslation('label', $code, false)) }}"
                                       @required($code === $languages->defaultLocale())>
                                @error('label.'.$code)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="type">{{ __('admin.menus.items.fields.type') }} <span class="text-danger">*</span></label>
                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" data-menu-type required>
                    @foreach(\App\Models\MenuItem::types() as $type)
                        <option value="{{ $type }}" @selected($currentType === $type)>{{ __('admin.menus.types.'.$type) }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="parent_id">{{ __('admin.menus.items.fields.parent') }}</label>
                <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                    <option value="">{{ __('admin.menus.items.fields.no_parent') }}</option>
                    @foreach($parentOptions as $option)
                        <option value="{{ $option->id }}" @selected((string) old('parent_id', $item->parent_id) === (string) $option->id)>
                            {!! str_repeat('&nbsp;&nbsp;', $option->depth ?? 0) !!}{{ ($option->depth ?? 0) ? '↳ ' : '' }}{{ $option->getTranslation('label', app()->getLocale(), false) ?: $option->getTranslation('label', $fallbackLocale, false) }}
                        </option>
                    @endforeach
                </select>
                @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- One target block per type; JavaScript only toggles visibility,
                 the server still clears whatever does not match the type. --}}
            <div class="col-12" data-menu-target="page">
                <label class="form-label fw-semibold" for="page_id">{{ __('admin.menus.items.fields.page') }}</label>
                <select class="form-select @error('page_id') is-invalid @enderror" id="page_id" name="page_id">
                    <option value="">{{ __('admin.menus.items.fields.choose') }}</option>
                    @foreach($pages as $page)
                        <option value="{{ $page->id }}" @selected((string) old('page_id', $item->page_id) === (string) $page->id)>
                            {{ $page->getTranslation('title', app()->getLocale(), false) ?: $page->getTranslation('title', $fallbackLocale, false) }}
                        </option>
                    @endforeach
                </select>
                @error('page_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12" data-menu-target="category">
                <label class="form-label fw-semibold" for="category_id">{{ __('admin.menus.items.fields.category') }}</label>
                <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                    <option value="">{{ __('admin.menus.items.fields.choose') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id) === (string) $category->id)>
                            {{ $category->getTranslation('name', app()->getLocale(), false) ?: $category->getTranslation('name', $fallbackLocale, false) }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('admin.menus.items.no_storefront_route_help') }}</div>
            </div>

            <div class="col-12" data-menu-target="post_category">
                <label class="form-label fw-semibold" for="post_category_id">{{ __('admin.menus.items.fields.post_category') }}</label>
                <select class="form-select @error('post_category_id') is-invalid @enderror" id="post_category_id" name="post_category_id">
                    <option value="">{{ __('admin.menus.items.fields.choose') }}</option>
                    @foreach($postCategories as $postCategory)
                        <option value="{{ $postCategory->id }}" @selected((string) old('post_category_id', $item->post_category_id) === (string) $postCategory->id)>
                            {{ $postCategory->getTranslation('name', app()->getLocale(), false) ?: $postCategory->getTranslation('name', $fallbackLocale, false) }}
                        </option>
                    @endforeach
                </select>
                @error('post_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('admin.menus.items.no_storefront_route_help') }}</div>
            </div>

            <div class="col-12" data-menu-target="url">
                <label class="form-label fw-semibold" for="url">{{ __('admin.menus.items.fields.url') }}</label>
                <input type="text" class="form-control @error('url') is-invalid @enderror" id="url" name="url"
                       value="{{ old('url', $item->url) }}" placeholder="/lien-he">
                @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('admin.menus.items.fields.url_help') }}</div>
            </div>

            <div class="col-md-6">
                <input type="hidden" name="target_blank" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="target_blank" value="1" id="target_blank"
                           @checked(old('target_blank', $item->target_blank))>
                    <label class="form-check-label fw-semibold" for="target_blank">{{ __('admin.menus.items.fields.target_blank') }}</label>
                </div>
            </div>

            <div class="col-md-6">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $item->is_active))>
                    <label class="form-check-label fw-semibold" for="is_active">{{ __('admin.menus.items.fields.is_active') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.shared.form-actions', ['cancelUrl' => route('admin.menus.items.index', $menu)])

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeSelect = document.querySelector('[data-menu-type]');
            if (!typeSelect) return;

            const blocks = Array.from(document.querySelectorAll('[data-menu-target]'));

            function sync() {
                blocks.forEach(function (block) {
                    block.classList.toggle('d-none', block.dataset.menuTarget !== typeSelect.value);
                });
            }

            typeSelect.addEventListener('change', sync);
            sync();
        });
    </script>
@endpush
