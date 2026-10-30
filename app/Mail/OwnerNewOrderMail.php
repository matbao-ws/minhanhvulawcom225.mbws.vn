<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\EmailAppearance;
use App\Services\LanguageRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Đơn mới" to the shop's notification addresses: the amount in the subject, the customer to call,
 * what was ordered, and a button to the order in the admin. Replaces sending the owner a copy of the
 * customer's own email.
 */
class OwnerNewOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?array $data = null;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->data()['subject']);
    }

    public function content(): Content
    {
        $look = app(EmailAppearance::class)->look();

        return new Content(
            view: 'emails.order-new-owner',
            with: [
                'look' => $look,
                'mail' => $this->data(),
                'preheader' => $this->data()['customer'].($this->data()['phone'] ? ' · '.$this->data()['phone'] : '').' · '.$this->data()['grand_total'].' · '.$this->data()['payment'],
                'footer' => __('emails.ui.footer_owner', ['shop' => $look['shop']]),
                'adminUrl' => route('admin.orders.show', ['locale' => app(LanguageRegistry::class)->adminLocale(null), 'order' => $this->order->id]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return $this->data ??= OrderEmailData::build($this->order, 'owner_new_order', app(EmailAppearance::class));
    }
}
