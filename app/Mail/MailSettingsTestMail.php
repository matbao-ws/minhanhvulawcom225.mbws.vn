<?php

namespace App\Mail;

use App\Services\EmailAppearance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The "Gửi email thử" button on the notification settings page: in the shop's email design, with
 * the route it went out by (never the SMTP password), so the admin can check it against the form.
 */
class MailSettingsTestMail extends Mailable
{
    use Queueable;

    /**
     * $route['via'] is own_smtp, relay or default.
     *
     * @param  array{via?: string, host?: ?string, port?: int|string|null, encryption?: ?string, to?: list<string>}  $route
     */
    public function __construct(
        public string $shop,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public array $route = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromEmail ? new Address($this->fromEmail, $this->fromName) : null,
            subject: app(EmailAppearance::class)->text('settings_test', 'subject', ['ten_shop' => $this->shop]),
        );
    }

    public function content(): Content
    {
        $appearance = app(EmailAppearance::class);
        $look = $appearance->look();
        $vars = ['ten_shop' => $this->shop];
        $via = $this->route['via'] ?? 'default';

        $rows = [[__('emails.ui.test_via'), __('emails.ui.'.match ($via) {
            'own_smtp' => 'test_own_smtp',
            'relay' => 'test_relay',
            default => 'test_default',
        }), false]];
        if ($via === 'own_smtp' && filled($this->route['host'] ?? null)) {
            $rows[] = [__('emails.ui.test_host'), $this->route['host'].(filled($this->route['port'] ?? null) ? ' : '.$this->route['port'] : ''), true];
            $rows[] = [__('emails.ui.test_encryption'), strtoupper((string) ($this->route['encryption'] ?? '')) ?: '—', false];
        }
        if ($this->fromEmail) {
            $rows[] = [__('emails.ui.test_from'), trim(($this->fromName ? $this->fromName.' ' : '').'<'.$this->fromEmail.'>'), false];
        }
        if (($this->route['to'] ?? []) !== []) {
            $rows[] = [__('emails.ui.test_to'), implode(', ', $this->route['to']), false];
        }

        return new Content(
            view: 'emails.settings-test',
            with: [
                'look' => $look,
                'heading' => $appearance->text('settings_test', 'heading', $vars),
                'intro' => $appearance->text('settings_test', 'intro', $vars),
                'rows' => $rows,
                'sentAt' => now()->format('H:i d/m/Y'),
                'preheader' => $appearance->text('settings_test', 'heading', $vars),
                'footer' => __('emails.ui.footer_owner', ['shop' => $look['shop']]),
            ],
        );
    }
}
