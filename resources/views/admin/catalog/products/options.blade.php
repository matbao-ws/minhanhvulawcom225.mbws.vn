@extends('admin.layouts.app')

@section('title', 'Thuộc tính biến thể')

@section('content')
    <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #141e30 0%, #243b55 100%) !important;">
        <div class="card-body px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fw-semibold mb-1 text-white">Thuộc tính biến thể: {{ $product->name }}</h4>
                <div class="text-white-50">Khai báo các giá trị trước, sau đó tạo các tổ hợp SKU.</div>
            </div>
            <a href="{{ route('admin.products.show', $product) }}" class="btn btn-light">Quay lại sản phẩm</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.options.update', $product) }}" class="admin-form-with-sticky-actions">
        @csrf
        @method('PUT')
        <div id="option-groups" class="d-flex flex-column gap-3">
            @forelse($product->optionGroups as $groupIndex => $group)
                <div class="card option-group" data-group-index="{{ $groupIndex }}">
                    <div class="card-body">
                        <input type="hidden" name="groups[{{ $groupIndex }}][id]" value="{{ $group->id }}">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tên nhóm</label>
                                <input class="form-control" name="groups[{{ $groupIndex }}][name]" value="{{ old("groups.$groupIndex.name", $group->name) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kiểu hiển thị</label>
                                <select class="form-select" name="groups[{{ $groupIndex }}][display_type]">
                                    @foreach(['select' => 'Danh sách', 'color' => 'Màu sắc', 'image' => 'Ảnh'] as $type => $label)
                                        <option value="{{ $type }}" @selected(old("groups.$groupIndex.display_type", $group->display_type) === $type)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-group">Xóa nhóm</button></div>
                        </div>
                        <div class="values d-flex flex-column gap-2">
                            @foreach($group->values as $valueIndex => $value)
                                @include('admin.catalog.products.partials.option-value-row', ['groupIndex' => $groupIndex, 'valueIndex' => $valueIndex, 'value' => $value])
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-value">+ Thêm giá trị</button>
                    </div>
                </div>
            @empty
                <div class="card option-group" data-group-index="0">
                    <div class="card-body">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-6"><label class="form-label">Tên nhóm</label><input class="form-control" name="groups[0][name]" value="Màu sắc" required></div>
                            <div class="col-md-4"><label class="form-label">Kiểu hiển thị</label><select class="form-select" name="groups[0][display_type]"><option value="select">Danh sách</option><option value="color">Màu sắc</option><option value="image">Ảnh</option></select></div>
                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-group">Xóa nhóm</button></div>
                        </div>
                        <div class="values d-flex flex-column gap-2">
                            @include('admin.catalog.products.partials.option-value-row', ['groupIndex' => 0, 'valueIndex' => 0, 'value' => null])
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-value">+ Thêm giá trị</button>
                    </div>
                </div>
            @endforelse
        </div>
        <button type="button" id="add-group" class="btn btn-outline-primary mt-3">+ Thêm nhóm thuộc tính</button>
        @include('admin.shared.form-actions', ['cancelUrl' => route('admin.products.show', $product)])
    </form>

    <template id="value-template">
        <div class="row g-2 value-row align-items-end">
            <div class="col-md-4"><label class="form-label">Giá trị</label><input class="form-control" data-name="groups[__GROUP__][values][__VALUE__][label]" required></div>
            <div class="col-md-2"><label class="form-label">Mã màu</label><input class="form-control" data-name="groups[__GROUP__][values][__VALUE__][color_hex]" placeholder="#FFFFFF"></div>
            <div class="col-md-4"><label class="form-label">Ảnh URL</label><input type="url" class="form-control" data-name="groups[__GROUP__][values][__VALUE__][image_url]"></div>
            <div class="col-md-1"><div class="form-check mb-2"><input type="hidden" data-name="groups[__GROUP__][values][__VALUE__][is_active]" value="0"><input class="form-check-input" type="checkbox" data-name="groups[__GROUP__][values][__VALUE__][is_active]" value="1" checked><label class="form-check-label">Bật</label></div></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-value">×</button></div>
        </div>
    </template>
    <template id="group-template">
        <div class="card option-group">
            <div class="card-body">
                <div class="row align-items-end mb-3">
                    <div class="col-md-6"><label class="form-label">Tên nhóm</label><input class="form-control" data-name="groups[__GROUP__][name]" required></div>
                    <div class="col-md-4"><label class="form-label">Kiểu hiển thị</label><select class="form-select" data-name="groups[__GROUP__][display_type]"><option value="select">Danh sách</option><option value="color">Màu sắc</option><option value="image">Ảnh</option></select></div>
                    <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-group">Xóa nhóm</button></div>
                </div>
                <div class="values d-flex flex-column gap-2"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-3 add-value">+ Thêm giá trị</button>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const groups = document.getElementById('option-groups');
    const valueTemplate = document.getElementById('value-template');
    const groupTemplate = document.getElementById('group-template');
    const nextGroupIndex = () => Math.max(-1, ...[...groups.querySelectorAll('.option-group')].map((el) => Number(el.dataset.groupIndex || -1))) + 1;
    const addValue = (group) => {
        const groupIndex = group.dataset.groupIndex;
        const valueIndex = group.querySelectorAll('.value-row').length;
        const node = valueTemplate.content.cloneNode(true);
        node.querySelectorAll('[data-name]').forEach((input) => input.name = input.dataset.name.replaceAll('__GROUP__', groupIndex).replaceAll('__VALUE__', valueIndex));
        group.querySelector('.values').appendChild(node);
    };
    document.getElementById('add-group').addEventListener('click', () => {
        const groupIndex = nextGroupIndex();
        const node = groupTemplate.content.cloneNode(true);
        const group = node.querySelector('.option-group');
        group.dataset.groupIndex = groupIndex;
        group.querySelectorAll('[data-name]').forEach((input) => input.name = input.dataset.name.replaceAll('__GROUP__', groupIndex));
        groups.appendChild(node);
        addValue(groups.lastElementChild);
    });
    groups.addEventListener('click', (event) => {
        if (event.target.closest('.add-value')) addValue(event.target.closest('.option-group'));
        if (event.target.closest('.remove-value')) event.target.closest('.value-row').remove();
        if (event.target.closest('.remove-group')) event.target.closest('.option-group').remove();
    });
});
</script>
@endpush
