<?php

namespace App\Services;

use App\Models\ProjectSetting;
use App\Support\LocalizedSiteSettings;
use App\Support\MediaUrl;

class SiteBranding
{
    private ?array $branding = null;

    /**
     * Return the current website identity with safe local fallbacks.
     *
     * This service is request-scoped, so settings saved in the admin area are
     * picked up on the next page load without leaving stale branding in workers.
     */
    public function current(): array
    {
        if ($this->branding !== null) {
            return $this->branding;
        }

        try {
            $settings = ProjectSetting::query()
                ->whereIn('setting_key', ['shop_name', LocalizedSiteSettings::SHOP_NAME_TRANSLATIONS, 'logo_url', 'favicon_url', 'contact'])
                ->pluck('setting_value', 'setting_key');

            $contact = $settings->get('contact');
            // The name in the language being read, the default language's name otherwise.
            $shopName = $this->textValue(LocalizedSiteSettings::shopName(
                $this->textValue($settings->get('shop_name')),
                $settings->get(LocalizedSiteSettings::SHOP_NAME_TRANSLATIONS),
                app()->getLocale(),
            ));
            $logoUrl = $settings->get('logo_url');
            $faviconUrl = $settings->get('favicon_url');
        } catch (\Throwable) {
            $contact = null;
            $shopName = null;
            $logoUrl = null;
            $faviconUrl = null;
        }

        return $this->branding = [
            'name' => $shopName ?: config('app.name', 'Laravel Ecommerce Core'),
            'logo_url' => $this->assetUrl($logoUrl, 'matbao-ws-logo.png'),
            'admin_logo_url' => asset('matbao-ws-logo.png'),
            'admin_favicon_url' => asset('admin-assets/images/logos/favicon.png'),
            'favicon_url' => $this->assetUrl($faviconUrl, 'admin-assets/images/logos/favicon.png'),
            // Address and branch names in the language being read (LocalizedSiteSettings).
            'contact' => LocalizedSiteSettings::contact($contact, app()->getLocale()),
        ];
    }

    private function textValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function assetUrl(mixed $value, string $fallback): string
    {
        return (string) MediaUrl::resolve($this->textValue($value), $fallback);
    }
}
