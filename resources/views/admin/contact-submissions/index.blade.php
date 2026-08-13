@extends('admin.layouts.app')

@section('title', 'Liên hệ')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
                <div class="card-body px-4 py-3">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h4 class="fw-semibold mb-1 text-white">Liên hệ</h4>
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                                    <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Liên hệ</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" data-responsive-filters class="row g-2 align-items-end">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label small fw-bold text-dark mb-1">Tìm kiếm</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Tên, SĐT, email, nội dung...">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small fw-bold text-dark mb-1">Trạng thái</label>
                    <select name="is_read" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="0" @selected(request('is_read') === '0')>Chưa đọc</option>
                        <option value="1" @selected(request('is_read') === '1')>Đã đọc</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit"><i class="ti ti-search fs-5"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">Người gửi</th>
                            <th>Liên hệ</th>
                            <th style="min-width: 320px;">Nội dung</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <th class="text-end pe-4">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $submission)
                            <tr @class(['fw-semibold' => ! $submission->is_read])>
                                <td class="ps-4">{{ $submission->name }}</td>
                                <td>
                                    <div>{{ $submission->phone }}</div>
                                    @if($submission->email)
                                        <div class="text-muted small">{{ $submission->email }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-dark text-wrap" style="max-width: 450px;">{{ \Illuminate\Support\Str::limit($submission->message, 200) }}</div>
                                    @if(!empty($submission->meta))
                                        <button type="button" class="btn btn-sm btn-link p-0 js-view-meta" data-bs-toggle="modal" data-bs-target="#contactMetaModal" data-meta="{{ json_encode($submission->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}">
                                            Xem thông tin thêm
                                        </button>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <span class="text-dark small">{{ $submission->created_at->format('d/m/Y H:i') }}</span>
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input js-read-toggle" type="checkbox" role="switch"
                                            data-url="{{ route('admin.contact-submissions.toggle-read', $submission) }}"
                                            @checked($submission->is_read)>
                                    </div>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <form action="{{ route('admin.contact-submissions.destroy', $submission) }}" method="POST" class="js-delete-form" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                            <i class="ti ti-trash fs-4"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($submissions->isEmpty())
                @include('admin.shared.empty-state', [
                    'emptyImage' => asset('admin-assets/images/icons/emptycomment.png'),
                    'emptyMessage' => 'Hiện tại chưa có liên hệ nào',
                ])
            @endif

            @if($submissions->hasPages())
                <div class="px-4 py-3 border-top">
                    {{ $submissions->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="contactMetaModal" tabindex="-1" aria-labelledby="contactMetaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold text-dark" id="contactMetaModalLabel">Thông tin thêm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre class="mb-0 text-dark" id="contactMetaContent" style="white-space: pre-wrap;"></pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-view-meta').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('contactMetaContent').textContent = this.getAttribute('data-meta');
                });
            });

            document.querySelectorAll('.js-read-toggle').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const url = this.getAttribute('data-url');
                    const isChecked = this.checked;
                    const row = this.closest('tr');

                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                    })
                    .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                    .then(function (data) {
                        if (data.success && row) row.classList.toggle('fw-semibold', !data.is_read);
                    })
                    .catch(function () {
                        checkbox.checked = !isChecked;
                    });
                });
            });

            document.querySelectorAll('.js-delete-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!confirm('Xóa liên hệ này?')) event.preventDefault();
                });
            });
        });
    </script>
@endpush
