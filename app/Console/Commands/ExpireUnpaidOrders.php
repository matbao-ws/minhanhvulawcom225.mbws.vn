<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * An online order takes stock, a voucher use and promotion quota when it is
 * placed, before anyone pays. Left alone, an abandoned (or deliberately
 * unpaid) order held them for good: a few anonymous checkouts could mark a
 * product sold out or drain a flash sale. Orders still unpaid after the
 * method's TTL (config/orders.php) are cancelled and hand everything back.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid {--dry-run : Report the orders without cancelling them}';

    protected $description = 'Cancel online orders left unpaid past their payment window';

    public function handle(OrderCancellationService $cancellations): int
    {
        $expired = 0;

        foreach (config('orders.unpaid_ttl_minutes', []) as $method => $minutes) {
            if ((int) $minutes <= 0) {
                continue;
            }

            $ids = $this->candidates((string) $method, now()->subMinutes((int) $minutes))->pluck('id');
            if ($this->option('dry-run')) {
                $expired += $ids->count();

                continue;
            }

            foreach ($ids as $id) {
                $cancelled = DB::transaction(function () use ($id, $method, $minutes, $cancellations): bool {
                    // Re-checked under the lock: a payment may have landed since the query.
                    $order = $this->candidates((string) $method, now()->subMinutes((int) $minutes))
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    return $order !== null
                        && $cancellations->cancel($order, "Tự hủy: chưa thanh toán sau {$minutes} phút");
                });
                $expired += (int) $cancelled;
            }
        }

        $this->components->info($this->option('dry-run')
            ? "{$expired} unpaid order(s) would be cancelled."
            : "Cancelled {$expired} unpaid order(s).");

        return self::SUCCESS;
    }

    private function candidates(string $method, \DateTimeInterface $placedBefore)
    {
        return Order::query()
            ->where('payment_method', $method)
            ->where('status', 'pending')
            ->whereIn('payment_status', ['pending', 'failed'])
            ->where('created_at', '<', $placedBefore);
    }
}
