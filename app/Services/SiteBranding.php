<?php

namespace App\Services;

use App\Models\ProjectSetting;
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
                ->whereIn('setting_key', ['shop_name', 'logo_url', 'favicon_url', 'contact'])
                ->pluck('setting_value', 'setting_key');

            $contact = $settings->get('contact');
            $shopName = $this->textValue($settings->get('shop_name'));
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
            'favicon_url' => $this->assetUrl($faviconUrl, 'admin-assets/images/logos/favicon.png'),
            'contact' => is_array($contact) ? $contact : [],
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
