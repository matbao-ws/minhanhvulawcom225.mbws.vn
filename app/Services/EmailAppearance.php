<?php

namespace App\Services;

use App\Models\ProjectSetting;
use App\Support\MediaUrl;

/**
 * How this shop's emails look and read (Quản trị › Giao diện email): layout, accent colour, logo,
 * footer, and per email whether it is sent and its own subject, heading and opening lines.
 *
 * Stored as one JSON setting (`email_appearance`); anything left empty falls back to the default in
 * lang/{locale}/emails.php. A shop's own wording is written in the default language, so it is used
 * for that language only: an order placed in another language keeps the translated defaults.
 * Texts are plain: `{ten_khach}`-style variables are replaced and the result is escaped in the view.
 */
class EmailAppearance
{
    public const KEY = 'email_appearance';

    public const LAYOUTS = ['classic', 'minimal', 'banner'];

    public const ALIGNS = ['center', 'left'];

    public const SOCIALS = ['facebook', 'zalo', 'instagram', 'tiktok', 'youtube'];

    public const DEFAULT_ACCENT = '#1f2937';

    /**
     * Fonts a shop can pick, all with full Vietnamese. Apple Mail, iOS Mail and Samsung Mail load the
     * Google font; Gmail, Outlook and Yahoo never load web fonts, so every choice ends in the system
     * fonts that set Vietnamese well there: SF Pro (Apple), Segoe UI (Windows), Roboto (Android).
     */
    public const FONTS = [
        'be_vietnam_pro' => ['label' => 'Be Vietnam Pro', 'google' => 'Be+Vietnam+Pro'],
        'inter' => ['label' => 'Inter', 'google' => 'Inter'],
        'nunito_sans' => ['label' => 'Nunito Sans', 'google' => 'Nunito+Sans'],
        'roboto' => ['label' => 'Roboto', 'google' => 'Roboto'],
        'system' => ['label' => null, 'google' => null],
    ];

    public const DEFAULT_FONT = 'be_vietnam_pro';

    private const SYSTEM_FONTS = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Helvetica,Arial,sans-serif";

    public const TEXT_FIELDS = ['subject', 'heading', 'intro'];

    /**
     * Every email the shop sends. `toggle` = the shop may switch it off; a password reset and the
     * settings test always go out. `vars` = what its texts may use.
     */
    public const EMAILS = [
        'order_placed' => ['audience' => 'customer', 'toggle' => true, 'vars' => ['ten_khach', 'ma_don', 'tong_tien', 'ten_shop']],
        'order_preparing' => ['audience' => 'customer', 'toggle' => true, 'vars' => ['ten_khach', 'ma_don', 'tong_tien', 'ten_shop']],
        'order_shipping' => ['audience' => 'customer', 'toggle' => true, 'vars' => ['ten_khach', 'ma_don', 'tong_tien', 'ten_shop', 'ma_van_don', 'don_vi_van_chuyen']],
        'order_completed' => ['audience' => 'customer', 'toggle' => true, 'vars' => ['ten_khach', 'ma_don', 'tong_tien', 'ten_shop']],
        'order_cancelled' => ['audience' => 'customer', 'toggle' => true, 'vars' => ['ten_khach', 'ma_don', 'tong_tien', 'ten_shop']],
        'owner_new_order' => ['audience' => 'owner', 'toggle' => true, 'vars' => ['ten_khach', 'ma_don', 'tong_tien', 'ten_shop']],
        'contact' => ['audience' => 'owner', 'toggle' => true, 'vars' => ['ten_nguoi_gui', 'ten_shop']],
        'reset_password' => ['audience' => 'admin', 'toggle' => false, 'vars' => ['ten_shop']],
        'settings_test' => ['audience' => 'owner', 'toggle' => false, 'vars' => ['ten_shop']],
    ];

    private const MAX = ['subject' => 150, 'heading' => 150, 'intro' => 1000, 'signature' => 500, 'policy' => 500];

    private ?array $settings = null;

    /** The stored settings, completed with defaults. */
    public function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        try {
            $stored = ProjectSetting::query()->where('setting_key', self::KEY)->value('setting_value');
        } catch (\Throwable) {
            $stored = null;
        }

