<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\EmailAppearance;
use App\Support\MediaUrl;

/**
 * Everything an order email shows, worked out once from the stored order (the template only prints):
 * which of the five customer emails it is, the shop's texts for it, the lines and totals as stored at
 * checkout, payment, carrier and tracking. Amounts are the order's own, never recalculated.
 */
class OrderEmailData
{
    /** Step on the progress bar (Đã đặt, Đang chuẩn bị, Đang giao, Đã giao) per customer email. */
    public const STEPS = ['order_placed' => 0, 'order_preparing' => 1, 'order_shipping' => 2, 'order_completed' => 3, 'order_cancelled' => null];

    private const CARRIERS = ['ghn' => 'Giao Hàng Nhanh', 'ghtk' => 'Giao Hàng Tiết Kiệm'];

    /** Public tracking pages; a carrier not listed shows the code only. */
    private const TRACKING_PAGES = ['ghn' => 'https://donhang.ghn.vn/?order_code=', 'ghtk' => 'https://i.ghtk.vn/'];

    /** The customer email for where the order stands now; null for a status that sends none. */
    public static function emailFor(Order $order): ?string
    {
        return match ($order->status) {
            'pending' => 'order_placed',
            'processing' => filled($order->tracking_number) ? 'order_shipping' : 'order_preparing',
            'completed' => 'order_completed',
            'cancelled' => 'order_cancelled',
            default => null,
        };
    }

    public static function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 0, ',', '.').' ₫';
    }

    /** @return array<string, string> the variables an order email's texts may use */
    public static function vars(Order $order, string $shop): array
    {
        $carrier = self::carrier($order);

        return [
            'ten_khach' => (string) $order->customer_name,
            'ma_don' => (string) $order->order_number,
            'tong_tien' => self::money($order->grand_total),
            'ten_shop' => $shop,
            'ma_van_don' => (string) ($order->tracking_number ?? ''),
            'don_vi_van_chuyen' => $carrier ?? '',
        ];
    }

    /**
     * @param  string  $email  an EmailAppearance::EMAILS order key, or owner_new_order
     * @return array<string, mixed>
     */
    public static function build(Order $order, string $email, EmailAppearance $appearance, ?string $locale = null): array
    {
        $look = $appearance->look();
        $settings = $appearance->settings();
        $vars = self::vars($order, $look['shop']);
        // Items as stored at checkout; a preview passes them in already (setRelation('items', …)).
        $items = $order->relationLoaded('items')
            ? $order->getRelation('items')
            : OrderItem::query()->where('order_id', $order->getKey())->orderBy('id')->get();
        $images = Product::query()->whereIn('id', $items->pluck('product_id')->filter()->unique()->all())->pluck('image_url', 'id');

        $lines = $items->map(fn (OrderItem $item) => [
            'name' => (string) $item->product_name,
            'variant' => $item->variant_name ?: null,
            'qty' => (int) $item->quantity,
            'total' => self::money($item->total ?? ((float) $item->price * (int) $item->quantity)),
            'image' => self::image($images[$item->product_id] ?? null),
        ])->values()->all();

        $totals = [['label' => __('emails.ui.subtotal', [], $locale), 'value' => self::money($order->subtotal), 'minus' => false]];
        if ((float) $order->discount > 0) {
            $totals[] = ['label' => __('emails.ui.voucher', [], $locale), 'value' => '−'.self::money($order->discount), 'minus' => true];
        }
        if ((float) $order->promotion_discount > 0) {
            $totals[] = ['label' => __('emails.ui.promotion', [], $locale), 'value' => '−'.self::money($order->promotion_discount), 'minus' => true];
        }
        $totals[] = ['label' => __('emails.ui.shipping_fee', [], $locale), 'value' => (float) $order->shipping_fee > 0 ? self::money($order->shipping_fee) : __('emails.ui.free', [], $locale), 'minus' => false];

        $notice = match (true) {
            $email === 'order_cancelled' && $order->payment_status === 'paid' => [
                'title' => __('emails.ui.refund_title', [], $locale),
                'text' => $settings['refund_note'] !== '' ? $settings['refund_note'] : __('emails.ui.refund_default', [], $locale),
                'background' => '#fef2f2', 'color' => '#991b1b',
            ],
            $email === 'order_completed' && $settings['return_policy'] !== '' => [
                'title' => null, 'text' => $settings['return_policy'], 'background' => '#f0fdf4', 'color' => '#166534',
            ],
            default => null,
        };
        if ($notice !== null && $notice['text'] === '') {
            $notice = null;
        }

        $carrierCode = strtolower((string) $order->shipping_carrier);
        $trackingPage = filled($order->tracking_number) && isset(self::TRACKING_PAGES[$carrierCode])
            ? self::TRACKING_PAGES[$carrierCode].rawurlencode((string) $order->tracking_number)
            : null;

        return [
            'email' => $email,
            'eyebrow' => __("emails.ui.eyebrow.{$email}", [], $locale),
            'tone' => match ($email) {
                'order_completed' => '#047857',
                'order_cancelled' => '#b91c1c',
                default => $look['accent'],
            },
            'subject' => $appearance->text($email, 'subject', $vars, $locale),
            'heading' => $appearance->text($email, 'heading', $vars, $locale),
            'intro' => $appearance->text($email, 'intro', $vars, $locale),
            'step' => self::STEPS[$email] ?? null,
            'number' => (string) $order->order_number,
            'placed_at' => $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
            'lines' => $lines,
            'images' => collect($lines)->contains(fn (array $line) => $line['image'] !== null),
            'totals' => $totals,
            'grand_total' => self::money($order->grand_total),
            'customer' => (string) $order->customer_name,
            'phone' => $order->customer_phone ?: null,
            'address' => (string) $order->shipping_address,
            'notes' => $order->notes ?: null,
            'payment' => self::payment($order),
            'carrier' => self::carrier($order),
            'tracking' => $order->tracking_number ?: null,
            'notice' => $notice,
            'cta' => $email === 'order_shipping' && $trackingPage !== null
                ? ['label' => __('emails.ui.track', [], $locale).' '.$order->tracking_number, 'href' => $trackingPage]
                : null,
            'preheader' => mb_substr($appearance->text($email, 'intro', $vars, $locale), 0, 140),
        ];
    }

    private static function carrier(Order $order): ?string
    {
        $code = strtolower(trim((string) $order->shipping_carrier));

        return $code === '' ? null : (self::CARRIERS[$code] ?? strtoupper($code));
    }

    private static function payment(Order $order): string
    {
        $code = (string) $order->payment_method;
        try {
            $name = PaymentMethod::query()->where('method_code', $code)->value('name');
        } catch (\Throwable) {
            $name = null;
        }

        return is_string($name) && $name !== '' ? $name : strtoupper($code);
    }

    /** The product's picture as an absolute https URL an inbox can load; none otherwise. */
    private static function image(mixed $stored): ?string
    {
        $url = MediaUrl::resolve(is_string($stored) ? $stored : null);

        return $url !== null && str_starts_with($url, 'https://') ? $url : null;
    }
}
