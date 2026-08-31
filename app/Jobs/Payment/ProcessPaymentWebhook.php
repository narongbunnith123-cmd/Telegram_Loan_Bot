<?php

namespace App\Jobs\Payment;

use App\Exceptions\TenantMismatchException;
use App\Models\Payment;
use App\Models\PaymentSession;
use App\Models\PaymentTransaction;
use App\Services\Payment\GatewayPayloadParser;
use App\Services\Payment\IdempotencyGuard;
use App\Services\Payment\PaymentMatcher;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $paymentTransactionId
    ) {
    }

    /**
     * Get the backoff intervals for retry attempts.
     */
    public function backoff(): array
    {
        return [60, 120, 240]; // 1min, 2min, 4min
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    /**
     * Execute the job.
     *
     * New flow (with PaymentSession support):
     * 1. Parse webhook payload
     * 2. Check idempotency
     * 3. Try to match PaymentSession first (new session-based flow)
     * 4. Fall back to PaymentMatcher (legacy direct-reference flow)
     * 5. Create Payment record + apply financial processing
     * 6. Update session/transaction status
     */
    public function handle(
        PaymentService $paymentService,
        PaymentMatcher $matcher,
        GatewayPayloadParser $parser,
        IdempotencyGuard $idempotencyGuard
    ): void {
        $transaction = PaymentTransaction::find($this->paymentTransactionId);

        if (!$transaction) {
            Log::error('PaymentTransaction not found', ['id' => $this->paymentTransactionId]);
            return;
        }

        // Skip if already processed or duplicate
        if (in_array($transaction->status, ['processed', 'duplicate'])) {
            Log::info('Skipping already processed transaction', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);
            return;
        }

        try {
            // Update status to processing
            $transaction->update(['status' => 'processing']);

            // Check idempotency - has this transaction_id been processed before?
            $duplicate = $idempotencyGuard->check($transaction->transaction_id, $transaction->tenant_id);
            if ($duplicate) {
                Log::info('Duplicate transaction detected', [
                    'transaction_id' => $transaction->transaction_id,
                    'tenant_id' => $transaction->tenant_id,
                ]);
                $transaction->markDuplicate();
                return;
            }

            // Parse payload based on gateway type
            $webhookData = $parser->parse($transaction->source, $transaction->payload);

            // ── NEW: Try PaymentSession first ──────────────────
            $session = PaymentSession::where('reference_code', $webhookData->referenceCode)
                ->where('tenant_id', $transaction->tenant_id)
                ->where('status', 'pending')
                ->first();

            if ($session) {
                $this->processViaSession(
                    $session,
                    $transaction,
                    $webhookData,
                    $paymentService
                );
                return;
            }

            // ── LEGACY: Fall back to PaymentMatcher ────────────
            $payment = $matcher->findByReference($webhookData->referenceCode, $transaction->tenant_id);

            if (!$payment) {
                $transaction->markFailed("Payment not found for reference: {$webhookData->referenceCode}");
                Log::warning('Payment not found', [
                    'reference' => $webhookData->referenceCode,
                    'tenant_id' => $transaction->tenant_id,
                ]);
                return;
            }

            // Validate tenant ownership
            try {
                $matcher->validateTenant($payment, $transaction->tenant_id);
            } catch (TenantMismatchException $e) {
                $transaction->markFailed($e->getMessage());
                Log::error('Tenant mismatch', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $transaction->tenant_id,
                    'error' => $e->getMessage(),
                ]);
                return;
            }

            // Apply payment via PaymentService (legacy flow)
            DB::transaction(function () use ($payment, $webhookData, $transaction, $paymentService) {
                // Update payment with webhook metadata
                $payment->update([
                    'transaction_id' => $webhookData->transactionId,
                    'gateway_name' => $webhookData->gateway,
                    'paid_at' => $webhookData->paidAt,
                ]);

                // Approve payment (this handles all business logic)
                $paymentService->approvePayment($payment, null); // null = system approval

                // Mark transaction as processed
                $transaction->markProcessed($payment);

                Log::info('Webhook payment processed successfully (legacy)', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $payment->amount,
                    'gateway' => $webhookData->gateway,
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                $transaction->markFailed('Processing error: ' . $e->getMessage());
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Process a webhook via the PaymentSession flow.
     *
     * 1. Validate session is still active
     * 2. Create a Payment record from session data
     * 3. Approve the payment (financial processing)
     * 4. Mark session as paid
     * 5. Mark transaction as processed
     */
    private function processViaSession(
        PaymentSession $session,
        PaymentTransaction $transaction,
        $webhookData,
        PaymentService $paymentService
    ): void {
        // Check if session is expired
        if ($session->isExpired()) {
            $session->markExpired();
            $transaction->markFailed("Payment session expired: {$session->reference_code}");
            Log::warning('Payment session expired at webhook time', [
                'session_id' => $session->id,
                'reference' => $session->reference_code,
                'expired_at' => $session->expires_at,
            ]);
            return;
        }

        // Validate amount matches (with small tolerance for currency conversion)
        $amountDiff = abs((float) $session->amount - (float) $webhookData->amount);
        if ($amountDiff > 0.01) {
            Log::warning('Webhook amount mismatch with session', [
                'session_amount' => $session->amount,
                'webhook_amount' => $webhookData->amount,
                'reference' => $session->reference_code,
            ]);
            // Continue processing — amount mismatch is logged but not blocking
            // The gateway amount is the source of truth for the actual paid amount
        }

        DB::transaction(function () use ($session, $transaction, $webhookData, $paymentService) {
            $loan = $session->loan;

            // Create Payment record from session data
            $payment = Payment::create([
                'tenant_id' => $session->tenant_id,
                'loan_id' => $session->loan_id,
                'amount' => $webhookData->amount, // Use actual paid amount from gateway
                'type' => 'partial',
                'method' => $session->gateway_name,
                'status' => 'pending',
                'notes' => "Auto-created from payment session #{$session->id}",
                'reference_code' => $session->reference_code,
                'transaction_id' => $webhookData->transactionId,
                'gateway_name' => $webhookData->gateway,
                'paid_at' => $webhookData->paidAt,
            ]);

            // Approve payment (handles principal reduction, interest, installments)
            $paymentService->approvePayment($payment, null); // null = system approval

            // Mark session as paid
            $session->markPaid(
                paymentId: $payment->id,
                transactionId: $webhookData->transactionId,
                webhookPayload: $transaction->payload,
            );

            // Mark transaction as processed
            $transaction->markProcessed($payment);

            Log::info('Webhook payment processed via session', [
                'session_id' => $session->id,
                'payment_id' => $payment->id,
                'transaction_id' => $transaction->id,
                'amount' => $payment->amount,
                'reference' => $session->reference_code,
                'gateway' => $webhookData->gateway,
            ]);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $transaction = PaymentTransaction::find($this->paymentTransactionId);

        if ($transaction && $transaction->status !== 'failed') {
            $transaction->markFailed('Job failed after retries: ' . $exception->getMessage());
        }

        Log::error('ProcessPaymentWebhook job failed', [
            'transaction_id' => $this->paymentTransactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
