<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()
            ->when($request->query('q'), function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('invoice_number', 'like', "%{$keyword}%")
                        ->orWhere('package_name', 'like', "%{$keyword}%");
                });
            })
            ->when($request->query('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function show(string $locale, Invoice $invoice)
    {
        return view('admin.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function sendEmail(string $locale, Invoice $invoice)
    {
        \Illuminate\Support\Facades\Mail::to(auth()->user()->email)
            ->send(new \App\Mail\InvoiceMail($invoice));

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.invoices.invoice_sent_success')
            ]);
        }

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('success', __('admin.invoices.invoice_sent_success'));
    }
}