        return $this->settings = $this->sanitize(is_array($stored) ? $stored : []);
    }

    /** A copy that renders with these settings instead of the stored ones (the admin preview). */
    public function using(array $settings): self
    {
        $copy = clone $this;
        $copy->settings = $this->sanitize($settings);

        return $copy;
    }

    public function save(array $settings): array
    {
        $clean = $this->sanitize($settings);
        ProjectSetting::query()->updateOrCreate(['setting_key' => self::KEY], ['setting_value' => $clean]);
        $this->settings = $clean;

        return $clean;
    }

    /**
     * The settings as they may be stored: known keys only, enums and colours checked, texts trimmed
     * and capped, links https only, images kept as portable paths.
     */
    public function sanitize(array $input): array
    {
        $text = fn (mixed $value, int $max) => mb_substr(trim(str_replace("\r\n", "\n", is_string($value) ? $value : '')), 0, $max);
        $accent = is_string($input['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['accent']) ? strtolower($input['accent']) : self::DEFAULT_ACCENT;
        $image = fn (mixed $value) => is_string($value) && trim($value) !== '' ? MediaUrl::toStorable(mb_substr(trim($value), 0, 500)) : null;

        $socials = [];
        foreach (self::SOCIALS as $network) {
            $url = trim((string) ($input['socials'][$network] ?? ''));
            if ($url !== '' && strlen($url) <= 300 && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL)) {
                $socials[$network] = $url;
            }
        }

        $emails = [];
        foreach (self::EMAILS as $key => $meta) {
            $given = is_array($input['emails'][$key] ?? null) ? $input['emails'][$key] : [];
            $emails[$key] = ['enabled' => $meta['toggle'] ? filter_var($given['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN) : true];
            foreach (self::TEXT_FIELDS as $field) {
                $emails[$key][$field] = $text($given[$field] ?? '', self::MAX[$field]);
            }
        }

        return [
            'layout' => in_array($input['layout'] ?? null, self::LAYOUTS, true) ? $input['layout'] : 'classic',
            'font' => array_key_exists((string) ($input['font'] ?? ''), self::FONTS) ? (string) $input['font'] : self::DEFAULT_FONT,
            'accent' => $accent,
            'logo' => $image($input['logo'] ?? null),
            'logo_align' => in_array($input['logo_align'] ?? null, self::ALIGNS, true) ? $input['logo_align'] : 'center',
            'banner' => $image($input['banner'] ?? null),
            'signature' => $text($input['signature'] ?? '', self::MAX['signature']),
            'return_policy' => $text($input['return_policy'] ?? '', self::MAX['policy']),
            'refund_note' => $text($input['refund_note'] ?? '', self::MAX['policy']),
            'socials' => $socials,
            'emails' => $emails,
        ];
    }

    public function enabled(string $email): bool
    {
        return (bool) ($this->settings()['emails'][$email]['enabled'] ?? true);
    }

    /**
     * One text of one email: the shop's own (in the default language) or the translated default,
     * with its variables filled in. Plain text; the view escapes it.
     *
     * @param  array<string, string>  $vars  e.g. ['ten_khach' => 'Minh Anh']
     */
    public function text(string $email, string $field, array $vars = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $own = $this->settings()['emails'][$email][$field] ?? '';
        $template = $own !== '' && $locale === $this->defaultLocale()
            ? $own
            : (string) trans("emails.appearance.{$email}.{$field}", [], $locale);

        return strtr($template, collect($vars)->mapWithKeys(fn ($value, $name) => ['{'.$name.'}' => (string) $value])->all());
    }

    /**
     * What every email's frame needs: layout, colour, logo, the shop's name and contact details,
     * signature and social links.
     *
     * @return array{layout: string, font_stack: string, font_url: ?string, accent: string, on_accent: string, logo_url: ?string, logo_align: string, banner_url: ?string, shop: string, address: ?string, phone: ?string, email: ?string, signature: string, socials: array<string, string>}
     */
    public function look(): array
    {
        $settings = $this->settings();
        $branding = app(SiteBranding::class)->current();
        $shopLogo = $this->ownShopLogo();

        // Text on accent-coloured buttons: dark on a light accent (yellow, pastel), else white.
        [$r, $g, $b] = array_map('hexdec', str_split(substr($settings['accent'], 1), 2));
        $onAccent = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.62 ? '#111827' : '#ffffff';

        $font = self::FONTS[$settings['font']];

        return [
            'layout' => $settings['layout'],
            'font_stack' => ($font['label'] !== null ? "'{$font['label']}'," : '').self::SYSTEM_FONTS,
            'font_url' => $font['google'] !== null ? 'https://fonts.googleapis.com/css2?family='.$font['google'].':wght@400;600&display=swap' : null,
            'accent' => $settings['accent'],
            'on_accent' => $onAccent,
            'logo_url' => $settings['logo'] !== null ? MediaUrl::resolve($settings['logo']) : $shopLogo,
            'logo_align' => $settings['logo_align'],
            'banner_url' => $settings['banner'] !== null ? MediaUrl::resolve($settings['banner']) : null,
            'shop' => (string) $branding['name'],
            'address' => data_get($branding, 'contact.address') ?: null,
            'phone' => data_get($branding, 'contact.phone') ?: null,
            'email' => data_get($branding, 'contact.email') ?: null,
            'signature' => $settings['signature'],
            'socials' => $settings['socials'],
        ];
    }

    /** The shop's uploaded logo; null while it keeps the default MatBao one (the name shows instead). */
    private function ownShopLogo(): ?string
    {
        try {
            $value = ProjectSetting::query()->where('setting_key', 'logo_url')->value('setting_value');
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && trim($value) !== '' ? MediaUrl::resolve(trim($value)) : null;
    }

    private function defaultLocale(): string
    {
        try {
            return app(LanguageRegistry::class)->defaultLocale();
        } catch (\Throwable) {
            return (string) config('app.locale', 'vi');
        }
    }
}
