<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\ProjectSetting;
use App\Services\ActivityLogger;
use App\Services\CloudinaryService;
use App\Support\MediaUrl;
use App\Services\LanguageRegistry;
use App\Services\MultilingualSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(
        private readonly CloudinaryService $cloudinaryService,
        private readonly MultilingualSettings $multilingual,
        private readonly LanguageRegistry $languages,
    ) {}

    /**
     * Display the settings page.
     */
    public function index()
    {
        $settings = ProjectSetting::query()->get()->pluck('setting_value', 'setting_key');

        return view('admin.settings.index', [
            'settings' => $settings,
            'multilingualSettings' => $this->multilingual->get(),
            'contentLanguages' => auth()->user()?->isSuperAdmin()
                ? Language::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
        ]);
    }

    /**
     * Update the website settings.
     */
    public function update(Request $request)
    {
        if ($request->has('multilingual') && ! $request->user()?->isSuperAdmin()) {
            abort(403, 'Chỉ Superadmin được thay đổi cấu hình đa ngôn ngữ.');
        }

        $rules = [
            'shop_name' => 'required|string|max:255',
            'logo' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,ico,cur|max:5120',
            'favicon' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,ico,cur|max:5120',
            'logo_url' => 'nullable|string|max:255',
            'favicon_url' => 'nullable|string|max:255',
            'contact.phone' => 'nullable|string|max:20',
            'contact.email' => 'nullable|email|max:255',
            'contact.address' => 'nullable|string|max:500',
            'contact.google_map_url' => 'nullable|string|max:2000',
            'contact.branches' => 'nullable|array',
            'contact.branches.*.name' => 'nullable|string|max:255',
            'contact.branches.*.phone' => 'nullable|string|max:50',
            'contact.branches.*.email' => 'nullable|string|max:255',
            'contact.branches.*.address' => 'nullable|string|max:500',
            'contact.branches.*.google_map_url' => 'nullable|string|max:2000',
            'seo.title' => 'nullable|string|max:255',
            'seo.description' => 'nullable|string|max:500',
            'social_links.facebook' => 'nullable|url|max:255',
            'social_links.youtube' => 'nullable|url|max:255',
            'social_links.instagram' => 'nullable|url|max:255',
            'social_links.tiktok' => 'nullable|url|max:255',
            'social_links.custom' => 'nullable|array',
            'social_links.custom.*.icon' => 'nullable|string|max:100',
            'social_links.custom.*.title' => 'nullable|string|max:255',
            'social_links.custom.*.url' => 'nullable|string|max:1000',
            // Embed code validation
            'embed_header' => 'nullable|string',
            'embed_footer' => 'nullable|string',
        ];

        if ($request->user()?->isSuperAdmin()) {
            $rules = [
                ...$rules,
                'multilingual.enabled' => ['required', 'boolean'],
                'multilingual.mode' => ['required', Rule::in([
                    MultilingualSettings::MODE_MANUAL,
                    MultilingualSettings::MODE_GTRANSLATE,
                ])],
                'multilingual.gtranslate.source_locale' => ['nullable', 'string'],
                'multilingual.gtranslate.target_locales' => [
                    Rule::requiredIf(fn () => $request->boolean('multilingual.enabled')
                        && $request->input('multilingual.mode') === MultilingualSettings::MODE_GTRANSLATE),
                    'array',
                    'max:120',
                ],
                'multilingual.gtranslate.target_locales.*' => [
                    'string',
                    'distinct',
                ],
                'multilingual.gtranslate.widget_look' => ['required', Rule::in(['float', 'dropdown_with_flags', 'flags_dropdown', 'dropdown', 'flags', 'flags_name', 'flags_code', 'lang_names', 'lang_codes', 'globe', 'popup', 'popup_search', 'uswds'])],
                'multilingual.gtranslate.position' => ['required', Rule::in(['bottom_left', 'bottom_right', 'top_left', 'top_right', 'inline'])],
                'multilingual.gtranslate.detect_browser_language' => ['required', 'boolean'],
                'multilingual.gtranslate.native_language_names' => ['required', 'boolean'],
            ];
        }

        $validated = $request->validate($rules);

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

        // Upload logo if uploaded
        if ($request->hasFile('logo') || filled($validated['logo_url'] ?? null)) {
            $logoUrl = $request->hasFile('logo')
                ? $this->cloudinaryService->uploadFile($request->file('logo'), 'settings')
                : MediaUrl::toStorable($validated['logo_url']);
            ProjectSetting::updateOrCreate(
                ['setting_key' => 'logo_url'],
                ['setting_value' => $logoUrl]
            );
        }

        // Upload favicon if uploaded
        if ($request->hasFile('favicon') || filled($validated['favicon_url'] ?? null)) {
            $faviconUrl = $request->hasFile('favicon')
                ? $this->cloudinaryService->uploadFile($request->file('favicon'), 'settings')
                : MediaUrl::toStorable($validated['favicon_url']);
            ProjectSetting::updateOrCreate(
                ['setting_key' => 'favicon_url'],
                ['setting_value' => $faviconUrl]
            );
        }

        if ($request->user()?->isSuperAdmin()) {
            $this->multilingual->update($validated['multilingual']);
            $this->languages->forget();
        }

        ActivityLogger::log('updated', null, 'Cập nhật cấu hình website', [
            'updated_keys' => array_values(array_filter([
                'shop_name',
                'contact',
                'seo',
                'social_links',
                'embed_header',
                'embed_footer',
                $request->user()?->isSuperAdmin() ? 'multilingual' : null,
                ($request->hasFile('logo') || filled($validated['logo_url'] ?? null)) ? 'logo_url' : null,
                ($request->hasFile('favicon') || filled($validated['favicon_url'] ?? null)) ? 'favicon_url' : null,
            ])),
        ]);

        $redirectLocale = $this->languages->supportsAdmin(app()->getLocale())
            ? app()->getLocale()
            : $this->languages->defaultLocale();
        $redirectUrl = route('admin.settings.index', ['locale' => $redirectLocale]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật cấu hình website thành công.',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)
            ->with('success', 'Đã cập nhật cấu hình website thành công.');
    }
}
