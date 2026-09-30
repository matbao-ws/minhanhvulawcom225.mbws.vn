@extends('admin.layouts.app')

@section('title', 'Chương trình khuyến mãi')

@section('content')
    <x-admin.page-header title="Khuyến mãi & Flash Sale" subtitle="Giá tự động theo sản phẩm/SKU, không cần nhập mã.">
        <x-slot:actions>
            <x-admin.action-button :href="route('admin.promotions.create')" icon="plus">Tạo chương trình</x-admin.action-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2"><div class="col-md-6"><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Tìm tên chương trình"></div><div class="col-md-4"><select name="kind" class="form-select"><option value="">Tất cả loại</option><option value="automatic" @selected(request('kind') === 'automatic')>Khuyến mãi tự động</option><option value="flash_sale" @selected(request('kind') === 'flash_sale')>Flash Sale</option></select></div><div class="col-md-2"><x-admin.action-button type="submit" icon="filter" class="w-100">Lọc</x-admin.action-button></div></form>
        </div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th class="ps-4">Chương trình</th><th>Loại / mức giảm</th><th>Phạm vi</th><th>Thời gian</th><th>Lượt</th><th>Trạng thái</th><th class="text-end pe-4">Thao tác</th></tr></thead><tbody>
        @forelse($promotions as $promotion)
            @php $active = $promotion->is_active && (! $promotion->start_at || ! $promotion->start_at->isFuture()) && (! $promotion->end_at || ! $promotion->end_at->isPast()); @endphp
            <tr><td class="ps-4"><div class="fw-semibold">{{ $promotion->name }}</div><small class="text-muted">Ưu tiên {{ $promotion->priority }} · Tối thiểu {{ $promotion->min_quantity }} SP</small></td><td><span class="badge {{ $promotion->kind === 'flash_sale' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}">{{ $promotion->kind === 'flash_sale' ? 'Flash Sale' : 'Tự động' }}</span><div class="mt-1 fw-semibold">@if($promotion->discount_type === 'percentage') -{{ number_format($promotion->value, 0) }}%@elseif($promotion->discount_type === 'fixed_price') Giá {{ number_format($promotion->value) }}đ @else -{{ number_format($promotion->value) }}đ @endif</div></td><td>{{ $promotion->applies_to === 'all_products' ? 'Toàn bộ sản phẩm' : $promotion->targets_count.' mục tiêu' }}</td><td><small>{{ $promotion->start_at?->format('d/m H:i') ?? 'Ngay' }}<br>→ {{ $promotion->end_at?->format('d/m H:i') ?? 'Không hạn' }}</small></td><td>{{ $promotion->used_count }} / {{ $promotion->quantity_limit ?? '∞' }}</td><td><span class="badge {{ $active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $active ? 'Đang chạy' : 'Tạm dừng/Kết thúc' }}</span></td><td class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.promotions.edit', $promotion) }}">Sửa</a><form class="d-inline" method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" onsubmit="return confirm('Xóa chương trình này?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr>
        @empty <tr><td colspan="7">@include('admin.shared.empty-state', [
            'emptyImage' => asset('admin-assets/images/icons/emptydata.png'),
            'emptyMessage' => 'Chưa có chương trình khuyến mãi.',
        ])</td></tr>@endforelse
        </tbody></table></div>
        @if($promotions->hasPages())<div class="p-3">{{ $promotions->links() }}</div>@endif
    </div>
@endsection
