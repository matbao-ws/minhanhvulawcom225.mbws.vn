@extends('admin.layouts.app')

@section('title', 'Ngôn ngữ nội dung')

@section('content')
    <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
        <div class="card-body px-4 py-3">
            <h4 class="fw-semibold mb-1 text-white">Ngôn ngữ nội dung</h4>
            <p class="mb-0 text-white-50">Quản lý locale dùng cho URL, nội dung và dữ liệu trả về API.</p>
        </div>
    </div>

    <div class="alert alert-info">
        Thêm ngôn ngữ tại đây không cần thêm cột database. Tên, mô tả và nội dung được lưu theo mã locale trong JSON; slug được lưu riêng theo locale.
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-2">Ưu tiên nội dung</h5>
            <p class="text-muted mb-3">Ngôn ngữ mặc định dùng để nhập nội dung chính. Fallback chỉ được dùng khi bản dịch đang xem bị trống và không làm thay đổi URL quản trị.</p>
            <form method="POST" action="{{ route('admin.languages.preferences') }}" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-5">
                    <label class="form-label" for="default_locale">Ngôn ngữ mặc định</label>
                    <select class="form-select" id="default_locale" name="default_locale" required>
                        @foreach($languages->where('is_active', true) as $language)
                            <option value="{{ $language->code }}" @selected(old('default_locale', $languages->firstWhere('is_default', true)?->code) === $language->code)>
                                {{ $language->native_name }} ({{ strtoupper($language->code) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="fallback_locale">Fallback nội dung</label>
                    <select class="form-select" id="fallback_locale" name="fallback_locale" required>
                        @foreach($languages->where('is_active', true) as $language)
                            <option value="{{ $language->code }}" @selected(old('fallback_locale', $languages->firstWhere('is_content_fallback', true)?->code) === $language->code)>
                                {{ $language->native_name }} ({{ strtoupper($language->code) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Lưu ưu tiên</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Thêm ngôn ngữ</h5>
            <form method="POST" action="{{ route('admin.languages.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2"><label class="form-label">Mã locale</label><input class="form-control" name="code" placeholder="zh" maxlength="16" required></div>
                <div class="col-md-2"><label class="form-label">Tên tiếng Anh</label><input class="form-control" name="name" placeholder="Chinese" required></div>
                <div class="col-md-2"><label class="form-label">Tên bản địa</label><input class="form-control" name="native_name" placeholder="中文" required></div>
                <div class="col-md-2"><label class="form-label">Regional</label><input class="form-control" name="regional" placeholder="zh_CN"></div>
                <div class="col-md-1"><label class="form-label">Thứ tự</label><input type="number" min="0" class="form-control" name="sort_order" value="20"></div>
                <div class="col-md-1"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="is_active" value="1" checked id="new_language_active"><label class="form-check-label" for="new_language_active">Bật</label></div></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Thêm ngôn ngữ</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Ngôn ngữ hiện có</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Mã</th><th>Tên</th><th>Regional</th><th>Trạng thái</th><th>Mặc định</th><th>Fallback nội dung</th><th class="text-end">Lưu</th></tr></thead>
                    <tbody>
                    @foreach($languages as $language)
                        <tr>
                            <td colspan="7" class="p-0 border-0">
                                <form method="POST" action="{{ route('admin.languages.update', $language) }}" class="row g-2 align-items-center py-2 mx-0 border-bottom">
                                    @csrf @method('PUT')
                                    <div class="col-md-1"><input class="form-control" name="code" value="{{ $language->code }}" required></div>
                                    <div class="col-md-2"><input class="form-control mb-1" name="name" value="{{ $language->name }}" required><input class="form-control" name="native_name" value="{{ $language->native_name }}" required></div>
                                    <div class="col-md-2"><input class="form-control mb-1" name="regional" value="{{ $language->regional }}"><input class="form-control" name="flag_path" value="{{ $language->flag_path }}" placeholder="Đường dẫn cờ"></div>
                                    <div class="col-md-1"><input type="number" min="0" class="form-control" name="sort_order" value="{{ $language->sort_order }}"></div>
                                    <div class="col-md-1 text-center"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($language->is_active)></div>
                                    <div class="col-md-1 text-center">@if($language->is_default)<span class="badge bg-primary">Mặc định</span>@else<span class="text-muted">—</span>@endif</div>
                                    <div class="col-md-2 text-center">@if($language->is_content_fallback)<span class="badge bg-info-subtle text-info">Fallback</span>@else<span class="text-muted">—</span>@endif</div>
                                    <div class="col-md-2 text-end"><button class="btn btn-outline-primary">Lưu {{ strtoupper($language->code) }}</button></div>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
