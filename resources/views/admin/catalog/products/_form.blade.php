@php
    $fallbackLocale = config('app.fallback_locale', config('app.locale'));
    $name = old('name', $product->getTranslation('name', app()->getLocale(), false) ?: $product->getTranslation('name', $fallbackLocale, false));
    $shortDescription = old('short_description', $product->getTranslation('short_description', app()->getLocale(), false) ?: $product->getTranslation('short_description', $fallbackLocale, false));
    $description = old('description', $product->getTranslation('description', app()->getLocale(), false) ?: $product->getTranslation('description', $fallbackLocale, false));
    $metaTitle = old('meta_title', $product->getTranslation('meta_title', app()->getLocale(), false) ?: $product->getTranslation('meta_title', $fallbackLocale, false));
    $metaDescription = old('meta_description', $product->getTranslation('meta_description', app()->getLocale(), false) ?: $product->getTranslation('meta_description', $fallbackLocale, false));
    $cancelUrl = $product->exists ? route('admin.products.show', $product) : route('admin.products.index');
@endphp

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-7">
                    <h4 class="card-title">{{ __('catalog.products.sections.general') }}</h4>
                    <button class="navbar-toggler border-0 shadow-none d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#productSidePanel" aria-controls="productSidePanel">
                        <i class="ti ti-menu fs-5 d-flex"></i>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="name">{{ __('catalog.fields.name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ $name }}" placeholder="{{ __('catalog.placeholders.product_name') }}" required>
                    <p class="fs-2">{{ __('catalog.products.help.name') }}</p>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="slug">{{ __('catalog.fields.slug') }}</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="{{ __('catalog.placeholders.product_slug') }}">
                    <p class="fs-2 mb-0">{{ __('catalog.products.help.slug') }}</p>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="short_description">{{ __('catalog.fields.short_description') }}</label>
                    <textarea class="form-control" id="short_description" name="short_description" rows="3" placeholder="{{ __('catalog.placeholders.product_short_description') }}">{{ $shortDescription }}</textarea>
                </div>

                <div class="mb-4 position-relative" id="editor_wrapper">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label mb-0" for="description">{{ __('catalog.fields.description') }}</label>
                        <button type="button" class="btn btn-xs btn-outline-secondary d-flex align-items-center gap-1 py-1 px-2" id="toggle_fullscreen" style="font-size: 12.2px !important; border-radius: 6px;">
                            <i class="ti ti-maximize fs-4" id="fullscreen_icon"></i>
                            <span id="fullscreen_text">Phóng to</span>
                        </button>
                    </div>
                    <textarea class="form-control d-none" id="description" name="description">{{ $description }}</textarea>
                    <div id="description_editor" class="catalog-quill" data-target="description" style="height: 350px;">{!! $description !!}</div>
                    <p class="fs-2 mb-0">{{ __('catalog.products.help.description') }}</p>
                </div>
                <div class="card border bg-light-subtle mt-4">
                    <div class="card-body">
                        <h5 class="mb-3">{{ __('catalog.fields.seo') }}</h5>
                        <div class="mb-3">
                            <label class="form-label" for="meta_title">{{ __('catalog.fields.meta_title') }}</label>
                            <input type="text" maxlength="255" class="form-control" id="meta_title" name="meta_title" value="{{ $metaTitle }}" placeholder="{{ __('catalog.placeholders.meta_title') }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="meta_description">{{ __('catalog.fields.meta_description') }}</label>
                            <textarea maxlength="500" class="form-control" id="meta_description" name="meta_description" rows="3" placeholder="{{ __('catalog.placeholders.meta_description') }}">{{ $metaDescription }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('catalog.products.sections.media') }}</h4>
                <div class="dropzone dz-clickable mb-2 catalog-dropzone">
                    <div class="dz-default dz-message">
                        <button class="dz-button" type="button">{{ __('catalog.products.dropzone.media') }}</button>
                    </div>
                </div>
                <label class="form-label" for="image_url">{{ __('catalog.fields.image_url') }}</label>
                <input type="text" class="form-control" id="image_url" name="image_url" value="{{ old('image_url', $product->image_url) }}" placeholder="{{ __('catalog.placeholders.product_image_url') }}">
            </div>
        </div>

        @php
            $variantGroups = old('variant_groups');
            if ($variantGroups === null && $product->exists) {
                $variantGroups = $product->optionGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_type' => $group->display_type,
                    'values' => $group->values->map(fn ($value) => [
                        'id' => $value->id,
                        'label' => $value->label,
                        'color_hex' => $value->color_hex,
                        'image_url' => $value->image_url,
                        'is_active' => $value->is_active,
                    ])->all(),
                ])->all();
            }
            $variantGroups ??= [];
            $hasVariants = (bool) old('has_variants', ! empty($variantGroups));
        @endphp
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h4 class="card-title mb-1">Biến thể & SKU</h4>
                            <p class="fs-2 mb-0">Khai báo hoặc chỉnh sửa nhóm như Màu sắc, Kích thước. Khi lưu, core tạo ngay các tổ hợp SKU còn thiếu.</p>
                        </div>
                        <div class="form-check form-switch mt-1">
                            <input type="hidden" name="has_variants" value="0">
                            <input class="form-check-input" type="checkbox" name="has_variants" value="1" id="has_variants" @checked($hasVariants)>
                            <label class="form-check-label" for="has_variants">Có biến thể</label>
                        </div>
                    </div>
                    <div id="create-variant-setup" @class(['d-none' => ! $hasVariants])>
                        <div id="create-variant-groups" class="d-flex flex-column gap-3">
                            @foreach($variantGroups as $groupIndex => $group)
                                <div class="border rounded p-3 create-variant-group" data-group-index="{{ $groupIndex }}">
                                    @if(data_get($group, 'id'))<input type="hidden" name="variant_groups[{{ $groupIndex }}][id]" value="{{ data_get($group, 'id') }}">@endif
                                    <div class="row align-items-end mb-3">
                                        <div class="col-md-6"><label class="form-label">Tên nhóm</label><input class="form-control" name="variant_groups[{{ $groupIndex }}][name]" value="{{ data_get($group, 'name') }}" required></div>
                                        <div class="col-md-4"><label class="form-label">Kiểu hiển thị</label><select class="form-select" name="variant_groups[{{ $groupIndex }}][display_type]"><option value="select" @selected(data_get($group, 'display_type', 'select') === 'select')>Danh sách</option><option value="color" @selected(data_get($group, 'display_type') === 'color')>Màu sắc</option><option value="image" @selected(data_get($group, 'display_type') === 'image')>Ảnh</option></select></div>
                                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-create-group">Xóa nhóm</button></div>
                                    </div>
                                    <div class="create-variant-values d-flex flex-column gap-2">
                                        @foreach(data_get($group, 'values', []) as $valueIndex => $value)
                                            <div class="row g-2 create-variant-value align-items-end">@if(data_get($value, 'id'))<input type="hidden" name="variant_groups[{{ $groupIndex }}][values][{{ $valueIndex }}][id]" value="{{ data_get($value, 'id') }}">@endif<div class="col-md-4"><label class="form-label">Giá trị</label><input class="form-control" name="variant_groups[{{ $groupIndex }}][values][{{ $valueIndex }}][label]" value="{{ data_get($value, 'label') }}" required></div><div class="col-md-2"><label class="form-label">Mã màu</label><input class="form-control" name="variant_groups[{{ $groupIndex }}][values][{{ $valueIndex }}][color_hex]" value="{{ data_get($value, 'color_hex') }}" placeholder="#FFFFFF"></div><div class="col-md-4"><label class="form-label">Ảnh URL</label><input type="url" class="form-control" name="variant_groups[{{ $groupIndex }}][values][{{ $valueIndex }}][image_url]" value="{{ data_get($value, 'image_url') }}"></div><div class="col-md-1"><div class="form-check mb-2"><input type="hidden" name="variant_groups[{{ $groupIndex }}][values][{{ $valueIndex }}][is_active]" value="0"><input class="form-check-input" type="checkbox" name="variant_groups[{{ $groupIndex }}][values][{{ $valueIndex }}][is_active]" value="1" @checked(data_get($value, 'is_active', true))><label class="form-check-label">Bật</label></div></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-create-value">×</button></div></div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-create-value">+ Thêm giá trị</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-create-group" class="btn btn-outline-primary mt-3">+ Thêm nhóm thuộc tính</button>
                    </div>
                </div>
            </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-7">{{ __('catalog.products.sections.pricing') }}</h4>
                <div class="mb-7">
                    <label class="form-label" for="price">{{ __('catalog.fields.price') }} <span class="text-danger">*</span></label>
                    <input type="number" min="0" step="0.01" class="form-control" id="price" name="price" value="{{ old('price', $product->price ?? 0) }}" placeholder="{{ __('catalog.placeholders.product_price') }}" required>
                    <p class="fs-2">{{ __('catalog.products.help.price') }}</p>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label" for="compare_at_price">{{ __('catalog.fields.compare_at_price') }}</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="compare_at_price" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" placeholder="{{ __('catalog.placeholders.product_compare_at_price') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label" for="cost_price">{{ __('catalog.fields.cost_price') }}</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="cost_price" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" placeholder="{{ __('catalog.placeholders.product_cost_price') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.shared.form-actions', ['cancelUrl' => $cancelUrl])
    </div>

    <div class="col-lg-4">
        <div class="offcanvas-md offcanvas-end overflow-auto" tabindex="-1" id="productSidePanel" aria-labelledby="productSidePanelLabel">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-7" id="productSidePanelLabel">{{ __('catalog.products.sections.thumbnail') }}</h4>
                    
                    <!-- Hidden file input for image select -->
                    <input type="file" name="image_file" id="product_image_file" class="d-none" accept="image/*">
                    
                    <!-- Styled image preview area -->
                    <div id="product_image_preview_container" class="position-relative text-center border border-2 border-dashed rounded p-3 mb-3 cursor-pointer d-flex flex-column align-items-center justify-content-center bg-light" 
                         style="min-height: 180px; cursor: pointer; border-style: dashed !important;" 
                         onclick="document.getElementById('product_image_file').click()">
                        
                        <img id="product_image_preview" src="{{ old('image_url', $product->image_url) }}" 
                             class="img-fluid rounded {{ old('image_url', $product->image_url) ? '' : 'd-none' }}" 
                             style="max-height: 150px; object-fit: contain;">
                        
                        <div id="product_image_placeholder" class="text-center py-3 {{ old('image_url', $product->image_url) ? 'd-none' : '' }}">
                            <iconify-icon icon="solar:camera-add-bold-duotone" class="fs-10 text-muted mb-2"></iconify-icon>
                            <div class="text-muted small">Kéo thả ảnh hoặc nhấp để chọn ảnh</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="image_url">{{ __('catalog.fields.image_url') }}</label>
                        <input type="text" class="form-control" id="image_url" name="image_url" value="{{ old('image_url', $product->image_url) }}" placeholder="{{ __('catalog.placeholders.product_image_url') }}">
                    </div>
                    <p class="fs-2 text-center mb-0">{{ __('catalog.products.help.thumbnail') }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-7">
                        <h4 class="card-title">{{ __('catalog.products.sections.status') }}</h4>
                        <div class="p-2 h-100 {{ old('is_active', $product->is_active) ? 'bg-success' : 'bg-danger' }} rounded-circle"></div>
                    </div>
                    <select class="form-select mb-2" name="is_active">
                        <option value="1" @selected((string) old('is_active', $product->is_active) === '1')>{{ __('catalog.status.active') }}</option>
                        <option value="0" @selected((string) old('is_active', $product->is_active) === '0')>{{ __('catalog.status.inactive') }}</option>
                    </select>
                    <p class="fs-2 mb-0">{{ __('catalog.products.help.status') }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-7">{{ __('catalog.products.sections.product_details') }}</h4>
                    <div class="mb-3">
                        <label class="form-label" for="category_id">{{ __('catalog.fields.category') }}</label>
                        <select class="catalog-select2 form-control" id="category_id" name="category_id">
                            <option value="">{{ __('catalog.common.none') }}</option>
                            @foreach($categoryOptions as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>
                                    {!! str_repeat('&nbsp;&nbsp;', $category->depth ?? 0) !!}{{ $category->depth ? '↳ ' : '' }}{{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="fs-2 mb-0">{{ __('catalog.products.help.category') }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="brand_id">{{ __('catalog.fields.brand') }}</label>
                        <select class="catalog-select2 form-control" id="brand_id" name="brand_id">
                            <option value="">{{ __('catalog.common.none') }}</option>
                            @foreach($brandOptions as $brandOption)
                                @php
                                    $brandName = $brandOption->getTranslation('name', app()->getLocale(), false) ?: $brandOption->name;
                                @endphp
                                <option value="{{ $brandOption->id }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brandOption->id)>
                                    {{ $brandName }}
                                </option>
                            @endforeach
                        </select>
                        <p class="fs-2 mb-0">{{ __('catalog.products.help.brand') }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.categories.create') }}" class="btn bg-primary-subtle text-primary btn-sm flex-fill">
                            <span class="fs-4 me-1">+</span>
                            {{ __('catalog.categories.create') }}
                        </a>
                        <a href="{{ route('admin.brands.create') }}" class="btn bg-secondary-subtle text-secondary btn-sm flex-fill">
                            <span class="fs-4 me-1">+</span>
                            {{ __('catalog.brands.create') }}
                        </a>
                    </div>

                    <div class="mt-7">
                        <label class="form-label" for="sku">{{ __('catalog.fields.sku') }}</label>
                        <input type="text" class="form-control" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="{{ __('catalog.placeholders.product_sku') }}">
                    </div>

                    <div class="mt-7">
                        <label class="form-label" for="stock_quantity">{{ __('catalog.fields.stock_quantity') }}</label>
                        <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" placeholder="{{ __('catalog.placeholders.product_stock_quantity') }}">
                    </div>

                    <div class="mt-7">
                        <input type="hidden" name="manage_stock" value="0">
                        <div class="form-check">
                            <input class="form-check-input primary" type="checkbox" name="manage_stock" value="1" id="manage_stock" @checked(old('manage_stock', $product->manage_stock))>
                            <label class="form-check-label" for="manage_stock">
                                {{ __('catalog.fields.manage_stock') }}
                                <iconify-icon icon="solar:question-circle-line-duotone" class="ms-1 align-middle text-muted" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('catalog.products.help.manage_stock') }}" style="cursor: pointer; font-size: 1.1rem;"></iconify-icon>
                            </label>
                        </div>
                    </div>

                    <div class="mt-3">
                        <input type="hidden" name="is_featured" value="0">
                        <div class="form-check">
                            <input class="form-check-input primary" type="checkbox" name="is_featured" value="1" id="is_featured" @checked(old('is_featured', $product->is_featured))>
                            <label class="form-check-label" for="is_featured">{{ __('catalog.fields.is_featured') }}</label>
                        </div>
                    </div>
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
                    <i class="ti ti-alert-triangle me-1"></i>{{ __('catalog.unsaved.title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ __('catalog.unsaved.body') }}
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('catalog.actions.cancel') }}</button>
                <div class="d-flex gap-2">
                    <button type="button" id="btn-discard-changes" class="btn btn-danger">{{ __('catalog.unsaved.discard') }}</button>
                    <button type="button" id="btn-save-draft" class="btn btn-warning text-dark">{{ __('catalog.unsaved.save_draft') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
