<?php

namespace App\Mail;

use App\Services\EmailAppearance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A contact form message to the shop's notification addresses. Replying answers the customer
 * (Reply-To is their address when they gave one).
 */
class ContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name?: string, phone?: string, email?: ?string, message?: string}  $inquiry
     * @param  array<string, string>  $extra  the theme's other form fields (product, page, …)
     */
    public function __construct(public array $inquiry, public array $extra = []) {}

    public function envelope(): Envelope
    {
        $email = (string) ($this->inquiry['email'] ?? '');

        return new Envelope(
            subject: app(EmailAppearance::class)->text('contact', 'subject', $this->vars()),
            replyTo: filter_var($email, FILTER_VALIDATE_EMAIL) ? [new Address($email, (string) ($this->inquiry['name'] ?? ''))] : [],
        );
    }

    public function content(): Content
    {
        $appearance = app(EmailAppearance::class);
        $look = $appearance->look();
        $message = trim((string) ($this->inquiry['message'] ?? ''));
        $phone = trim((string) ($this->inquiry['phone'] ?? ''));

        return new Content(
            view: 'emails.contact-inquiry',
            with: [
                'look' => $look,
                'inquiry' => $this->inquiry,
                'extra' => $this->extra,
                'eyebrow' => __('emails.ui.eyebrow.contact'),
                'heading' => $appearance->text('contact', 'heading', $this->vars()),
                'intro' => $appearance->text('contact', 'intro', $this->vars()),
                'sentAt' => now()->format('d/m/Y H:i'),
                'tel' => $phone !== '' ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : null,
                'preheader' => trim($phone.' · '.mb_substr($message, 0, 120), ' ·'),
                'footer' => __('emails.ui.footer_owner', ['shop' => $look['shop']]),
            ],
        );
    }

    /** @return array<string, string> */
    private function vars(): array
    {
        return [
            'ten_nguoi_gui' => (string) ($this->inquiry['name'] ?? ''),
            'ten_shop' => app(EmailAppearance::class)->look()['shop'],
        ];
    }
}
