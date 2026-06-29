<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShippingPartnerController extends Controller
{
    /**
     * Display a listing of shipping partners.
     */
    public function index()
    {
        $partners = ShippingPartner::all();
        return view('admin.shipping_partners.index', compact('partners'));
    }

    /**
     * Show the form for creating a new custom shipping partner.
     */
    public function create()
    {
        return view('admin.shipping_partners.create');
    }

    /**
     * Store a newly created custom shipping partner in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:255',
            'fee' => 'required|numeric|min:0',
        ]);

        $partnerCode = 'DTGH' . strtoupper(Str::random(8));

        ShippingPartner::create([
            'partner_code' => $partnerCode,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'account_name' => $validated['account_name'],
            'type' => 'custom',
            'status' => 'inactive',
            'settings' => [
                'fee' => (float) $validated['fee'],
            ],
            'logo_url' => 'self_delivery.png',
        ]);

        return redirect()
            ->route('admin.shipping-partners.index')
            ->with('success', __('admin.shipping_partners.created'));
    }

    /**
     * Show the form for editing the specified custom shipping partner.
     */
    public function edit(string $locale, ShippingPartner $shipping_partner)
    {
        if ($shipping_partner->type !== 'custom') {
            return redirect()
                ->route('admin.shipping-partners.index')
                ->with('error', __('admin.shipping_partners.edit_custom_only'));
        }

        return view('admin.shipping_partners.edit', ['partner' => $shipping_partner]);
    }

    /**
     * Update the specified custom shipping partner in storage.
     */
    public function update(Request $request, string $locale, ShippingPartner $shipping_partner)
    {
        if ($shipping_partner->type !== 'custom') {
            return redirect()
                ->route('admin.shipping-partners.index')
                ->with('error', __('admin.shipping_partners.edit_custom_only'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:255',
            'fee' => 'required|numeric|min:0',
        ]);

        $shipping_partner->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'account_name' => $validated['account_name'],
            'settings' => [
                'fee' => (float) $validated['fee'],
            ]
        ]);

        return redirect()
            ->route('admin.shipping-partners.index')
            ->with('success', __('admin.shipping_partners.updated'));
    }

    /**
     * Remove the specified custom shipping partner from storage.
     */
    public function destroy(string $locale, ShippingPartner $shipping_partner)
    {
        if ($shipping_partner->type !== 'custom') {
            return redirect()
                ->route('admin.shipping-partners.index')
                ->with('error', __('admin.shipping_partners.delete_custom_only'));
        }

        $shipping_partner->delete();

        return redirect()
            ->route('admin.shipping-partners.index')
            ->with('success', __('admin.shipping_partners.deleted'));
    }

    /**
     * Toggle status of a shipping partner.
     */
    public function toggleStatus(Request $request, string $locale, ShippingPartner $shipping_partner)
    {
        if ($shipping_partner->type !== 'custom' && !\App\Models\Addon::isPurchased('shipping_api')) {
            return response()->json([
                'success' => false,
                'message' => 'Tính năng kết nối API vận chuyển chưa được mở khóa. Vui lòng mua Addon để sử dụng.'
            ]);
        }
        $newStatus = $shipping_partner->status === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active') {
            $settings = $shipping_partner->settings ?? [];

            if ($shipping_partner->partner_code === 'DTGH000012') { // GHTK
                if (empty($settings['api_token']) || empty($settings['api_url'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.shipping_partners.config_required_ghtk')
                    ]);
                }
            } elseif ($shipping_partner->partner_code === 'DTGH000013') { // GHN
                if (empty($settings['api_token']) || empty($settings['api_url']) || empty($settings['shop_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.shipping_partners.config_required_ghn')
                    ]);
                }
            } elseif ($shipping_partner->partner_code === 'DTGH000014') { // J&T Express
                if (empty($settings['customerid']) || empty($settings['key']) || empty($settings['eccompanyid'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.shipping_partners.config_required_jt')
                    ]);
                }
            } elseif ($shipping_partner->partner_code === 'DTGH000015') { // SPX Express
                if (empty($settings['api_token']) || empty($settings['api_url'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.shipping_partners.config_required_spx')
                    ]);
                }
            } elseif ($shipping_partner->partner_code === 'DTGH000016') { // Viettel Post
                if (empty($settings['api_token']) || empty($settings['api_url'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.shipping_partners.config_required_viettel')
                    ]);
                }
            } elseif ($shipping_partner->type === 'custom') {
                if (!isset($settings['fee'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('admin.shipping_partners.config_required_custom')
                    ]);
                }
            }
        }

        $shipping_partner->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'message' => __('admin.shipping_partners.toggle_success', ['name' => $shipping_partner->name])
        ]);
    }

    /**
     * Show the integration settings form for a shipping partner.
     */
    public function settings(string $locale, ShippingPartner $shipping_partner)
    {
        if ($shipping_partner->type !== 'custom' && !\App\Models\Addon::isPurchased('shipping_api')) {
            return redirect()
                ->route('admin.shipping-partners.index')
                ->with('error', 'Tính năng kết nối API vận chuyển chưa được mở khóa. Vui lòng mua Addon để sử dụng.');
        }
        return view('admin.shipping_partners.settings', ['partner' => $shipping_partner]);
    }

    /**
     * Update the integration settings for a shipping partner.
     */
    public function updateSettings(Request $request, string $locale, ShippingPartner $shipping_partner)
    {
        if ($shipping_partner->type !== 'custom' && !\App\Models\Addon::isPurchased('shipping_api')) {
            return redirect()
                ->route('admin.shipping-partners.index')
                ->with('error', 'Tính năng kết nối API vận chuyển chưa được mở khóa. Vui lòng mua Addon để sử dụng.');
        }
        if ($shipping_partner->partner_code === 'DTGH000012') { // GHTK
            $validated = $request->validate([
                'api_token' => 'required|string|max:255',
                'api_url' => 'required|url|max:255',
                'webhook_token' => 'nullable|string|max:255',
            ]);

            $webhookToken = $validated['webhook_token'] ?: Str::random(32);

            $shipping_partner->update([
                'account_name' => $validated['api_token'] !== 'mock' ? 'GHTK Account' : 'Mock Mode',
                'settings' => [
                    'api_token' => $validated['api_token'],
                    'api_url' => $validated['api_url'],
                    'webhook_token' => $webhookToken,
                ]
            ]);
        } elseif ($shipping_partner->partner_code === 'DTGH000013') { // GHN
            $validated = $request->validate([
                'api_token' => 'required|string|max:255',
                'api_url' => 'required|url|max:255',
                'client_id' => 'nullable|string|max:255',
                'shop_id' => 'nullable|string|max:255',
            ]);

            $shipping_partner->update([
                'account_name' => $validated['api_token'] ? 'GHN Account' : null,
                'settings' => [
                    'api_token' => $validated['api_token'],
                    'api_url' => $validated['api_url'],
                    'client_id' => $validated['client_id'] ?? '',
                    'shop_id' => $validated['shop_id'] ?? '',
                ]
            ]);
        } elseif ($shipping_partner->partner_code === 'DTGH000014') { // J&T
            $validated = $request->validate([
                'customerid' => 'required|string|max:255',
                'key' => 'required|string|max:255',
                'eccompanyid' => 'required|string|max:255',
            ]);

            $shipping_partner->update([
                'account_name' => $validated['customerid'] ? 'J&T Account' : null,
                'settings' => [
                    'customerid' => $validated['customerid'],
                    'key' => $validated['key'],
                    'eccompanyid' => $validated['eccompanyid'],
                ]
            ]);
        } elseif ($shipping_partner->partner_code === 'DTGH000015') { // SPX Express
            $validated = $request->validate([
                'api_token' => 'required|string|max:255',
                'api_url' => 'required|url|max:255',
                'partner_id' => 'nullable|string|max:255',
            ]);

            $shipping_partner->update([
                'account_name' => $validated['api_token'] ? 'SPX Account' : null,
                'settings' => [
                    'api_token' => $validated['api_token'],
                    'api_url' => $validated['api_url'],
                    'partner_id' => $validated['partner_id'] ?? '',
                ]
            ]);
        } elseif ($shipping_partner->partner_code === 'DTGH000016') { // Viettel Post
            $validated = $request->validate([
                'api_token' => 'required|string|max:255',
                'api_url' => 'required|url|max:255',
                'username' => 'nullable|string|max:255',
            ]);

            $shipping_partner->update([
                'account_name' => $validated['username'] ?: 'Viettel Post Account',
                'settings' => [
                    'api_token' => $validated['api_token'],
                    'api_url' => $validated['api_url'],
                    'username' => $validated['username'] ?? '',
                ]
            ]);
        } elseif ($shipping_partner->type === 'custom') { // Custom
            $validated = $request->validate([
                'fee' => 'required|numeric|min:0',
            ]);

            $shipping_partner->update([
                'settings' => [
                    'fee' => (float) $validated['fee'],
                ]
            ]);
        }

        return redirect()
            ->route('admin.shipping-partners.index')
            ->with('success', __('admin.shipping_partners.save_success'));
    }
}
