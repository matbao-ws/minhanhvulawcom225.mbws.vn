<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * Shape of `order_number`: NUMBER_PREFIX followed by NUMBER_RANDOM_LENGTH
     * uppercase alphanumerics.
     *
     * Bank transfer content strips punctuation, so SePay's payment code is the
     * order number without its separator. Both directions of that mapping read
     * these constants — see App\Services\SePayService — so the format stays
     * defined in exactly one place.
     */
    public const NUMBER_PREFIX = 'ORD-';

    public const NUMBER_RANDOM_LENGTH = 10;

    public const SHIPPING_STATUSES = [
        'not_shipped',
        'shipping_pending',
        'shipping_created',
        'waiting_pickup',
        'picked_up',
        'in_transit',
        'delivered',
        'pickup_failed',
        'delivery_failed',
        'delayed',
        'returning',
        'returned',
        'compensated',
        'cancelled',
    ];

    private const GHTK_SHIPPING_STATUS_MAP = [
        -1 => 'cancelled',
        1 => 'waiting_pickup',
        2 => 'waiting_pickup',
        3 => 'picked_up',
        4 => 'in_transit',
        5 => 'delivered',
        6 => 'delivered',
        7 => 'pickup_failed',
        8 => 'delayed',
        9 => 'delivery_failed',
        10 => 'delayed',
        11 => 'returned',
        12 => 'waiting_pickup',
        13 => 'compensated',
        20 => 'returning',
        21 => 'returned',
    ];

    private const SHIPPING_STATUS_TRANSITIONS = [
        'not_shipped' => ['shipping_pending', 'shipping_created', 'waiting_pickup', 'picked_up', 'in_transit', 'delivered', 'pickup_failed', 'delivery_failed', 'delayed', 'returning', 'returned', 'compensated', 'cancelled'],
        'shipping_pending' => ['not_shipped', 'shipping_created', 'waiting_pickup', 'picked_up', 'in_transit', 'delivered', 'pickup_failed', 'delivery_failed', 'delayed', 'returning', 'returned', 'compensated', 'cancelled'],
        'shipping_created' => ['waiting_pickup', 'picked_up', 'in_transit', 'delivered', 'pickup_failed', 'delivery_failed', 'delayed', 'returning', 'returned', 'compensated', 'cancelled'],
        'waiting_pickup' => ['picked_up', 'in_transit', 'delivered', 'pickup_failed', 'delivery_failed', 'delayed', 'returning', 'returned', 'compensated', 'cancelled'],
        'picked_up' => ['in_transit', 'delivered', 'delivery_failed', 'delayed', 'returning', 'returned', 'compensated', 'cancelled'],
        'in_transit' => ['delivered', 'delivery_failed', 'delayed', 'returning', 'returned', 'compensated', 'cancelled'],
        'delayed' => ['waiting_pickup', 'picked_up', 'in_transit', 'delivered', 'delivery_failed', 'returning', 'returned', 'compensated', 'cancelled'],
        'delivery_failed' => ['in_transit', 'delivered', 'returning', 'returned', 'compensated', 'cancelled'],
        'returning' => ['returned', 'compensated'],
        'returned' => ['compensated'],
        'pickup_failed' => [],
        'delivered' => [],
        'compensated' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'user_id',
        'order_number',
        'locale',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'payment_method',
        'shipping_carrier',
        'shipping_fee',
        'carrier_shipping_fee',
        'tracking_number',
        'shipping_status',
        'shipping_status_updated_at',
        'payment_status',
        'status',
        'subtotal',
        'discount',
        'promotion_discount',
        'grand_total',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'carrier_shipping_fee' => 'decimal:2',
        'shipping_status_updated_at' => 'datetime',
    ];

    public static function shippingStatusKeys(): array
    {
        return self::SHIPPING_STATUSES;
    }

    public static function shippingStatusFromGhtk(?int $statusId): ?string
    {
        return self::GHTK_SHIPPING_STATUS_MAP[$statusId] ?? null;
    }

    public static function canTransitionShippingStatus(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::SHIPPING_STATUS_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public function shippingStatusLabel(): string
    {
        return __('admin.orders.shipping_statuses.'.($this->shipping_status ?: 'not_shipped'));
    }

    public function shippingStatusBadgeClass(): string
    {
        return match ($this->shipping_status ?: 'not_shipped') {
            'shipping_pending', 'shipping_created', 'waiting_pickup' => 'bg-info-subtle text-info',
            'picked_up', 'in_transit' => 'bg-primary-subtle text-primary',
            'delivered' => 'bg-success-subtle text-success',
            'delayed' => 'bg-warning-subtle text-warning',
            'pickup_failed', 'delivery_failed', 'cancelled' => 'bg-danger-subtle text-danger',
            'returning', 'returned' => 'bg-dark-subtle text-dark',
            'compensated' => 'bg-secondary-subtle text-secondary',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function historyEntries()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    public function refunds()
    {
        return $this->hasMany(OrderRefund::class)->latest();
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class)->latest('created_at');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }
}
