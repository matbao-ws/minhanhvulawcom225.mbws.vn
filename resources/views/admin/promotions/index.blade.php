@extends('admin.layouts.app')

@section('title', 'Chương trình khuyến mãi')

@section('content')
    <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
        <div class="card-body px-4 py-3 d-flex align-items-center justify-content-between">
            <div><h4 class="fw-semibold mb-1 text-white">Chương trình khuyến mãi & Flash Sale</h4><div class="text-white-50">Giá tự động theo sản phẩm/SKU, không cần nhập mã.</div></div>
            <a href="{{ route('admin.promotions.create') }}" class="btn btn-light">+ Tạo chương trình</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2"><div class="col-md-6"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Tìm tên chương trình"></div><div class="col-md-4"><select name="kind" class="form-select"><option value="">Tất cả loại</option><option value="automatic" @selected(request('kind') === 'automatic')>Khuyến mãi tự động</option><option value="flash_sale" @selected(request('kind') === 'flash_sale')>Flash Sale</option></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Lọc</button></div></form>
        </div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th class="ps-4">Chương trình</th><th>Loại / mức giảm</th><th>Phạm vi</th><th>Thời gian</th><th>Lượt</th><th>Trạng thái</th><th class="text-end pe-4">Thao tác</th></tr></thead><tbody>
        @forelse($promotions as $promotion)
            @php $active = $promotion->is_active && (! $promotion->start_at || ! $promotion->start_at->isFuture()) && (! $promotion->end_at || ! $promotion->end_at->isPast()); @endphp
            <tr><td class="ps-4"><div class="fw-semibold">{{ $promotion->name }}</div><small class="text-muted">Ưu tiên {{ $promotion->priority }} · Tối thiểu {{ $promotion->min_quantity }} SP</small></td><td><span class="badge {{ $promotion->kind === 'flash_sale' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}">{{ $promotion->kind === 'flash_sale' ? 'Flash Sale' : 'Tự động' }}</span><div class="mt-1 fw-semibold">@if($promotion->discount_type === 'percentage') -{{ number_format($promotion->value, 0) }}%@elseif($promotion->discount_type === 'fixed_price') Giá {{ number_format($promotion->value) }}đ @else -{{ number_format($promotion->value) }}đ @endif</div></td><td>{{ $promotion->applies_to === 'all_products' ? 'Toàn bộ sản phẩm' : $promotion->targets_count.' mục tiêu' }}</td><td><small>{{ $promotion->start_at?->format('d/m H:i') ?? 'Ngay' }}<br>→ {{ $promotion->end_at?->format('d/m H:i') ?? 'Không hạn' }}</small></td><td>{{ $promotion->used_count }} / {{ $promotion->quantity_limit ?? '∞' }}</td><td><span class="badge {{ $active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $active ? 'Đang chạy' : 'Tạm dừng/Kết thúc' }}</span></td><td class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.promotions.edit', $promotion) }}">Sửa</a><form class="d-inline" method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" onsubmit="return confirm('Xóa chương trình này?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr>
        @empty <tr><td colspan="7" class="text-center py-5 text-muted">Chưa có chương trình khuyến mãi.</td></tr>@endforelse
        </tbody></table></div>
        @if($promotions->hasPages())<div class="p-3">{{ $promotions->links() }}</div>@endif
    </div>
@endsection
