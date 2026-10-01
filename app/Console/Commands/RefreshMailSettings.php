<?php

namespace App\Console\Commands;

use App\Services\AiGateway\GatewayClient;
use App\Services\MailSettings;
use App\Services\Packages\PackageLimits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshMailSettings extends Command
{
    protected $signature = 'mail:refresh-smtp';

    protected $description = 'Re-read the mail configuration and show which mail source is in effect';

    public function handle(MailSettings $settings): int
    {
        MailSettings::flush();
        GatewayClient::forgetProject();
        Cache::forget(PackageLimits::EMAIL_USAGE_CACHE_KEY);

        $source = $settings->source();

        // Host and sender only: no password or token goes to the terminal.
        $this->components->twoColumnDetail('Source', $source);

        if ($source === MailSettings::SOURCE_ADMIN) {
            $overrides = $settings->overrides() ?? [];
            $this->components->twoColumnDetail('Host', (string) ($overrides['mail.mailers.smtp.host'] ?? ''));
            $this->components->twoColumnDetail('From', (string) ($overrides['mail.from.address'] ?? ''));
        } elseif ($source === MailSettings::SOURCE_REMOTE) {
            $this->components->twoColumnDetail('Relay', (string) config('ai_gateway.url'));
            $this->components->twoColumnDetail('From', $settings->sharedSender() ?? 'unknown (gateway unreachable?)');
        } else {
            $this->components->twoColumnDetail('Mailer', (string) config('mail.default'));
            $this->components->twoColumnDetail('From', (string) config('mail.from.address'));
        }

        // Long-running workers resolved the mailer at boot and keep the old one.
        $this->call('queue:restart');

        return self::SUCCESS;
    }
}
