<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $invoiceId,
        public readonly string $recipient,
    ) {
    }

    public function handle(): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);

        if (! $invoice) {
            Log::warning('Skipped queued invoice email because the invoice no longer exists.', [
                'invoice_id' => $this->invoiceId,
            ]);

            return;
        }

        Mail::to($this->recipient)->send(new InvoiceMail($invoice));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Queued invoice email failed.', [
            'invoice_id' => $this->invoiceId,
            'recipient' => $this->recipient,
            'message' => $exception->getMessage(),
        ]);
    }
}
