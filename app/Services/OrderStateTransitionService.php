<?php

namespace App\Services;

use DomainException;

class OrderStateTransitionService
{
    private const ORDER_TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    private const PAYMENT_TRANSITIONS = [
        'pending' => ['paid', 'failed'],
        'paid' => ['partially_refunded', 'refunded'],
        'failed' => ['pending', 'paid'],
        'partially_refunded' => ['refunded'],
        'refunded' => [],
    ];

    public function assertCanTransition(
        string $fromStatus,
        string $toStatus,
        string $fromPaymentStatus,
        string $toPaymentStatus,
    ): void {
        if (! $this->canTransition($fromStatus, $toStatus, self::ORDER_TRANSITIONS)) {
            throw new DomainException("Không thể chuyển trạng thái đơn hàng từ {$fromStatus} sang {$toStatus}.");
        }

        if (! $this->canTransition($fromPaymentStatus, $toPaymentStatus, self::PAYMENT_TRANSITIONS)) {
            throw new DomainException("Không thể chuyển trạng thái thanh toán từ {$fromPaymentStatus} sang {$toPaymentStatus}.");
        }
    }

    public function canTransition(
        string $from,
        string $to,
        array $transitions,
    ): bool {
        if ($from === $to) {
            return true;
        }

        return in_array($to, $transitions[$from] ?? [], true);
    }

    public function canTransitionOrderStatus(string $fromStatus, string $toStatus): bool
    {
        return $this->canTransition($fromStatus, $toStatus, self::ORDER_TRANSITIONS);
    }

    public function canTransitionPaymentStatus(string $fromPaymentStatus, string $toPaymentStatus): bool
    {
        return $this->canTransition($fromPaymentStatus, $toPaymentStatus, self::PAYMENT_TRANSITIONS);
    }
}
