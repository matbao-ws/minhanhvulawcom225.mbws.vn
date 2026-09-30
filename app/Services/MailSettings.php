<?php

namespace App\Services;

use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Decides which SMTP credentials the application actually sends with.
 *
 * Three sources, in order:
 *   1. The admin screen, whenever the shop has filled it in and switched it on.
 *   2. The shared SMTP export (SMTP_EXPORT_URL) that every project deployed from
 *      this core points at, so rotating the provider or its key is done once on
 *      the tool instead of in each project's `.env`.
 *   3. The `.env` values, the last resort when neither of the above is usable.
 *
 * Resolution lives here rather than at each send site — the previous arrangement
 * configured the mailer inline in one notification helper, which is why every
 * other mail in the system (customer order updates, contact form, password reset,
 * invoices) quietly ignored the admin configuration.
 */
class MailSettings
{
    public const CACHE_KEY = 'mail_settings.smtp';

    public const REMOTE_CACHE_KEY = 'mail_settings.remote';

    /** Survives a failed refresh so an outage of the tool does not stop mail. */
    public const REMOTE_LAST_GOOD_KEY = 'mail_settings.remote_last_good';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_REMOTE = 'remote';

    public const SOURCE_ENV = 'env';

    private const CACHE_TTL = 300;

    private const REMOTE_TTL = 3600;

    /** Retry sooner after a failed fetch than after a successful one. */
    private const REMOTE_RETRY_TTL = 300;

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
     * Credentials from the shared SMTP export, or null when it is not configured
     * or has never answered.
     *
     * Cached encrypted: the cache store is often the database, and the export
     * hands out the provider password in clear.
     *
     * @return array{host: string, port: int, encryption: string, username: string, password: string, domain: ?string}|null
     */
    public function remote(): ?array
    {
        if (blank(config('mail.smtp_export_url'))) {
            return null;
        }

        $cached = Cache::get(self::REMOTE_CACHE_KEY);

        if ($cached === null) {
            $fetched = $this->fetchRemote();

            if ($fetched !== null) {
                $cached = Crypt::encrypt($fetched);
                Cache::put(self::REMOTE_CACHE_KEY, $cached, self::REMOTE_TTL);
                Cache::forever(self::REMOTE_LAST_GOOD_KEY, $cached);
            } else {
                $cached = Cache::get(self::REMOTE_LAST_GOOD_KEY, '');
                Cache::put(self::REMOTE_CACHE_KEY, $cached, self::REMOTE_RETRY_TTL);
            }
        }

        if ($cached === '') {
            return null;
        }

        try {
            return Crypt::decrypt($cached);
        } catch (\Throwable) {
            return null;
        }
    }

    public function source(): string
    {
        if ($this->usesAdminSettings()) {
            return self::SOURCE_ADMIN;
        }

        return $this->remote() !== null ? self::SOURCE_REMOTE : self::SOURCE_ENV;
    }

    /**
     * Config overrides for the effective mailer, or null to leave `.env` alone.
     *
     * @return array<string, mixed>|null
     */
    public function overrides(): ?array
    {
        if ($this->usesAdminSettings()) {
            $smtp = $this->stored();

            return $this->transport([
                'host' => data_get($smtp, 'host'),
                'port' => (int) data_get($smtp, 'port'),
                'encryption' => data_get($smtp, 'encryption'),
                'username' => data_get($smtp, 'username'),
                'password' => data_get($smtp, 'password'),
            ]) + [
                'mail.from.address' => data_get($smtp, 'from_email'),
                'mail.from.name' => data_get($smtp, 'from_name') ?: config('mail.from.name'),
            ];
        }

        $remote = $this->remote();

        if ($remote === null) {
            return null;
        }

        $overrides = $this->transport($remote) + [
            'mail.from.address' => $this->remoteFromAddress($remote['domain']),
        ];

        // Mail leaves from the shared sending domain, so a customer who hits reply
        // would otherwise write to a mailbox nobody reads.
        if ($owner = $this->ownerEmail()) {
            $overrides['mail.reply_to'] = ['address' => $owner, 'name' => config('mail.from.name')];
        }

        return $overrides;
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

    /**
     * Drop the cached export so the next mail fetches it again. The last good copy
     * is kept: it is what keeps mail flowing if the refetch fails.
     */
    public static function flushRemote(): void
    {
        Cache::forget(self::REMOTE_CACHE_KEY);
    }

    /**
     * @param  array{host: mixed, port: int, encryption: mixed, username: mixed, password: mixed}  $smtp
     * @return array<string, mixed>
     */
    private function transport(array $smtp): array
    {
        return [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => $smtp['port'],
            // Laravel 11+ builds the transport from `scheme`; `encryption` is dead
            // config it never reads. And MAIL_SCHEME from .env suppresses the
            // port-465 fallback in MailManager, so without this mapping an SSL
            // choice becomes a plaintext connection to an implicit-TLS port.
            // `smtp` on 587 still upgrades with STARTTLS.
            'mail.mailers.smtp.scheme' => ($smtp['encryption'] === 'ssl' || $smtp['port'] === 465)
                ? 'smtps'
                : 'smtp',
            'mail.mailers.smtp.username' => $smtp['username'],
            'mail.mailers.smtp.password' => $smtp['password'],
            // A MAIL_URL left in .env outranks host/port and would send these
            // credentials to the wrong server.
            'mail.mailers.smtp.url' => null,
        ];
    }

    /**
     * The provider only relays for its verified domain, so MAIL_FROM_ADDRESS is
     * kept when it already sits on that domain and replaced otherwise.
     */
    private function remoteFromAddress(?string $domain): mixed
    {
        $configured = config('mail.from.address');

        if (blank($domain)) {
            return $configured;
        }

        $configuredDomain = strtolower((string) substr(strrchr((string) $configured, '@') ?: '', 1));

        return $configuredDomain === strtolower($domain)
            ? $configured
            : config('mail.smtp_export_local_part', 'noreply').'@'.$domain;
    }

    /**
     * @return array{host: string, port: int, encryption: string, username: string, password: string, domain: ?string}|null
     */
    private function fetchRemote(): ?array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(4)
                ->get(config('mail.smtp_export_url'));
        } catch (\Throwable) {
            // Deliberately not logged: the URL carries the export token.
            return null;
        }

        $smtp = $response->successful() && $response->json('ok') === true
            ? (array) $response->json('smtp', [])
            : [];

        foreach (['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'] as $field) {
            if (blank($smtp[$field] ?? null)) {
                return null;
            }
        }

        return [
            'host' => (string) $smtp['smtp_host'],
            'port' => (int) $smtp['smtp_port'],
            'encryption' => strtolower((string) ($smtp['smtp_encryption'] ?? 'tls')),
            'username' => (string) $smtp['smtp_username'],
            'password' => (string) $smtp['smtp_password'],
            'domain' => filled($smtp['smtp_domain'] ?? null) ? strtolower((string) $smtp['smtp_domain']) : null,
        ];
    }
}
