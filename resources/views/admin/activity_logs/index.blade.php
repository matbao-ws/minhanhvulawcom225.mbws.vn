@extends('admin.layouts.app')

@section('title', 'Nhật ký hoạt động')

@section('content')
    <div class="card shadow-none overflow-hidden mb-4" style="background: linear-gradient(135deg, #141e30 0%, #243b55 100%) !important;">
        <div class="card-body px-4 py-3"><h4 class="fw-semibold mb-0 text-white">Nhật ký hoạt động</h4></div>
    </div>

    <div class="card mb-4"><div class="card-body">
        <form class="row g-3" method="GET">
            <div class="col-md-3"><select name="user_id" class="form-select"><option value="">Tất cả người thực hiện</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="action" class="form-select"><option value="">Tất cả thao tác</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="subject_type" class="form-select"><option value="">Tất cả đối tượng</option>@foreach($subjectTypes as $type)<option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ class_basename($type) }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Lọc</button></div>
        </form>
    </div></div>

    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th class="ps-4">Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Đối tượng</th><th>Mô tả</th><th class="pe-4">Thay đổi</th></tr></thead>
        <tbody>@forelse($logs as $log)<tr>
            <td class="ps-4 text-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $log->user?->name ?? 'Hệ thống' }}</td>
            <td><span class="badge bg-primary-subtle text-primary">{{ $log->action }}</span></td>
            <td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
            <td>{{ $log->description }}</td>
            <td class="pe-4"><details><summary class="text-primary">Xem</summary><pre class="small mb-0">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td>
        </tr>@empty<tr><td colspan="6" class="text-center py-5 text-muted">Chưa có hoạt động nào.</td></tr>@endforelse</tbody>
    </table></div>@if($logs->hasPages())<div class="card-body border-top">{{ $logs->links() }}</div>@endif</div>
@endsection
