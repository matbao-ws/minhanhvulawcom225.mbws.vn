<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherRequest;
use App\Models\Voucher;
use App\Services\ActivityLogger;
use App\Services\LanguageRegistry;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    use HandlesBulkActions;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vouchers = Voucher::query()
            ->when(request('q'), function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('code', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%");
                });
            })
            ->when(request()->filled('status'), function ($query) {
                $query->where('is_active', request('status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.vouchers.index', compact('vouchers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $voucher = new Voucher([
            'is_active' => true,
            'min_order_amount' => 0.00,
            'type' => 'percentage',
        ]);

        return view('admin.vouchers.create', compact('voucher'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VoucherRequest $request)
    {
        $validated = $request->validated();
        Voucher::create([
            'code' => strtoupper($validated['code']),
            'name' => $this->translations($validated['name']),
            'description' => $this->translations($validated['description'] ?? []),
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order_amount' => $validated['min_order_amount'] ?? 0.00,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'per_user_limit' => $validated['per_user_limit'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', __('admin.vouchers.created'));
    }

    /**
     * Display the specified resource (redirects to edit).
     */
    public function show(string $locale, Voucher $voucher)
    {
        return redirect()->route('admin.vouchers.edit', $voucher);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $locale, Voucher $voucher)
    {
        return view('admin.vouchers.edit', compact('voucher'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VoucherRequest $request, string $locale, Voucher $voucher)
    {
        $validated = $request->validated();

        $voucher->update([
            'code' => strtoupper($validated['code']),
            'name' => [...$voucher->getTranslations('name'), ...$this->translations($validated['name'])],
            'description' => [...$voucher->getTranslations('description'), ...$this->translations($validated['description'] ?? [])],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order_amount' => $validated['min_order_amount'] ?? 0.00,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'per_user_limit' => $validated['per_user_limit'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', __('admin.vouchers.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $locale, Voucher $voucher)
    {
        if ($voucher->used_count > 0) {
            return back()->with('error', 'Không thể xóa mã giảm giá đã được sử dụng. Hãy tạm ẩn mã thay vì xóa.');
        }

        $voucher->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', __('admin.vouchers.deleted'));
    }

    public function bulk(Request $request, string $locale)
    {
        $validated = $this->validatedBulkAction($request, 'vouchers', 'vouchers');
        $ids = $validated['ids'];

        if ($validated['action'] === 'delete') {
            $usedCount = Voucher::query()->whereIn('id', $ids)->where('used_count', '>', 0)->count();
            if ($usedCount > 0) {
                return back()->with('error', "Không thể xóa {$usedCount} mã giảm giá đã được sử dụng. Hãy tạm ẩn các mã này.");
            }

            $deleted = Voucher::query()->whereIn('id', $ids)->delete();
            ActivityLogger::log('bulk_deleted', null, 'Xóa hàng loạt mã giảm giá', [
                'model' => Voucher::class,
                'ids' => $ids,
                'count' => $deleted,
            ]);

            return back()->with('success', 'Đã xóa '.$deleted.' mã giảm giá.');
        }

        $isActive = $validated['action'] === 'activate';
        $updated = Voucher::query()->whereIn('id', $ids)->update(['is_active' => $isActive]);
        ActivityLogger::log('bulk_status_changed', null, 'Cập nhật trạng thái hàng loạt mã giảm giá', [
            'model' => Voucher::class,
            'ids' => $ids,
            'count' => $updated,
            'is_active' => $isActive,
        ]);

        return back()->with('success', 'Đã '.($isActive ? 'kích hoạt' : 'tạm ẩn').' '.$updated.' mã giảm giá.');
    }

    private function translations(array $values): array
    {
        $languages = app(LanguageRegistry::class);

        return collect($values)->filter(fn ($value, $locale) => $languages->supports((string) $locale) && is_string($value) && trim($value) !== '')->map(fn (string $value) => trim($value))->all();
    }
}
