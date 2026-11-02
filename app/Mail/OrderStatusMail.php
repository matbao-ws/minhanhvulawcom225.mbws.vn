<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\EmailAppearance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's email for where their order stands (placed, preparing, shipping, delivered,
 * cancelled), worded and styled as the shop set in Quản trị › Giao diện email.
 */
class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?array $data = null;

    /** @param  string|null  $email  which customer email; null = the one for the order's status */
    public function __construct(public Order $order, public ?string $email = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->data()['subject']);
    }

    public function content(): Content
    {
        $appearance = app(EmailAppearance::class);
        $look = $appearance->look();

        return new Content(
            view: 'emails.order-status',
            with: [
                'look' => $look,
                'mail' => $this->data(),
                'preheader' => $this->data()['preheader'],
                'footer' => __('emails.ui.footer_customer', ['shop' => $look['shop']]),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return $this->data ??= OrderEmailData::build(
            $this->order,
            $this->email ?? OrderEmailData::emailFor($this->order) ?? 'order_placed',
            app(EmailAppearance::class),
        );
    }
}
