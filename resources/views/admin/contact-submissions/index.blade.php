@extends('admin.layouts.app')

@section('title', 'Liên hệ')

@section('content')
    <x-admin.page-header title="Liên hệ">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">Liên hệ</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @php
        $spamReasons = [
            'honeypot' => 'Bot điền ô ẩn',
            'too_fast' => 'Gửi quá nhanh',
            'link_in_name' => 'Có link trong tên',
            'html_link' => 'Có thẻ link',
            'too_many_links' => 'Quá nhiều link',
            'blocked_word' => 'Từ khoá spam',
        ];
    @endphp

    <ul data-tour="contacts.tabs" class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a @class(['nav-link', 'active' => ! $spamBox]) href="{{ route('admin.contact-submissions.index') }}">Hộp thư</a>
        </li>
        <li class="nav-item">
            <a @class(['nav-link', 'active' => $spamBox]) href="{{ route('admin.contact-submissions.index', ['box' => 'spam']) }}">
                Spam @if($spamCount > 0)<span class="badge bg-secondary-subtle text-secondary ms-1">{{ $spamCount }}</span>@endif
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-body">
            @if($spamBox)
                <p class="small text-muted mb-3">Liên hệ bị bộ lọc giữ lại: không gửi email, không vào hộp thư. Nếu là khách thật, bấm "Không phải spam".</p>
            @endif
            <form method="GET" data-responsive-filters class="row g-2 align-items-end">
                @if($spamBox)<input type="hidden" name="box" value="spam">@endif
                <div data-tour="contacts.search" class="col-md-6 col-lg-4">
                    <label class="form-label small fw-bold text-dark mb-1">Tìm kiếm</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Tên, SĐT, email, nội dung...">
                </div>
                <div data-tour="contacts.read-filter" class="col-md-3 col-lg-2">
                    <label class="form-label small fw-bold text-dark mb-1">Trạng thái</label>
                    <select name="is_read" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="0" @selected(request('is_read') === '0')>Chưa đọc</option>
                        <option value="1" @selected(request('is_read') === '1')>Đã đọc</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <x-admin.action-button type="submit" icon="search" class="w-100" :title="__('catalog.actions.search')" aria-label="{{ __('catalog.actions.search') }}" />
                </div>
            </form>
        </div>

        {{-- Filters, pages, read/unread, not-spam and delete update this part only (admin-ajax.js). --}}
        <div class="card-body p-0" data-ajax-list="contacts">
            <div data-tour="contacts.table" class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">Người gửi</th>
                            <th>Liên hệ</th>
                            <th style="min-width: 320px;">Nội dung</th>
                            <th>Thời gian</th>
                            <th data-tour="contacts.read">Trạng thái</th>
                            <th data-tour="contacts.actions" class="text-end pe-4">Hành động</th>
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
                                    @if($submission->is_spam)
                                        <span class="badge bg-warning-subtle text-warning">{{ $spamReasons[$submission->spam_reason] ?? $submission->spam_reason }}</span>
                                        @if($submission->ip)<span class="text-muted small ms-1">IP {{ $submission->ip }}</span>@endif
                                    @endif
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
                                    @if($submission->is_spam)
                                        @can('contacts.update')
                                            <form action="{{ route('admin.contact-submissions.not-spam', $submission) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Không phải spam">
                                                    <i class="ti ti-mail-forward fs-4"></i> Không phải spam
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
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
                    'emptyImage' => asset('admin-assets/images/icons/empty-lienhe.png'),
                    'emptyMessage' => 'Hiện tại chưa có liên hệ nào',
                ])
            @endif

            @if($submissions->total() > 0)
                <div class="px-4 py-3 border-top">
                    @include('admin.shared.pagination', ['paginator' => $submissions])
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
        // Again after each in-place list update (admin-ajax.js): the rows are new.
        AdminAjax.ready(function (root) {
            root.querySelectorAll('.js-view-meta').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('contactMetaContent').textContent = this.getAttribute('data-meta');
                });
            });

            root.querySelectorAll('.js-read-toggle').forEach(function (checkbox) {
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
        });
    </script>
@endpush
