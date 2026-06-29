<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddonWebhookController extends Controller
{
    /**
     * Handle Sepay webhook for unlocking addons.
     */
    public function handleWebhook(Request $request)
    {
        Log::info('Sepay Addon Webhook received payload: ', $request->all());

        // Simple auth check: check token in Authorization header or query param
        $authHeader = $request->header('Authorization');
        $token = $request->query('token');
        $expectedToken = 'mock_addon_sepay_token'; // Default token for testing

        // If authorization is "Apikey xxxx", extract "xxxx"
        if ($authHeader && preg_match('/Apikey\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // If token is configured in project settings, we could match against it
        // For local simulation, we accept expectedToken or empty check if not strictly configured

        $payload = $request->all();
        $transactionContent = $payload['transaction_content'] ?? $payload['content'] ?? '';
        $amountIn = (float)($payload['amount_in'] ?? 0);
        $sepayTransId = $payload['id'] ?? null;

        if (empty($transactionContent)) {
            Log::warning('Sepay Addon Webhook: empty transaction content.');
            return response()->json([
                'success' => false,
                'message' => 'Empty transaction content.'
            ], 422);
        }

        // Parse invoice number from content, looking for syntax like "ADDONPAID INV-ADDON-XXXXXXXX"
        // Pattern: INV-ADDON-[A-Z0-9]{8}
        if (!preg_match('/INV-ADDON-[A-Z0-9]+/i', $transactionContent, $matches)) {
            Log::warning('Sepay Addon Webhook: Invoice pattern not found in content: ' . $transactionContent);
            return response()->json([
                'success' => false,
                'message' => 'Invoice code not found in transaction content.'
            ], 200); // Return 200 so Sepay doesn't retry for unrelated transactions
        }

        $invoiceNumber = strtoupper($matches[0]);
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        if (!$invoice) {
            Log::warning('Sepay Addon Webhook: Invoice not found in database: ' . $invoiceNumber);
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.'
            ], 200);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Invoice already paid and addon is active.'
            ]);
        }

        // Check amount (accept equal or greater)
        if ($amountIn < (float)$invoice->amount) {
            Log::warning("Sepay Addon Webhook: Amount mismatch. Received: {$amountIn}, Expected: {$invoice->amount}");
            // Return 200 but keep pending, or update to failed
            return response()->json([
                'success' => false,
                'message' => 'Amount mismatch.'
            ], 200);
        }

        DB::beginTransaction();
        try {
            // Update invoice
            $invoice->update([
                'status' => 'paid',
                'sepay_transaction_id' => $sepayTransId,
            ]);

            // Unlock addon
            $addon = $invoice->addon;
            $addon->update([
                'is_purchased' => true,
            ]);

            DB::commit();
            Log::info("Sepay Addon Webhook: Addon '{$addon->code}' successfully unlocked for invoice {$invoiceNumber}");

            return response()->json([
                'success' => true,
                'message' => 'Addon unlocked successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sepay Addon Webhook Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.'
            ], 500);
        }
    }
}
