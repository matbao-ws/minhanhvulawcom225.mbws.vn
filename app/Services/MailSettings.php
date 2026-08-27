<?php

namespace App\Services;

use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Decides which SMTP credentials the application actually sends with.
 *
 * The admin screen wins whenever the shop has filled it in and switched it on;
 * the `.env` values are the fallback a fresh install ships with, so mail still
 * works before anyone opens the settings page. Resolution lives here rather than
 * at each send site — the previous arrangement configured the mailer inline in
 * one notification helper, which is why every other mail in the system (customer
 * order updates, contact form, password reset, invoices) quietly ignored the
 * admin configuration.
 */
class MailSettings
{
    public const CACHE_KEY = 'mail_settings.smtp';

    private const CACHE_TTL = 300;

    /** Without all of these the admin configuration cannot send anything. */
    private const REQUIRED_FIELDS = ['host', 'port', 'username', 'password', 'from_email'];

    /**
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $record = ProjectSetting::query()
                ->where('setting_key', 'notification_settings')
                ->first();

            return (array) data_get($record?->setting_value, 'smtp', []);
        });
    }

    public function usesAdminSettings(): bool
    {
        $smtp = $this->stored();

        if (! data_get($smtp, 'enabled')) {
            return false;
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (blank(data_get($smtp, $field))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Config overrides for the admin-configured mailer, or null to leave `.env` alone.
     *
     * @return array<string, mixed>|null
     */
    public function overrides(): ?array
    {
        if (! $this->usesAdminSettings()) {
            return null;
        }

        $smtp = $this->stored();
        $port = (int) data_get($smtp, 'port');

        return [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => data_get($smtp, 'host'),
            'mail.mailers.smtp.port' => $port,
            // Laravel 11+ builds the transport from `scheme`; `encryption` is dead
            // config it never reads. And MAIL_SCHEME from .env suppresses the
            // port-465 fallback in MailManager, so without this mapping an admin
            // who picks SSL gets a plaintext connection to an implicit-TLS port.
            'mail.mailers.smtp.scheme' => (data_get($smtp, 'encryption') === 'ssl' || $port === 465)
                ? 'smtps'
                : 'smtp',
            'mail.mailers.smtp.username' => data_get($smtp, 'username'),
            'mail.mailers.smtp.password' => data_get($smtp, 'password'),
            // A MAIL_URL left in .env outranks host/port and would send the admin's
            // credentials to the wrong server.
            'mail.mailers.smtp.url' => null,
            'mail.from.address' => data_get($smtp, 'from_email'),
            'mail.from.name' => data_get($smtp, 'from_name') ?: config('mail.from.name'),
        ];
    }

    /**
     * Point the mail manager at the effective configuration.
     *
     * Never throws: a fresh install runs migrations before `project_settings`
     * exists, and failing to read a setting must not stop the process that is
     * creating it. Mail simply stays on the `.env` configuration.
     */
    public function apply(): void
    {
        try {
            $overrides = $this->overrides();
        } catch (\Throwable) {
            return;
        }

        if ($overrides !== null) {
            config($overrides);
        }
    }

    /**
     * Recipient for store-owner notifications: the admin value, then MAIL_SELLER.
     */
    public function ownerEmail(): ?string
    {
        return data_get($this->stored(), 'owner_email') ?: config('mail.seller');
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
