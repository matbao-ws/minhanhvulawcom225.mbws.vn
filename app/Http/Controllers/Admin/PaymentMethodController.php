<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of payment methods.
     */
    public function index()
    {
        $methods = PaymentMethod::all();
        return view('admin.payment_methods.index', compact('methods'));
    }

    /**
     * Show the form for creating a new custom payment method.
     */
    public function create()
    {
        return view('admin.payment_methods.create');
    }

    /**
     * Store a newly created custom payment method in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);

        $methodCode = 'PM_' . strtoupper(Str::random(8));

        PaymentMethod::create([
            'method_code' => $methodCode,
            'name' => $validated['name'],
            'type' => 'custom',
            'status' => 'inactive',
            'settings' => [
                'description' => $validated['description'],
            ],
        ]);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', __('admin.payment_methods.created'));
    }

    /**
     * Show the form for editing the specified custom payment method.
     */
    public function edit(string $locale, PaymentMethod $payment_method)
    {
        if ($payment_method->type !== 'custom') {
            return redirect()
                ->route('admin.payment-methods.index')
                ->with('error', __('admin.payment_methods.edit_custom_only'));
        }

        return view('admin.payment_methods.edit', ['method' => $payment_method]);
    }

    /**
     * Update the specified custom payment method in storage.
     */
    public function update(Request $request, string $locale, PaymentMethod $payment_method)
    {
        if ($payment_method->type !== 'custom') {
            return redirect()
                ->route('admin.payment-methods.index')
                ->with('error', __('admin.payment_methods.edit_custom_only'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);

        $payment_method->update([
            'name' => $validated['name'],
            'settings' => [
                'description' => $validated['description'],
            ]
        ]);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', __('admin.payment_methods.updated'));
    }

    /**
     * Remove the specified custom payment method from storage.
     */
    public function destroy(string $locale, PaymentMethod $payment_method)
    {
        if ($payment_method->type !== 'custom') {
            return redirect()
                ->route('admin.payment-methods.index')
                ->with('error', __('admin.payment_methods.delete_custom_only'));
        }

        $payment_method->delete();

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', __('admin.payment_methods.deleted'));
    }

    /**
     * Toggle status of a payment method.
     */
    public function toggleStatus(Request $request, string $locale, PaymentMethod $payment_method)
    {
        if (in_array($payment_method->method_code, ['vnpay', 'sepay', 'stripe']) && !\App\Models\Addon::isPurchased($payment_method->method_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Cổng thanh toán ' . strtoupper($payment_method->method_code) . ' chưa được mở khóa. Vui lòng mua Addon để sử dụng.'
            ]);
        }

        $newStatus = $payment_method->status === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active') {
            $settings = $payment_method->settings ?? [];

            if ($payment_method->method_code === 'vnpay') {
                if (empty($settings['tmn_code']) || empty($settings['hash_secret']) || empty($settings['api_url'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.payment_methods.config_required_vnpay')
                    ]);
                }
            } elseif ($payment_method->method_code === 'stripe') {
                if (empty($settings['secret_key']) || empty($settings['publishable_key'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.payment_methods.config_required_stripe')
                    ]);
                }
            } elseif ($payment_method->method_code === 'sepay') {
                if (empty($settings['api_key'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.payment_methods.config_required_sepay')
                    ]);
                }
            } elseif ($payment_method->method_code === 'bank_transfer') {
                if (empty($settings['bank_name']) || empty($settings['account_number']) || empty($settings['account_holder'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.payment_methods.config_required_bank')
                    ]);
                }
            }
        }

        $payment_method->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'message' => __('admin.payment_methods.toggle_success', ['name' => $payment_method->name])
        ]);
    }

    /**
     * Show the configuration settings form for a payment method.
     */
    public function settings(string $locale, PaymentMethod $payment_method)
    {
        if (in_array($payment_method->method_code, ['vnpay', 'sepay', 'stripe']) && !\App\Models\Addon::isPurchased($payment_method->method_code)) {
            return redirect()
                ->route('admin.payment-methods.index')
                ->with('error', 'Cổng thanh toán ' . strtoupper($payment_method->method_code) . ' chưa được mở khóa. Vui lòng mua Addon để sử dụng.');
        }
        return view('admin.payment_methods.settings', ['method' => $payment_method]);
    }

    /**
     * Update the integration settings for a payment method.
     */
    public function updateSettings(Request $request, string $locale, PaymentMethod $payment_method)
    {
        if (in_array($payment_method->method_code, ['vnpay', 'sepay', 'stripe']) && !\App\Models\Addon::isPurchased($payment_method->method_code)) {
            return redirect()
                ->route('admin.payment-methods.index')
                ->with('error', 'Cổng thanh toán ' . strtoupper($payment_method->method_code) . ' chưa được mở khóa. Vui lòng mua Addon để sử dụng.');
        }

        if ($payment_method->method_code === 'vnpay') {
            $validated = $request->validate([
                'tmn_code' => 'required|string|max:255',
                'hash_secret' => 'required|string|max:255',
                'api_url' => 'required|url|max:255',
            ]);

            $payment_method->update([
                'account_name' => $validated['tmn_code'] !== 'mock' ? 'VNPAY Account' : 'Mock Mode',
                'settings' => [
                    'tmn_code' => $validated['tmn_code'],
                    'hash_secret' => $validated['hash_secret'],
                    'api_url' => $validated['api_url'],
                ]
            ]);
        } elseif ($payment_method->method_code === 'stripe') {
            $validated = $request->validate([
                'publishable_key' => 'required|string|max:255',
                'secret_key' => 'required|string|max:255',
                'webhook_secret' => 'nullable|string|max:255',
            ]);

            $payment_method->update([
                'account_name' => $validated['publishable_key'] !== 'mock' ? 'Stripe Account' : 'Mock Mode',
                'settings' => [
                    'publishable_key' => $validated['publishable_key'],
                    'secret_key' => $validated['secret_key'],
                    'webhook_secret' => $validated['webhook_secret'] ?? '',
                ]
            ]);
        } elseif ($payment_method->method_code === 'sepay') {
            $validated = $request->validate([
                'api_key' => 'required|string|max:255',
                'webhook_token' => 'nullable|string|max:255',
            ]);

            $webhookToken = $validated['webhook_token'] ?: Str::random(32);

            $payment_method->update([
                'account_name' => $validated['api_key'] !== 'mock' ? 'Sepay Account' : 'Mock Mode',
                'settings' => [
                    'api_key' => $validated['api_key'],
                    'webhook_token' => $webhookToken,
                ]
            ]);
        } elseif ($payment_method->method_code === 'bank_transfer') {
            $validated = $request->validate([
                'bank_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:255',
                'account_holder' => 'required|string|max:255',
                'instructions' => 'nullable|string|max:1000',
            ]);

            $payment_method->update([
                'account_name' => $validated['bank_name'] . ' - ' . $validated['account_number'],
                'settings' => [
                    'bank_name' => $validated['bank_name'],
                    'account_number' => $validated['account_number'],
                    'account_holder' => $validated['account_holder'],
                    'instructions' => $validated['instructions'] ?? '',
                ]
            ]);
        } elseif ($payment_method->method_code === 'cod') {
            $validated = $request->validate([
                'description' => 'required|string|max:1000',
            ]);

            $payment_method->update([
                'settings' => [
                    'description' => $validated['description'],
                ]
            ]);
        }

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('success', __('admin.payment_methods.save_success'));
    }
}
