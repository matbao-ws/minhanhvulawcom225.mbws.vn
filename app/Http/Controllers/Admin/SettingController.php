<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectSetting;
use App\Services\CloudinaryService;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinaryService)
    {
    }

    /**
     * Display the settings page.
     */
    public function index()
    {
        $settings = ProjectSetting::query()->get()->pluck('setting_value', 'setting_key');

        // Also fetch notification settings for SMTP configurations
        $notificationSettings = ProjectSetting::query()->where('setting_key', 'notification_settings')->first();
        if ($notificationSettings) {
            $settings['notification_settings'] = $notificationSettings->setting_value;
        }

        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update the website settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'contact.phone' => 'nullable|string|max:20',
            'contact.email' => 'nullable|email|max:255',
            'contact.address' => 'nullable|string|max:500',
            'theme.primary_color' => 'nullable|string|max:7',
            'theme.layout' => 'nullable|string|in:default,compact,boxed',
            'seo.title' => 'nullable|string|max:255',
            'seo.description' => 'nullable|string|max:500',
            'social_links.facebook' => 'nullable|url|max:255',
            'social_links.youtube' => 'nullable|url|max:255',
            'social_links.instagram' => 'nullable|url|max:255',
            'social_links.tiktok' => 'nullable|url|max:255',
            // Embed code validation
            'embed_header' => 'nullable|string',
            'embed_footer' => 'nullable|string',
            // SMTP validation
            'smtp.enabled' => 'nullable|boolean',
            'smtp.host' => 'nullable|string|max:255',
            'smtp.port' => 'nullable|integer|min:1|max:65535',
            'smtp.encryption' => 'nullable|string|in:ssl,tls,none',
            'smtp.username' => 'nullable|string|max:255',
            'smtp.password' => 'nullable|string|max:255',
            'smtp.from_email' => 'nullable|email|max:255',
            'smtp.from_name' => 'nullable|string|max:255',
            'smtp.owner_email' => 'nullable|email|max:255',
        ]);

        // Update basic and nested JSON columns
        ProjectSetting::updateOrCreate(
            ['setting_key' => 'shop_name'],
            ['setting_value' => $validated['shop_name']]
        );

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'contact'],
            ['setting_value' => $validated['contact'] ?? []]
        );

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'theme'],
            ['setting_value' => $validated['theme'] ?? []]
        );

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'seo'],
            ['setting_value' => $validated['seo'] ?? []]
        );

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'social_links'],
            ['setting_value' => $validated['social_links'] ?? []]
        );

        // Update Embed codes
        ProjectSetting::updateOrCreate(
            ['setting_key' => 'embed_header'],
            ['setting_value' => $validated['embed_header'] ?? '']
        );

        ProjectSetting::updateOrCreate(
            ['setting_key' => 'embed_footer'],
            ['setting_value' => $validated['embed_footer'] ?? '']
        );

        // Update SMTP configuration in notification_settings key to preserve other keys
        $notificationSettingsRecord = ProjectSetting::firstOrNew(['setting_key' => 'notification_settings']);
        $notificationSettings = $notificationSettingsRecord->setting_value ?? [];
        $notificationSettings['smtp'] = [
            'enabled' => (bool) ($validated['smtp']['enabled'] ?? false),
            'host' => $validated['smtp']['host'] ?? '',
            'port' => isset($validated['smtp']['port']) ? (int) $validated['smtp']['port'] : null,
            'encryption' => $validated['smtp']['encryption'] ?? 'none',
            'username' => $validated['smtp']['username'] ?? '',
            'password' => $validated['smtp']['password'] ?? '',
            'from_email' => $validated['smtp']['from_email'] ?? '',
            'from_name' => $validated['smtp']['from_name'] ?? '',
            'owner_email' => $validated['smtp']['owner_email'] ?? '',
        ];
        $notificationSettingsRecord->setting_value = $notificationSettings;
        $notificationSettingsRecord->save();

        // Upload logo if uploaded
        if ($request->hasFile('logo')) {
            $logoUrl = $this->cloudinaryService->uploadFile($request->file('logo'), 'settings');
            ProjectSetting::updateOrCreate(
                ['setting_key' => 'logo_url'],
                ['setting_value' => $logoUrl]
            );
        }

        // Upload favicon if uploaded
        if ($request->hasFile('favicon')) {
            $faviconUrl = $this->cloudinaryService->uploadFile($request->file('favicon'), 'settings');
            ProjectSetting::updateOrCreate(
                ['setting_key' => 'favicon_url'],
                ['setting_value' => $faviconUrl]
            );
        }

        ActivityLogger::log('updated', null, 'Cập nhật cấu hình website', [
            'updated_keys' => array_values(array_filter([
                'shop_name',
                'contact',
                'theme',
                'seo',
                'social_links',
                'embed_header',
                'embed_footer',
                isset($validated['smtp']) ? 'smtp' : null,
                $request->hasFile('logo') ? 'logo_url' : null,
                $request->hasFile('favicon') ? 'favicon_url' : null,
            ])),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật cấu hình website thành công.'
            ]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Đã cập nhật cấu hình website thành công.');
    }
}
