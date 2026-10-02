<?php

namespace App\Services\Seo;

use App\Models\ProjectSetting;

/**
 * Code the shop owner pastes under Settings → Embed (verification meta tags,
 * tag managers, chat widgets). Printed as-is: only admins with settings.update
 * can write it, and running third-party markup is the whole point of the field.
 */
class SiteEmbeds
{
    private ?array $embeds = null;

    /** @return array{header: string, footer: string} */
    public function current(): array
    {
        if ($this->embeds !== null) {
            return $this->embeds;
        }

        try {
            $settings = ProjectSetting::query()
                ->whereIn('setting_key', ['embed_header', 'embed_footer'])
                ->pluck('setting_value', 'setting_key');
        } catch (\Throwable) {
            // A storefront page must render even before the settings table exists.
            $settings = collect();
        }

        return $this->embeds = [
            'header' => is_string($settings->get('embed_header')) ? trim($settings->get('embed_header')) : '',
            'footer' => is_string($settings->get('embed_footer')) ? trim($settings->get('embed_footer')) : '',
        ];
    }
}
