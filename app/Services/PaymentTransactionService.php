<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;

class PaymentTransactionService
{
    public function createPending(Order $order, string $source): PaymentTransaction
    {
        return PaymentTransaction::query()->firstOrCreate(
            ['idempotency_key' => "order:{$order->id}:payment:created"],
            [
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
                'gateway' => $order->payment_method,
                'transaction_reference' => $order->order_number,
                'amount' => $order->grand_total,
                'status' => 'pending',
                'payload' => ['source' => $source],
            ],
        );
    }

    public function recordVnpayIpn(Order $order, array $params, bool $successful): PaymentTransaction
    {
        $transactionId = (string) ($params['vnp_TransactionNo'] ?? 'unknown');
        $responseCode = (string) ($params['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($params['vnp_TransactionStatus'] ?? '');
        $idempotencyKey = implode(':', [
            'vnpay',
            'ipn',
            $order->order_number,
            $transactionId,
            $responseCode,
            $transactionStatus,
        ]);

        return PaymentTransaction::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'order_id' => $order->id,
                'payment_method' => 'vnpay',
                'gateway' => 'vnpay',
                'transaction_reference' => $order->order_number,
                'gateway_transaction_id' => $transactionId === 'unknown' ? null : $transactionId,
                'amount' => $order->grand_total,
                'status' => $successful ? 'paid' : 'failed',
                'response_code' => $responseCode ?: null,
                'payload' => $this->redactVnpayPayload($params),
                'processed_at' => now(),
            ],
        );
    }

    /**
     * Record one SePay bank transfer against an order.
     *
     * `id` is SePay's own transaction id and is unique per transfer, so it is the
     * idempotency key: a retried webhook — SePay retries whenever our endpoint errors
     * or times out — finds the existing row and the caller skips the state change.
     *
     * The amount stored is what actually arrived, not the order total, so an
     * underpayment or an overpayment stays visible for reconciliation.
     */
    public function recordSepayWebhook(Order $order, array $payload, string $status, string $outcome): PaymentTransaction
    {
        $gatewayTransactionId = trim((string) ($payload['id'] ?? ''));
        $idempotencyKey = 'sepay:webhook:'.($gatewayTransactionId !== ''
            ? $gatewayTransactionId
            : hash('sha256', json_encode(SePayService::redactPayload($payload))));

        return PaymentTransaction::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'order_id' => $order->id,
                'payment_method' => SePayService::METHOD_CODE,
                'gateway' => SePayService::METHOD_CODE,
                'transaction_reference' => $order->order_number,
                'gateway_transaction_id' => $gatewayTransactionId !== '' ? $gatewayTransactionId : null,
                'amount' => (float) ($payload['transferAmount'] ?? 0),
                'status' => $status,
                'response_code' => $outcome,
                'payload' => SePayService::redactPayload($payload),
                'processed_at' => now(),
            ],
        );
    }

    public function syncInitialTransaction(Order $order): void
    {
        $transaction = PaymentTransaction::query()
            ->where('idempotency_key', "order:{$order->id}:payment:created")
            ->first();

        if (! $transaction || $transaction->status === $order->payment_status) {
            return;
        }

        $transaction->update([
            'status' => $order->payment_status,
            'processed_at' => $order->payment_status === 'pending' ? null : now(),
        ]);
    }

    private function redactVnpayPayload(array $params): array
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        return $params;
    }
}
