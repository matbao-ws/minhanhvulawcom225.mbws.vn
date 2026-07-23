<?php

namespace App\Jobs;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $orderId,
        public readonly string $recipient,
    ) {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['items.product', 'items.variant'])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('Skipped queued order status email because the order no longer exists.', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        Mail::to($this->recipient)->locale($order->locale ?: 'vi')->send(new OrderStatusMail($order));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Queued order status email failed.', [
            'order_id' => $this->orderId,
            'recipient' => $this->recipient,
            'message' => $exception->getMessage(),
        ]);
    }
}
