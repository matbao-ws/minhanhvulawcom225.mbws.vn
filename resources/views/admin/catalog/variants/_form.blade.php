@php
    $contentLanguages = app(\App\Services\LanguageRegistry::class)->active();
    $defaultContentLocale = app(\App\Services\LanguageRegistry::class)->defaultLocale();
    $selectedOptionIds = collect(old('option_value_ids', $variant->relationLoaded('optionValues') ? $variant->optionValues->modelKeys() : []))->map(fn ($id) => (int) $id)->all();
@endphp

@if($product->optionGroups->isEmpty())
    <div class="alert alert-warning d-flex align-items-center justify-content-between">
        <span>Sản phẩm chưa có thuộc tính. Hãy tạo Màu sắc, Kích thước, Dung lượng… trước khi tạo SKU.</span>
        <a href="{{ route('admin.products.options.edit', $product) }}" class="btn btn-sm btn-warning">Cấu hình thuộc tính</a>
    </div>
@else
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-12 mb-3">
                    <h5 class="mb-1">Tổ hợp thuộc tính</h5>
                    <p class="text-muted mb-0">Chọn chính xác một giá trị cho mỗi nhóm. Hệ thống không cho phép tạo SKU trùng tổ hợp.</p>
                </div>
                @foreach($product->optionGroups as $group)
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="option_group_{{ $group->id }}">{{ $group->name }}</label>
                        <select id="option_group_{{ $group->id }}" name="option_value_ids[]" class="form-select" required>
                            <option value="">Chọn {{ $group->name }}</option>
                            @foreach($group->values->where('is_active', true) as $value)
                                <option value="{{ $value->id }}" @selected(in_array($value->id, $selectedOptionIds, true))>
                                    {{ $value->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div class="row">
                <div class="col-12 mb-3">
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        @foreach($contentLanguages as $language)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link d-flex align-items-center gap-2 @if($language->code === $defaultContentLocale) active @endif"
                                   data-bs-toggle="tab"
                                   href="#variant-language-{{ $language->code }}"
                                   role="tab"
                                   aria-selected="{{ $language->code === $defaultContentLocale ? 'true' : 'false' }}">
                                    <span><i class="ti ti-language fs-4"></i></span>
                                    <span>{{ $language->native_name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content">@foreach($contentLanguages as $language)@php($code = $language->code)<div id="variant-language-{{ $code }}" class="tab-pane fade @if($code === $defaultContentLocale) show active @endif">@if($code !== $defaultContentLocale)<div class="text-end mb-2"><button type="button" class="btn btn-sm btn-outline-primary js-translate-locale" data-source-locale="{{ $defaultContentLocale }}" data-target-locale="{{ $code }}">Dịch {{ strtoupper($defaultContentLocale) }} → {{ strtoupper($code) }}</button></div>@endif<label class="form-label">Tên hiển thị (tùy chọn)</label><input class="form-control" id="variant_name_{{ $code }}" name="name[{{ $code }}]" value="{{ old("name.$code", $variant->getTranslation('name', $code, false)) }}" data-i18n-locale="{{ $code }}" data-i18n-field="name" placeholder="Để trống để tự lấy từ tổ hợp"></div>@endforeach</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="sku">SKU</label>
                    <input type="text" class="form-control" id="sku" name="sku" value="{{ old('sku', $variant->sku) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="barcode">Mã vạch</label>
                    <input type="text" class="form-control" id="barcode" name="barcode" value="{{ old('barcode', $variant->barcode) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="price">Giá bán (để trống dùng giá sản phẩm)</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="price" name="price" value="{{ old('price', $variant->price) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="compare_at_price">Giá niêm yết</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="compare_at_price" name="compare_at_price" value="{{ old('compare_at_price', $variant->compare_at_price) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="cost_price">Giá vốn</label>
                    <input type="number" min="0" step="0.01" class="form-control" id="cost_price" name="cost_price" value="{{ old('cost_price', $variant->cost_price) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="stock_quantity">Tồn kho SKU</label>
                    <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', $variant->stock_quantity ?? 0) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="weight_grams">Khối lượng (gram)</label>
                    <input type="number" min="0" class="form-control" id="weight_grams" name="weight_grams" value="{{ old('weight_grams', $variant->weight_grams) }}">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="image_url">Ảnh riêng của SKU (URL)</label>
                    <input type="url" class="form-control" id="image_url" name="image_url" value="{{ old('image_url', $variant->image_url) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="sort_order">Thứ tự</label>
                    <input type="number" min="0" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', $variant->sort_order ?? 0) }}">
                </div>
                <div class="col-12 mb-1 d-flex gap-4">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input primary" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $variant->is_active))>
                        <label class="form-check-label" for="is_active">Đang kinh doanh</label>
                    </div>
                    <input type="hidden" name="is_default" value="0">
                    <div class="form-check">
                        <input class="form-check-input primary" type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default', $variant->is_default))>
                        <label class="form-check-label" for="is_default">SKU mặc định</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.shared.form-actions', ['cancelUrl' => route('admin.products.show', $product)])
    @include('admin.shared.translation-assets')
@endif
