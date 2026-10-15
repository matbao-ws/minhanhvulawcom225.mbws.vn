@extends('admin.layouts.app')

@section('title', 'Nhật ký hoạt động')

@push('styles')
    <style>
        .activity-log-table-wrap {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }

        .activity-log-table {
            min-width: 1180px;
        }

        .activity-log-table th {
            white-space: nowrap;
            padding: 1rem 1.25rem;
        }

        .activity-log-table td {
            padding: 1rem 1.25rem;
            vertical-align: top;
        }

        .activity-log-description {
            min-width: 280px;
        }

        .activity-log-changes {
            min-width: 250px;
        }
    </style>
@endpush

@section('content')
    <x-admin.page-header title="Nhật ký hoạt động">
        @if($canDelete && $logs->total() > 0)
            <x-slot:actions>
                {{-- Deletes what the filters on screen match: everything when none is set. --}}
                <form method="POST" action="{{ route('admin.activity-logs.destroy-all') }}" class="js-delete-form"
                      data-confirm-title="{{ collect(request()->only(['user_id', 'action', 'subject_type', 'from', 'to']))->filter()->isNotEmpty() ? 'Xóa toàn bộ nhật ký theo bộ lọc?' : 'Xóa toàn bộ nhật ký?' }}"
                      data-confirm-text="{{ number_format($logs->total()) }} bản ghi sẽ bị xóa vĩnh viễn và không thể khôi phục."
                      data-confirm-btn="Xóa tất cả">
                    @csrf
                    @method('DELETE')
                    @foreach(request()->only(['user_id', 'action', 'subject_type', 'from', 'to']) as $key => $value)
                        @if(filled($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <x-admin.action-button data-tour="activity.delete-all" type="submit" variant="danger" icon="trash">Xóa tất cả ({{ number_format($logs->total()) }})</x-admin.action-button>
                </form>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <div class="card">
        <div class="card-body border-bottom p-3 p-md-4">
            <button class="btn btn-outline-primary d-md-none w-100 d-flex align-items-center justify-content-between"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#activity-log-filters"
                    aria-expanded="{{ request()->hasAny(['user_id', 'action', 'subject_type', 'from', 'to']) ? 'true' : 'false' }}"
                    aria-controls="activity-log-filters">
                <span><i class="ti ti-adjustments-horizontal me-2" aria-hidden="true"></i>Bộ lọc nhật ký</span>
                <i class="ti ti-chevron-down" aria-hidden="true"></i>
            </button>

            <div data-tour="activity.filters" class="collapse d-md-block {{ request()->hasAny(['user_id', 'action', 'subject_type', 'from', 'to']) ? 'show' : '' }}" id="activity-log-filters">
                <form class="row g-2 align-items-end mt-1 mt-md-0" method="GET">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label small fw-bold text-muted mb-1" for="activity_user_id">Người thực hiện</label>
                        <select name="user_id" id="activity_user_id" class="form-select"><option value="">Tất cả người thực hiện</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-muted mb-1" for="activity_action">Thao tác</label>
                        <select name="action" id="activity_action" class="form-select"><option value="">Tất cả thao tác</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ \App\Models\AdminActivityLog::actionLabelFor($action) }}</option>@endforeach</select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-muted mb-1" for="activity_subject_type">Đối tượng</label>
                        <select name="subject_type" id="activity_subject_type" class="form-select"><option value="">Tất cả đối tượng</option>@foreach($subjectTypes as $type)<option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ \App\Models\AdminActivityLog::subjectLabelFor($type) }}</option>@endforeach</select>
                    </div>
                    <div data-tour="activity.dates" class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-muted mb-1" for="activity_from">Từ ngày</label>
                        <input type="date" name="from" id="activity_from" value="{{ request('from') }}" class="form-control">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-muted mb-1" for="activity_to">Đến ngày</label>
                        <input type="date" name="to" id="activity_to" value="{{ request('to') }}" class="form-control">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-1"><x-admin.action-button type="submit" icon="search" class="w-100" title="Lọc" aria-label="Lọc"><span class="d-lg-none">Lọc</span></x-admin.action-button></div>
                </form>
            </div>
        </div>

        @if($canDelete)
        <form id="activity-bulk-form" method="POST" action="{{ route('admin.activity-logs.bulk-destroy') }}" class="js-delete-form d-none"
              data-confirm-title="Xóa các bản ghi đã chọn?"
              data-confirm-text="Các bản ghi đã chọn sẽ bị xóa vĩnh viễn và không thể khôi phục."
              data-confirm-btn="Xóa đã chọn">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @if($canDelete)
        <div class="d-none align-items-center gap-2 px-4 py-3 border-bottom bg-light-subtle" data-activity-bulk-toolbar>
            <span class="fw-semibold text-dark"><span data-activity-bulk-count>0</span> bản ghi đã chọn</span>
            <button type="submit" form="activity-bulk-form" class="btn btn-sm btn-outline-danger">
                <i class="ti ti-trash me-1" aria-hidden="true"></i>Xóa đã chọn
            </button>
            <button type="button" class="btn btn-sm btn-link text-secondary ms-auto" data-activity-bulk-clear>Bỏ chọn</button>
        </div>
    @endif
    <div data-tour="activity.table" class="table-responsive activity-log-table-wrap"><table class="table table-hover align-middle mb-0 activity-log-table">
        <thead><tr>@if($canDelete)<th class="ps-4" style="width: 48px;"><input data-tour="activity.select-all" type="checkbox" class="form-check-input" data-activity-select-all aria-label="Chọn tất cả trên trang"></th>@endif<th class="{{ $canDelete ? '' : 'ps-4' }}">Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Đối tượng</th><th>Mô tả</th><th data-tour="activity.changes" class="pe-4">Thay đổi</th></tr></thead>
        <tbody>@forelse($logs as $log)<tr>
            @if($canDelete)<td class="ps-4"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $log->id }}" form="activity-bulk-form" data-activity-select aria-label="Chọn bản ghi #{{ $log->id }}"></td>@endif
            <td class="{{ $canDelete ? '' : 'ps-4' }} text-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $log->user?->name ?? 'Hệ thống' }}</td>
            <td><span class="badge bg-primary-subtle text-primary">{{ $log->displayActionLabel() }}</span></td>
            <td>{{ $log->displaySubjectLabel() }}@if($log->subject_id) #{{ $log->subject_id }}@endif</td>
            <td class="activity-log-description">{{ $log->displayDescription() }}</td>
            <td class="pe-4 activity-log-changes">
                @forelse($log->displayChanges() as $label => $value)
                    <div class="small mb-1"><span class="text-muted">{{ $label }}:</span> {{ $value }}</div>
                @empty
                    <span class="text-muted small">Không có chi tiết thêm.</span>
                @endforelse
            </td>
        </tr>@empty<tr><td colspan="{{ $canDelete ? 7 : 6 }}">@include('admin.shared.empty-state', [
            'emptyImage' => asset('admin-assets/images/icons/empty-nhatkyhoatdong.png'),
            'emptyMessage' => 'Chưa có hoạt động nào.',
        ])</td></tr>@endforelse</tbody>
    </table></div>@if($logs->total() > 0)<div class="card-body border-top">@include('admin.shared.pagination', ['paginator' => $logs])</div>@endif</div>
