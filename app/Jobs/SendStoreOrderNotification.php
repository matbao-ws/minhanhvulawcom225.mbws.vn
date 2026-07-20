<?php

namespace App\Jobs;

use App\Models\Order;
use App\Support\NotificationHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendStoreOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            Log::warning('Skipped queued store notification because the order no longer exists.', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        NotificationHelper::deliverNewOrderNotification($order);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Queued store notification failed.', [
            'order_id' => $this->orderId,
            'message' => $exception->getMessage(),
        ]);
    }
}
