<?php

namespace App\Services\Orders;

/**
 * Everything that differs between the two ways an order is born — public
 * checkout and manual admin creation — so the shared money math and write
 * sequence in {@see OrderCreationService} can stay identical for both.
 */
class OrderDraft
{
    /**
     * @param  array<int, array{quote: array, data: array<string, mixed>}>  $lines  from OrderCreationService::priceLine()
     * @param  float  $extraDiscount  voucher discount (checkout) or manual discount (admin), on top of promotions
     * @param  string  $paymentSource  tag recorded on the pending payment transaction
     */
    public function __construct(
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $customerPhone,
        public readonly string $shippingAddress,
        public readonly string $paymentMethod,
        public readonly array $lines,
        public readonly float $extraDiscount = 0.0,
        public readonly float $shippingFee = 0.0,
        public readonly ?string $notes = null,
        public readonly ?int $userId = null,
        public readonly ?string $locale = null,
        public readonly string $paymentSource = 'public_checkout',
        public readonly string $historyNote = '',
        public readonly string $stockNote = '',
        public readonly ?int $changedBy = null,
    ) {}
}
