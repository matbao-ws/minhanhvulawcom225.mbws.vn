@extends('admin.layouts.app')

@section('title', 'Liên hệ')

@push('styles')
    <style>
        .contact-inbox { display: grid; grid-template-columns: minmax(280px, 380px) minmax(0, 1fr); min-height: 520px; }
        .contact-inbox__list { border-right: 1px solid var(--bs-border-color); max-height: 70vh; overflow-y: auto; }
        .contact-inbox__item { display: block; width: 100%; text-align: left; background: none; border: 0; border-bottom: 1px solid var(--bs-border-color); border-left: 3px solid transparent; padding: 12px 16px; color: inherit; }
        .contact-inbox__item:hover { background: var(--bs-tertiary-bg); }
        .contact-inbox__item.is-active { background: var(--bs-primary-bg-subtle); border-left-color: var(--bs-primary); }
        .contact-inbox__item.is-unread .contact-inbox__name { font-weight: 700; }
        .contact-inbox__item.is-unread .contact-inbox__name::before { content: ""; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: var(--bs-primary); vertical-align: middle; }
        .contact-inbox__preview { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: .8125rem; color: var(--bs-secondary-color); margin-top: 2px; }
        .contact-inbox__pane { min-width: 0; }
        .contact-inbox__placeholder { height: 100%; min-height: 320px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--bs-secondary-color); }
        .contact-inbox__detail { padding: 24px 28px; }
        .contact-inbox__message { white-space: pre-wrap; word-break: break-word; line-height: 1.65; font-size: .9375rem; color: var(--bs-emphasis-color); background: var(--bs-tertiary-bg); border-radius: 8px; padding: 16px 18px; }
        .contact-inbox__meta { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0; }
        .contact-inbox__meta dt { font-weight: 500; color: var(--bs-secondary-color); }
        .contact-inbox__meta dd { margin: 0; word-break: break-word; }
        .contact-inbox__back { display: none; }

        /* Below lg the list takes the width; an opened message covers the screen with a way back. */
        @media (max-width: 991.98px) {
            .contact-inbox { grid-template-columns: minmax(0, 1fr); min-height: 0; }
            .contact-inbox__list { border-right: 0; max-height: none; }
            .contact-inbox__pane { display: none; }
            .contact-inbox.is-open .contact-inbox__pane { display: block; position: fixed; inset: 0; z-index: 1055; overflow-y: auto; background: var(--bs-body-bg); }
            .contact-inbox__placeholder { display: none; }
            .contact-inbox__detail { padding: 16px; }
            .contact-inbox__back { display: inline-flex; }
            .contact-inbox__actions .btn { flex: 1 1 auto; }
        }
    </style>
