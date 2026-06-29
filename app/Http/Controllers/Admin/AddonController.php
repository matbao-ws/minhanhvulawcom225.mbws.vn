<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddonController extends Controller
{
    /**
     * Display the Addons Store.
     */
    public function index(Request $request)
    {
        $addons = Addon::all();
        $invoices = Invoice::with('addon')->whereNotNull('addon_code')->latest()->paginate(10);
        
        // Fetch bank/sepay info for QR code mock display (e.g. from Sepay settings)
        $sepayMethod = PaymentMethod::where('method_code', 'sepay')->first();
        $sepaySettings = $sepayMethod ? $sepayMethod->settings : [];
        $bankName = 'Vietinbank';
        $accountNum = '113366668888';
        $accountHolder = 'CONG TY PHAN MEM CO';

        $activeTab = $request->query('tab', $request->has('page') ? 'invoices' : 'addons');

        return view('admin.addons.index', compact('addons', 'invoices', 'bankName', 'accountNum', 'accountHolder', 'activeTab'));
    }

    /**
     * Display the addon invoice history.
     */
    public function invoices(Request $request)
    {
        $addons = Addon::all();
        $invoices = Invoice::with('addon')->whereNotNull('addon_code')->latest()->paginate(10);
        
        // Fetch bank/sepay info for QR code mock display (e.g. from Sepay settings)
        $sepayMethod = PaymentMethod::where('method_code', 'sepay')->first();
        $sepaySettings = $sepayMethod ? $sepayMethod->settings : [];
        $bankName = 'Vietinbank';
        $accountNum = '113366668888';
        $accountHolder = 'CONG TY PHAN MEM CO';

        $activeTab = 'invoices';

        return view('admin.addons.index', compact('addons', 'invoices', 'bankName', 'accountNum', 'accountHolder', 'activeTab'));
    }

    /**
     * Create an invoice for purchasing an addon.
     */
    public function checkout(Request $request, string $locale, Addon $addon)
    {
        if ($addon->is_purchased) {
            return response()->json([
                'success' => false,
                'message' => 'Addon này đã được mua và mở khóa rồi.'
            ], 422);
        }

        // Create pending invoice
        $invoiceNumber = 'INV-ADDON-' . strtoupper(Str::random(8));
        
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'package_name' => 'Addon: ' . $addon->name,
            'amount' => $addon->price,
            'status' => 'pending',
            'billing_date' => now(),
            'due_date' => now()->addDays(7),
            'payment_method' => 'sepay',
            'addon_code' => $addon->code,
        ]);

        // Generate VietQR Link
        // Format: https://api.vietqr.io/image/vietinbank-113366668888-compact2.jpg?amount=...&addInfo=...
        $bankNum = '113366668888';
        $bankBin = 'vietinbank';
        $qrCodeUrl = "https://api.vietqr.io/image/{$bankBin}-{$bankNum}-compact2.jpg?amount=" . (int)$invoice->amount . "&addInfo=" . urlencode("ADDONPAID {$invoiceNumber}");

        return response()->json([
            'success' => true,
            'invoice' => $invoice,
            'qr_code_url' => $qrCodeUrl,
            'transfer_syntax' => "ADDONPAID {$invoiceNumber}",
        ]);
    }

    /**
     * Check payment status of an invoice.
     */
    public function checkInvoiceStatus(string $locale, Invoice $invoice)
    {
        return response()->json([
            'success' => true,
            'payment_status' => $invoice->status,
            'is_purchased' => $invoice->addon ? $invoice->addon->is_purchased : false,
        ]);
    }

    /**
     * Super Admin Addon management screen.
     */
    public function manage()
    {
        $addons = Addon::all();
        return view('admin.addons.manage', compact('addons'));
    }

    /**
     * Super Admin update addon pricing and info.
     */
    public function updateAddon(Request $request, string $locale, Addon $addon)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        $addon->update([
            'price' => $validated['price'],
            'description' => $validated['description'] ?? $addon->description,
        ]);

        return redirect()
            ->route('admin.addons.manage')
            ->with('success', 'Đã cập nhật cấu hình addon ' . $addon->name . ' thành công.');
    }
}
