<?php

namespace App\Console\Commands;

use App\Services\MailSettings;
use Illuminate\Console\Command;

class RefreshMailSettings extends Command
{
    protected $signature = 'mail:refresh-smtp';

    protected $description = 'Refetch the shared SMTP export and show which mail source is in effect';

    public function handle(MailSettings $settings): int
    {
        MailSettings::flush();
        MailSettings::flushRemote();

        $source = $settings->source();
        $overrides = $settings->overrides() ?? [];

        // Host and sender only: the password never goes to the terminal.
        $this->components->twoColumnDetail('Source', $source);
        $this->components->twoColumnDetail('Host', (string) ($overrides['mail.mailers.smtp.host'] ?? config('mail.mailers.smtp.host')));
        $this->components->twoColumnDetail('From', (string) ($overrides['mail.from.address'] ?? config('mail.from.address')));

        if (filled(config('mail.smtp_export_url')) && $settings->remote() === null) {
            $this->components->warn('SMTP_EXPORT_URL is set but the export could not be read.');
        }

        // Long-running workers resolved the mailer at boot and keep the old credentials.
        $this->call('queue:restart');

        return self::SUCCESS;
    }
}
