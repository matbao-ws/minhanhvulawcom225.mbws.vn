@extends('admin.layouts.app')

@section('title', __('admin.vouchers.title'))

@section('content')
    <div class="card bg-primary-subtle shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3 d-flex flex-column gap-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-semibold mb-1">{{ __('admin.vouchers.title') }}</h4>
                    <nav class="py-2" style="--bs-breadcrumb-divider: '&gt;'" aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('admin.vouchers.title') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center justify-content-end mb-4">
        <a href="{{ route('admin.vouchers.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1 fs-4"></i>{{ __('admin.vouchers.create') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark mb-1">{{ __('catalog.actions.search') }}</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="{{ __('admin.vouchers.search_placeholder') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-dark mb-1">{{ __('admin.users.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="1" @selected((string) request('status') === '1')>{{ __('admin.vouchers.statuses.active') }}</option>
                        <option value="0" @selected((string) request('status') === '0')>{{ __('admin.vouchers.statuses.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="ti ti-search fs-5 me-1"></i> {{ __('catalog.actions.search') }}
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-nowrap">
                            <th class="ps-4">{{ __('admin.vouchers.fields.code') }}</th>
                            <th>{{ __('admin.vouchers.fields.name') }}</th>
                            <th>{{ __('admin.vouchers.fields.type') }}</th>
                            <th>{{ __('admin.vouchers.fields.value') }}</th>
                            <th>{{ __('admin.vouchers.fields.min_order_amount') }}</th>
                            <th>{{ __('admin.vouchers.fields.used_count') }}</th>
                            <th>{{ __('admin.vouchers.fields.end_date') }}</th>
                            <th>{{ __('admin.vouchers.fields.is_active') }}</th>
                            <th class="text-end pe-4">{{ __('catalog.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $voucher)
                            @php
                                $fallbackLocale = config('app.fallback_locale', config('app.locale', 'vi'));
                                $name = $voucher->getTranslation('name', app()->getLocale(), false) ?: $voucher->getTranslation('name', $fallbackLocale, false);
                                $description = $voucher->getTranslation('description', app()->getLocale(), false) ?: $voucher->getTranslation('description', $fallbackLocale, false);
                                
                                $isExpired = $voucher->end_date && $voucher->end_date->isPast();
                                $isNotStarted = $voucher->start_date && $voucher->start_date->isFuture();
                                $isLimitReached = $voucher->quantity !== null && $voucher->used_count >= $voucher->quantity;
                            @endphp
                            <tr class="text-nowrap">
                                <td class="ps-4">
                                    <span class="badge bg-primary text-white fw-bold font-monospace fs-3 px-3 py-2">{{ $voucher->code }}</span>
                                </td>
                                <td class="text-wrap" style="min-width: 200px;">
                                    <div class="fw-semibold text-dark fs-3">{{ $name }}</div>
                                    @if($description)
                                        <div class="text-dark small text-truncate" style="max-width: 250px;" title="{{ $description }}">{{ $description }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($voucher->type === 'percentage')
                                        <span class="badge bg-info-subtle text-info fw-semibold fs-2">{{ __('admin.vouchers.fields.percentage') }}</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success fw-semibold fs-2">{{ __('admin.vouchers.fields.fixed') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($voucher->type === 'percentage')
                                        <strong class="text-danger fs-4">{{ number_format($voucher->value, 0) }}%</strong>
                                    @else
                                        <strong class="text-danger fs-4">{{ number_format($voucher->value, 0) }} đ</strong>
                                    @endif
                                </td>
                                <td>
                                    <div class="small text-dark">{{ __('admin.vouchers.min_order') }}: <strong>{{ number_format($voucher->min_order_amount, 0) }} đ</strong></div>
                                    @if($voucher->type === 'percentage')
                                        <div class="small text-dark">{{ __('admin.vouchers.max_discount') }}: {{ $voucher->max_discount_amount ? number_format($voucher->max_discount_amount, 0) . ' đ' : __('admin.vouchers.no_limit') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="progress" style="height: 6px; width: 100px;">
                                        @php
                                            $percent = 0;
                                            if ($voucher->quantity > 0) {
                                                $percent = min(100, ($voucher->used_count / $voucher->quantity) * 100);
                                            }
                                        @endphp
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $voucher->used_count }}" aria-valuemin="0" aria-valuemax="{{ $voucher->quantity ?? 100 }}"></div>
                                    </div>
                                    <span class="fs-2 text-dark mt-1 d-block fw-semibold">
                                        {{ $voucher->used_count }} / {{ $voucher->quantity ?? '∞' }}
                                    </span>
                                </td>
                                <td>
                                    @if($voucher->start_date || $voucher->end_date)
                                        <div class="small text-dark">{{ __('admin.vouchers.from') }}: <span class="fw-semibold">{{ $voucher->start_date ? $voucher->start_date->format('d/m/Y H:i') : '...' }}</span></div>
                                        <div class="small text-dark">{{ __('admin.vouchers.to') }}: <span class="fw-semibold">{{ $voucher->end_date ? $voucher->end_date->format('d/m/Y H:i') : '...' }}</span></div>
                                    @else
                                        <span class="text-dark fs-2 fw-semibold">{{ __('admin.vouchers.no_limit') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$voucher->is_active)
                                        <span class="badge bg-danger-subtle text-danger fw-semibold fs-2">{{ __('admin.vouchers.statuses.inactive') }}</span>
                                    @elseif($isExpired)
                                        <span class="badge bg-secondary-subtle text-secondary fw-semibold fs-2">{{ __('admin.vouchers.statuses.expired') }}</span>
                                    @elseif($isNotStarted)
                                        <span class="badge bg-warning-subtle text-warning fw-semibold fs-2">{{ __('admin.vouchers.statuses.upcoming') }}</span>
                                    @elseif($isLimitReached)
                                        <span class="badge bg-secondary-subtle text-secondary fw-semibold fs-2">{{ __('admin.vouchers.statuses.out_of_stock') }}</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success fw-semibold fs-2">{{ __('admin.vouchers.statuses.running') }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="btn btn-sm btn-outline-primary" title="{{ __('catalog.actions.edit') }}">
                                            <i class="ti ti-edit fs-4"></i>
                                        </a>
                                        <form action="{{ route('admin.vouchers.destroy', $voucher) }}" method="POST" onsubmit="return confirm('{{ __('admin.vouchers.confirm_delete') }}');" style="display:inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('catalog.actions.delete') }}">
                                                <i class="ti ti-trash fs-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    {{ __('admin.vouchers.not_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($vouchers->hasPages())
                <div class="px-4 py-3 border-top">
                    {{ $vouchers->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
