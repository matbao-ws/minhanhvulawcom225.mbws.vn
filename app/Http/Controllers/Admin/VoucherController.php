<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherRequest;
use App\Models\Voucher;

class VoucherController extends Controller
{
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
        $locale = app()->getLocale() ?: 'vi';

        Voucher::create([
            'code' => strtoupper($validated['code']),
            'name' => [$locale => $validated['name']],
            'description' => isset($validated['description']) ? [$locale => $validated['description']] : null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order_amount' => $validated['min_order_amount'] ?? 0.00,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
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

        $nameTranslations = $voucher->getTranslations('name');
        $nameTranslations[$locale] = $validated['name'];

        $descTranslations = $voucher->getTranslations('description');
        if (isset($validated['description'])) {
            $descTranslations[$locale] = $validated['description'];
        } else {
            unset($descTranslations[$locale]);
        }

        $voucher->update([
            'code' => strtoupper($validated['code']),
            'name' => $nameTranslations,
            'description' => $descTranslations,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order_amount' => $validated['min_order_amount'] ?? 0.00,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
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
        $voucher->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', __('admin.vouchers.deleted'));
    }
}
