<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The "Gửi email thử" button on the notification settings page.
 */
class MailSettingsTestMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $shop,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromEmail ? new Address($this->fromEmail, $this->fromName) : null,
            subject: __('admin.notification_settings.smtp.test_subject', ['shop' => $this->shop]),
        );
    }

    public function content(): Content
    {
        $body = __('admin.notification_settings.smtp.test_body', ['shop' => $this->shop, 'time' => now()->format('H:i d/m/Y')]);

        return new Content(htmlString: nl2br(e($body)));
    }
}
