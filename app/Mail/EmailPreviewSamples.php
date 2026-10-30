<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Believable content for the email design preview (Quản trị › Giao diện email), so a shop sees its
 * real layout rather than empty boxes. Nothing here is ever saved: the order lives in memory only,
 * its items set as a loaded relation (OrderEmailData reads them from there).
 */
class EmailPreviewSamples
{
    /** A sample order in the state the given customer email is about. */
    public static function order(string $email): Order
    {
        $order = new Order([
            'order_number' => 'DH-24103018',
            'locale' => app()->getLocale(),
            'customer_name' => __('emails.sample.customer'),
            'customer_email' => 'minhanh@example.com',
            'customer_phone' => '0938 456 712',
            'shipping_address' => __('emails.sample.address'),
            'notes' => __('emails.sample.notes'),
            'payment_method' => 'cod',
            'shipping_carrier' => 'ghn',
            'status' => match ($email) {
                'order_preparing', 'order_shipping' => 'processing',
                'order_completed' => 'completed',
                'order_cancelled' => 'cancelled',
                default => 'pending',
            },
            'payment_status' => $email === 'order_cancelled' ? 'paid' : 'pending',
            'tracking_number' => in_array($email, ['order_shipping', 'order_completed'], true) ? 'GHN9KX4T7L' : null,
            'subtotal' => 1715000, 'discount' => 50000, 'promotion_discount' => 120000, 'shipping_fee' => 30000, 'grand_total' => 1575000,
        ]);
        $order->id = 0;
        $order->created_at = now();
        $order->setRelation('items', collect([
            new OrderItem(['product_name' => __('emails.sample.item_a'), 'variant_name' => __('emails.sample.variant_a'), 'price' => 185000, 'quantity' => 2, 'total' => 370000]),
            new OrderItem(['product_name' => __('emails.sample.item_b'), 'variant_name' => __('emails.sample.variant_b'), 'price' => 1250000, 'quantity' => 1, 'total' => 1250000]),
            new OrderItem(['product_name' => __('emails.sample.item_c'), 'price' => 95000, 'quantity' => 1, 'total' => 95000]),
        ]));

        return $order;
    }

    /** @return array{name: string, phone: string, email: string, message: string} */
    public static function contact(): array
    {
        return [
            'name' => __('emails.sample.contact_name'),
            'phone' => '0912 884 301',
            'email' => 'bao.tran@example.com',
            'message' => __('emails.sample.contact_message'),
        ];
    }
}
