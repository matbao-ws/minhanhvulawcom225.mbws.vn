<?php

namespace App\Services;

use App\Models\ProjectSetting;
use App\Services\AiGateway\GatewayClient;
use Illuminate\Support\Facades\Cache;

/**
 * Decides how the application actually sends mail.
 *
 * Three sources, in order:
 *   1. The shop's own SMTP from the admin screen, whenever it is filled in and on.
 *   2. The MatBao mail relay through the MatBaoAI-SEO gateway, authenticated by
 *      this website's project token. The relay holds the provider key and sends
 *      from Mắt Bão's verified domain; this website never sees a shared password,
 *      and the monthly quota is enforced on the gateway.
 *   3. The `.env` mailer, the last resort when neither is configured.
 *
 * Resolution lives here rather than at each send site — the previous arrangement
 * configured the mailer inline in one notification helper, which is why every
 * other mail in the system (customer order updates, contact form, password reset,
 * invoices) quietly ignored the admin configuration.
 */
class MailSettings
{
    public const CACHE_KEY = 'mail_settings.smtp';

    public const SOURCE_ADMIN = 'admin';

    /** The shared MatBao relay (the gateway). */
    public const SOURCE_REMOTE = 'remote';

    public const SOURCE_ENV = 'env';

    /** Name of the mailer in config/mail.php that talks to the gateway. */
    public const GATEWAY_MAILER = 'matbao';

    private const CACHE_TTL = 300;

    /** Without all of these the admin configuration cannot send anything. */
    private const REQUIRED_FIELDS = ['host', 'port', 'username', 'password', 'from_email'];

    public function __construct(private readonly GatewayClient $gateway) {}

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

    public function usesGateway(): bool
    {
        return ! $this->usesAdminSettings() && $this->gateway->configured();
    }

    public function source(): string
    {
        if ($this->usesAdminSettings()) {
            return self::SOURCE_ADMIN;
        }

        return $this->gateway->configured() ? self::SOURCE_REMOTE : self::SOURCE_ENV;
    }

    /**
     * The address the relay sends from, as the gateway reports it, for the
     * settings screen. Null when unknown.
     */
    public function sharedSender(): ?string
    {
        return $this->gateway->project()['mail_from'] ?? null;
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

            return $this->smtpTransport([
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

        if (! $this->gateway->configured()) {
            return null;
        }

        // The relay replaces the From address with Mắt Bão's verified one and keeps
        // the display name, so a reply would otherwise go to a mailbox nobody reads.
        $overrides = ['mail.default' => self::GATEWAY_MAILER];

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
     * Recipients for store-owner notifications: the admin value, then MAIL_SELLER.
     * Both may list several addresses separated by commas.
     *
     * @return list<string>
     */
    public function ownerEmails(): array
    {
        return self::splitEmails(data_get($this->stored(), 'owner_email') ?: config('mail.seller'));
    }

    /**
     * The first owner address: the single reply-to for mail sent through the relay.
     */
    public function ownerEmail(): ?string
    {
        return $this->ownerEmails()[0] ?? null;
    }

    /**
     * "a@x.vn, b@y.vn; c@z.vn" → unique, trimmed addresses (case-insensitive), in order.
     *
     * @return list<string>
     */
    public static function splitEmails(mixed $value): array
    {
        $emails = [];
        foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
            $emails[strtolower($email)] ??= $email;
        }

        return array_values($emails);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array{host: mixed, port: int, encryption: mixed, username: mixed, password: mixed}  $smtp
     * @return array<string, mixed>
     */
    private function smtpTransport(array $smtp): array
    {
        $overrides = ['mail.default' => 'smtp'];
        foreach (self::smtpMailer($smtp) as $key => $value) {
            $overrides['mail.mailers.smtp.'.$key] = $value;
        }

        return $overrides;
    }

    /**
     * Mailer configuration for an SMTP account, as Mail::build() takes it.
     *
     * @param  array{host: mixed, port: int, encryption: mixed, username: mixed, password: mixed}  $smtp
     * @return array<string, mixed>
     */
    public static function smtpMailer(array $smtp): array
    {
        return [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            // Laravel 11+ builds the transport from `scheme`; `encryption` is dead
            // config it never reads. And MAIL_SCHEME from .env suppresses the
            // port-465 fallback in MailManager, so without this mapping an SSL
            // choice becomes a plaintext connection to an implicit-TLS port.
            // `smtp` on 587 still upgrades with STARTTLS.
            'scheme' => ($smtp['encryption'] === 'ssl' || $smtp['port'] === 465)
                ? 'smtps'
                : 'smtp',
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            // A MAIL_URL left in .env outranks host/port and would send these
            // credentials to the wrong server.
            'url' => null,
        ];
    }
}
