<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NotificationSettingController extends Controller
{
    /**
     * Display the notification settings page.
     */
    public function index()
    {
        $settingsRecord = ProjectSetting::query()->where('setting_key', 'notification_settings')->first();
        $settings = $settingsRecord ? $settingsRecord->setting_value : [];

        // Define default settings structures
        $defaults = [
            'zalo_oa' => [
                'enabled' => false,
                'app_id' => '',
                'secret_key' => '',
                'access_token' => '',
                'refresh_token' => '',
                'template_id' => '',
            ],
            'zalo_personal' => [
                'enabled' => false,
                'bot_token' => '',
                'chat_id' => '',
            ],
            'smtp' => [
                'enabled' => false,
                'host' => 'smtp.mailtrap.io',
                'port' => 2525,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'from_email' => 'noreply@yourdomain.com',
                'from_name' => 'Laravel Shop',
                'owner_email' => 'admin@yourdomain.com',
            ],
            'dashboard' => [
                'enabled' => true,
                'play_sound' => true,
                'auto_refresh' => true,
            ],
        ];

        // Merge saved settings with defaults to avoid missing keys in view
        $settings = array_merge($defaults, is_array($settings) ? $settings : []);
        foreach ($defaults as $section => $fields) {
            $settings[$section] = array_merge($fields, is_array($settings[$section] ?? null) ? $settings[$section] : []);
        }

        $hasActiveSubscription = false;
        $subscription = \App\Models\ProjectSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('package_id')
            ->where(function ($query) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', now()->toDateString());
            })
            ->first();

        if ($subscription) {
            $hasActiveSubscription = \App\Models\FeatureSetting::query()
                ->where('feature_code', 'zalo_oa')
                ->where('is_enabled', true)
                ->exists();
        }

        return view('admin.notification_settings.index', compact('settings', 'hasActiveSubscription'));
    }

    /**
     * Update the notification settings.
     */
    public function update(Request $request)
    {
        $hasActiveSubscription = false;
        $subscription = \App\Models\ProjectSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('package_id')
            ->where(function ($query) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', now()->toDateString());
            })
            ->first();

        if ($subscription) {
            $hasActiveSubscription = \App\Models\FeatureSetting::query()
                ->where('feature_code', 'zalo_oa')
                ->where('is_enabled', true)
                ->exists();
        }

        if (!$hasActiveSubscription) {
            $tryingToEnable = filter_var($request->input('zalo_oa.enabled'), FILTER_VALIDATE_BOOLEAN)
                || filter_var($request->input('zalo_personal.enabled'), FILTER_VALIDATE_BOOLEAN);

            if ($tryingToEnable) {
                $errorMessage = __('admin.notification_settings.no_package');
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage
                    ], 403);
                }

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $errorMessage);
            }
        }

        $validated = $request->validate([
            // Zalo OA
            'zalo_oa.enabled' => 'nullable|boolean',
            'zalo_oa.app_id' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:255',
            'zalo_oa.secret_key' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:255',
            'zalo_oa.access_token' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:1000',
            'zalo_oa.refresh_token' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:1000',
            'zalo_oa.template_id' => 'required_if:zalo_oa.enabled,1,true|nullable|string|max:255',

            // Zalo Personal
            'zalo_personal.enabled' => 'nullable|boolean',
            'zalo_personal.bot_token' => 'required_if:zalo_personal.enabled,1,true|nullable|string|max:1000',
            'zalo_personal.chat_id' => 'required_if:zalo_personal.enabled,1,true|nullable|string|max:255',

            // SMTP
            'smtp.enabled' => 'nullable|boolean',
            'smtp.host' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.port' => 'required_if:smtp.enabled,1,true|nullable|integer|min:1|max:65535',
            'smtp.encryption' => 'required_if:smtp.enabled,1,true|nullable|string|in:ssl,tls,none',
            'smtp.username' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.password' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.from_email' => 'required_if:smtp.enabled,1,true|nullable|email|max:255',
            'smtp.from_name' => 'required_if:smtp.enabled,1,true|nullable|string|max:255',
            'smtp.owner_email' => 'required_if:smtp.enabled,1,true|nullable|email|max:255',

            // Dashboard
            'dashboard.enabled' => 'nullable|boolean',
            'dashboard.play_sound' => 'nullable|boolean',
            'dashboard.auto_refresh' => 'nullable|boolean',
        ]);

        // Standardize switches to boolean (since form checkboxes only send values if checked)
        $settingsData = [
            'zalo_oa' => [
                'enabled' => (bool) ($validated['zalo_oa']['enabled'] ?? false),
                'app_id' => $validated['zalo_oa']['app_id'] ?? '',
                'secret_key' => $validated['zalo_oa']['secret_key'] ?? '',
                'access_token' => $validated['zalo_oa']['access_token'] ?? '',
                'refresh_token' => $validated['zalo_oa']['refresh_token'] ?? '',
                'template_id' => $validated['zalo_oa']['template_id'] ?? '',
            ],
            'zalo_personal' => [
                'enabled' => (bool) ($validated['zalo_personal']['enabled'] ?? false),
                'bot_token' => $validated['zalo_personal']['bot_token'] ?? '',
                'chat_id' => $validated['zalo_personal']['chat_id'] ?? '',
            ],
            'smtp' => [
                'enabled' => (bool) ($validated['smtp']['enabled'] ?? false),
                'host' => $validated['smtp']['host'] ?? '',
                'port' => isset($validated['smtp']['port']) ? (int) $validated['smtp']['port'] : null,
                'encryption' => $validated['smtp']['encryption'] ?? 'none',
                'username' => $validated['smtp']['username'] ?? '',
                'password' => $validated['smtp']['password'] ?? '',
                'from_email' => $validated['smtp']['from_email'] ?? '',
                'from_name' => $validated['smtp']['from_name'] ?? '',
                'owner_email' => $validated['smtp']['owner_email'] ?? '',
            ],
            'dashboard' => [
                'enabled' => (bool) ($validated['dashboard']['enabled'] ?? false),
                'play_sound' => (bool) ($validated['dashboard']['play_sound'] ?? false),
                'auto_refresh' => (bool) ($validated['dashboard']['auto_refresh'] ?? false),
            ],
        ];

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'notification_settings'],
            ['setting_value' => $settingsData]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('admin.notification_settings.save_success')
            ]);
        }

        return redirect()
            ->route('admin.notification-settings.index')
            ->with('success', __('admin.notification_settings.save_success'));
    }

    /**
     * Get Zalo Chat ID automatically using Zalo Bot getUpdates API.
     */
    public function getZaloChatId(Request $request)
    {
        $validated = $request->validate([
            'bot_token' => 'required|string|max:1000',
        ]);

        $botToken = preg_replace('/^zbot:/i', '', trim($validated['bot_token']));

        try {
            $response = Http::post("https://bot-api.zaloplatforms.com/bot{$botToken}/getUpdates", [
                'timeout' => 5,
            ]);

            if (!$response->successful() || !$response->json('ok')) {
                return response()->json([
                    'success' => false,
                    'message' => __('admin.notification_settings.zalo_personal.get_chat_id_error'),
                ], 400);
            }

            $updates = $response->json('result') ?? [];
            $chats = [];

            foreach ($updates as $update) {
                $chatId = data_get($update, 'message.chat.id');
                $displayName = data_get($update, 'message.from.display_name') ?? data_get($update, 'message.from.username') ?? 'Zalo User';

                if ($chatId) {
                    $chats[$chatId] = [
                        'chat_id' => $chatId,
                        'display_name' => $displayName,
                    ];
                }
            }

            $chatsList = array_values($chats);

            if (empty($chatsList)) {
                return response()->json([
                    'success' => true,
                    'chats' => [],
                    'message' => __('admin.notification_settings.zalo_personal.get_chat_id_empty')
                ]);
            }

            return response()->json([
                'success' => true,
                'chats' => $chatsList,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('admin.notification_settings.zalo_personal.get_chat_id_error') . ' ' . $e->getMessage(),
            ], 500);
        }
    }
}