@endsection

@if($canDelete)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toolbar = document.querySelector('[data-activity-bulk-toolbar]');
                const selectAll = document.querySelector('[data-activity-select-all]');
                const boxes = Array.from(document.querySelectorAll('[data-activity-select]'));
                const form = document.getElementById('activity-bulk-form');
                if (!toolbar || !form || !boxes.length) return;

                const refresh = function () {
                    const total = boxes.filter(function (box) { return box.checked; }).length;
                    toolbar.classList.toggle('d-none', total === 0);
                    toolbar.classList.toggle('d-flex', total > 0);
                    toolbar.querySelector('[data-activity-bulk-count]').textContent = String(total);
                    form.dataset.confirmText = total + ' bản ghi đã chọn sẽ bị xóa vĩnh viễn và không thể khôi phục.';
                    if (selectAll) {
                        selectAll.checked = total > 0 && total === boxes.length;
                        selectAll.indeterminate = total > 0 && total < boxes.length;
                    }
                };

                boxes.forEach(function (box) { box.addEventListener('change', refresh); });
                selectAll?.addEventListener('change', function () {
                    boxes.forEach(function (box) { box.checked = selectAll.checked; });
                    refresh();
                });
                toolbar.querySelector('[data-activity-bulk-clear]').addEventListener('click', function () {
                    boxes.forEach(function (box) { box.checked = false; });
                    refresh();
                });
            });
        </script>
    @endpush
@endif
