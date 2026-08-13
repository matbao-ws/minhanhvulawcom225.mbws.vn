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
    <div class="card shadow-none overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
        <div class="card-body px-4 py-3"><h4 class="fw-semibold mb-0 text-white">Nhật ký hoạt động</h4></div>
    </div>

    <div class="card mb-4"><div class="card-body">
        <form class="row g-3" method="GET">
            <div class="col-md-3"><select name="user_id" class="form-select"><option value="">Tất cả người thực hiện</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="action" class="form-select"><option value="">Tất cả thao tác</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ \App\Models\AdminActivityLog::actionLabelFor($action) }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="subject_type" class="form-select"><option value="">Tất cả đối tượng</option>@foreach($subjectTypes as $type)<option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ \App\Models\AdminActivityLog::subjectLabelFor($type) }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Lọc</button></div>
        </form>
    </div></div>

    <div class="card"><div class="table-responsive activity-log-table-wrap"><table class="table table-hover align-middle mb-0 activity-log-table">
        <thead><tr><th class="ps-4">Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Đối tượng</th><th>Mô tả</th><th class="pe-4">Thay đổi</th></tr></thead>
        <tbody>@forelse($logs as $log)<tr>
            <td class="ps-4 text-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
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
        </tr>@empty<tr><td colspan="6" class="text-center py-5 text-muted">Chưa có hoạt động nào.</td></tr>@endforelse</tbody>
    </table></div>@if($logs->hasPages())<div class="card-body border-top">{{ $logs->links() }}</div>@endif</div>
@endsection