@endpush

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
        $canUpdate = auth()->user()->can('contacts.update');
        $canDelete = auth()->user()->can('contacts.delete');
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
        <div class="card-body border-bottom">
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
        <div data-tour="contacts.table" class="card-body p-0" data-ajax-list="contacts">
            @if($submissions->isEmpty())
                @include('admin.shared.empty-state', [
                    'emptyImage' => asset('admin-assets/images/icons/empty-lienhe.png'),
                    'emptyMessage' => 'Hiện tại chưa có liên hệ nào',
                ])
            @else
                <div class="contact-inbox" data-contact-inbox>
                    <div class="contact-inbox__list">
                        @foreach($submissions as $submission)
                            <button type="button" id="contact-item-{{ $submission->id }}"
                                @class(['contact-inbox__item', 'is-unread' => ! $submission->is_read])
                                data-contact-open="{{ $submission->id }}" aria-controls="contact-detail-{{ $submission->id }}">
                                <span class="d-flex justify-content-between align-items-baseline gap-2">
                                    <span class="contact-inbox__name text-dark text-truncate">{{ $submission->name }}</span>
                                    <span class="text-muted small text-nowrap">{{ $submission->created_at->isToday() ? $submission->created_at->format('H:i') : $submission->created_at->format('d/m/Y') }}</span>
                                </span>
                                <span class="contact-inbox__preview">{{ \Illuminate\Support\Str::limit($submission->message, 160) }}</span>
                                @if($submission->is_spam)
                                    <span class="badge bg-warning-subtle text-warning mt-1">{{ $spamReasons[$submission->spam_reason] ?? $submission->spam_reason }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="contact-inbox__pane">
                        <div class="contact-inbox__placeholder" data-contact-placeholder>
                            <i class="ti ti-mail-opened fs-10"></i>
                            <span>Chọn một liên hệ để đọc</span>
                        </div>

                        @foreach($submissions as $submission)
                            @php
                                // Zalo opens a chat by the local number: +84 / 84 become 0.
                                $digits = preg_replace('/\D/', '', (string) $submission->phone);
                                $zalo = str_starts_with($digits, '84') && strlen($digits) >= 11 ? '0'.substr($digits, 2) : $digits;
                            @endphp
                            <article id="contact-detail-{{ $submission->id }}" class="contact-inbox__detail" hidden
                                data-contact-detail="{{ $submission->id }}"
                                data-read="{{ $submission->is_read ? 1 : 0 }}"
                                @if($canUpdate) data-read-url="{{ route('admin.contact-submissions.toggle-read', $submission) }}" @endif>
                                <button type="button" class="btn btn-sm btn-link px-0 mb-2 contact-inbox__back align-items-center gap-1" data-contact-close>
                                    <i class="ti ti-arrow-left"></i> Danh sách
                                </button>

                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                    <div style="min-width: 0;">
                                        <h5 class="fw-semibold text-dark mb-1">{{ $submission->name }}</h5>
                                        <div class="text-muted small">
                                            @if($submission->phone)<a href="tel:{{ $digits }}" class="text-reset">{{ $submission->phone }}</a>@endif
                                            @if($submission->email) · <a href="mailto:{{ $submission->email }}" class="text-reset">{{ $submission->email }}</a>@endif
                                        </div>
                                        <div class="text-muted small">{{ $submission->created_at->format('d/m/Y H:i') }}</div>
                                    </div>
                                    @if($submission->is_spam)
                                        <div class="text-end">
                                            <span class="badge bg-warning-subtle text-warning">{{ $spamReasons[$submission->spam_reason] ?? $submission->spam_reason }}</span>
                                            @if($submission->ip)<div class="text-muted small mt-1">IP {{ $submission->ip }}</div>@endif
                                        </div>
                                    @endif
                                </div>

                                <div class="contact-inbox__actions d-flex flex-wrap gap-2 mb-3">
                                    @if($digits !== '')
                                        <a href="tel:{{ $digits }}" class="btn btn-sm btn-primary"><i class="ti ti-phone me-1"></i>Gọi</a>
                                        <a href="https://zalo.me/{{ $zalo }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="ti ti-message-circle me-1"></i>Zalo</a>
                                    @endif
                                    @if($submission->email)
                                        <a href="mailto:{{ $submission->email }}?subject={{ rawurlencode('Phản hồi yêu cầu liên hệ') }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-mail me-1"></i>Email</a>
                                    @endif
                                </div>

                                <div class="contact-inbox__message mb-3">{{ $submission->message }}</div>

                                @if(!empty($submission->meta))
                                    <h6 class="fw-semibold text-dark mb-2">Thông tin thêm</h6>
                                    <dl class="contact-inbox__meta small mb-3">
                                        @foreach($submission->meta as $key => $value)
                                            <dt>{{ ucfirst(str_replace('_', ' ', (string) $key)) }}</dt>
                                            <dd>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                                        @endforeach
                                    </dl>
                                @endif

                                <div class="d-flex flex-wrap justify-content-end gap-2 border-top pt-3">
                                    @if($canUpdate)
                                        <button type="button" data-tour="contacts.read" class="btn btn-sm bg-secondary-subtle text-secondary" data-contact-toggle-read>
                                            {{ $submission->is_read ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc' }}
                                        </button>
                                    @endif
                                    <span data-tour="contacts.actions" class="d-inline-flex flex-wrap gap-2">
                                        @if($submission->is_spam && $canUpdate)
                                            <form action="{{ route('admin.contact-submissions.not-spam', $submission) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-success"><i class="ti ti-mail-forward me-1"></i>Không phải spam</button>
                                            </form>
                                        @endif
                                        @if($canDelete)
                                            <form action="{{ route('admin.contact-submissions.destroy', $submission) }}" method="POST" class="js-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-trash me-1"></i>Xóa</button>
                                            </form>
                                        @endif
                                    </span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <div class="px-4 py-3 border-top">
                    @include('admin.shared.pagination', ['paginator' => $submissions])
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Again after each in-place list update (admin-ajax.js): the list and its messages are new.
        AdminAjax.ready(function (root) {
            const inbox = root.querySelector('[data-contact-inbox]');
            if (!inbox) return;

            const placeholder = inbox.querySelector('[data-contact-placeholder]');

            function setRead(detail, isRead) {
                detail.setAttribute('data-read', isRead ? '1' : '0');
                const item = inbox.querySelector('[data-contact-open="' + detail.getAttribute('data-contact-detail') + '"]');
                if (item) item.classList.toggle('is-unread', !isRead);
                const button = detail.querySelector('[data-contact-toggle-read]');
                if (button) button.textContent = isRead ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc';
            }

            // The endpoint flips the flag; the answer says where it ended up.
            function toggleRead(detail) {
                const url = detail.getAttribute('data-read-url');
                if (!url) return;
                fetch(url, {
                    method: 'PATCH',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                })
                    .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                    .then(function (data) { if (data.success) setRead(detail, data.is_read); })
                    .catch(function () {});
            }

            function open(id, markRead) {
                const detail = inbox.querySelector('[data-contact-detail="' + id + '"]');
                if (!detail) return;
                inbox.querySelectorAll('[data-contact-detail]').forEach(function (other) { other.hidden = other !== detail; });
                inbox.querySelectorAll('[data-contact-open]').forEach(function (item) {
                    item.classList.toggle('is-active', item.getAttribute('data-contact-open') === String(id));
                });
                if (placeholder) placeholder.hidden = true;
                inbox.classList.add('is-open');
                history.replaceState(null, '', '#contact-' + id);
                // Opening a message is reading it.
                if (markRead && detail.getAttribute('data-read') === '0') toggleRead(detail);
            }

            inbox.querySelectorAll('[data-contact-open]').forEach(function (item) {
                item.addEventListener('click', function () { open(this.getAttribute('data-contact-open'), true); });
            });
            inbox.querySelectorAll('[data-contact-toggle-read]').forEach(function (button) {
                button.addEventListener('click', function () { toggleRead(this.closest('[data-contact-detail]')); });
            });
            inbox.querySelectorAll('[data-contact-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    inbox.classList.remove('is-open');
                    history.replaceState(null, '', location.pathname + location.search);
                });
            });

            // F5 or a shared link keeps the open message, without counting as reading it again.
            const match = location.hash.match(/^#contact-(\d+)$/);
            if (match) open(match[1], false);
        });
    </script>
@endpush
