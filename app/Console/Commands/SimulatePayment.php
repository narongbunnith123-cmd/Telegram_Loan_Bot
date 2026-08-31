<?php

namespace App\Console\Commands;

use App\Models\PaymentSession;
use App\Models\PaymentTransaction;
use App\Jobs\Payment\ProcessPaymentWebhook;
use App\Services\Payment\Gateways\MockGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Simulate a payment for testing the full end-to-end flow.
 *
 * Usage:
 *   php artisan payment:simulate PAY-T1-L15-20260528-001
 *
 * What happens:
 *   1. Finds the PaymentSession by reference code
 *   2. Generates a mock webhook payload
 *   3. Creates a PaymentTransaction record
 *   4. Dispatches ProcessPaymentWebhook job (synchronously)
 *   5. Reports the result
 *
 * This simulates what happens when a real payment gateway sends a webhook.
 */
class SimulatePayment extends Command
{
    protected $signature = 'payment:simulate
        {reference : The payment session reference code}
        {--sync : Process synchronously instead of via queue}';

    protected $description = 'Simulate a payment webhook for testing (mock gateway only)';

    public function handle(): int
    {
        $reference = $this->argument('reference');

        // Find session
        $session = PaymentSession::where('reference_code', $reference)->first();

        if (!$session) {
            $this->error("Session not found: {$reference}");
            return self::FAILURE;
        }

        $this->info("Found session #{$session->id}");
        $this->line("  Loan:     #{$session->loan_id}");
        $this->line("  Borrower: #{$session->borrower_id}");
        $this->line("  Amount:   \${$session->amount}");
        $this->line("  Status:   {$session->status}");
        $this->line("  Gateway:  {$session->gateway_name}");

        if ($session->status !== 'pending') {
            $this->error("Session is not pending (status: {$session->status}). Cannot simulate.");
            return self::FAILURE;
        }

        if ($session->isExpired()) {
            $this->error("Session has expired. Cannot simulate.");
            return self::FAILURE;
        }

        // Generate mock webhook payload
        $mockGateway = app(MockGatewayService::class);
        $webhookPayload = $mockGateway->generateWebhookPayload($session);

        $this->info("\nGenerated mock webhook payload:");
        $this->line(json_encode($webhookPayload, JSON_PRETTY_PRINT));

        // Create PaymentTransaction (simulating what PaymentWebhookController does)
        $transaction = PaymentTransaction::create([
            'tenant_id' => $session->tenant_id,
            'source' => 'mock',
            'transaction_id' => $webhookPayload['transaction_id'],
            'payload' => $webhookPayload,
            'signature' => null,
            'status' => 'received',
        ]);

        $this->info("\nCreated PaymentTransaction #{$transaction->id}");

        if ($this->option('sync')) {
            // Process synchronously
            $this->info("Processing synchronously...");

            try {
                $job = new ProcessPaymentWebhook($transaction->id);
                $job->handle(
                    app(\App\Services\PaymentService::class),
                    app(\App\Services\Payment\PaymentMatcher::class),
                    app(\App\Services\Payment\GatewayPayloadParser::class),
                    app(\App\Services\Payment\IdempotencyGuard::class),
                );

                // Refresh session
                $session->refresh();
                $this->newLine();
                $this->info("✅ Payment simulation complete!");
                $this->line("  Session Status: {$session->status}");
                if ($session->payment_id) {
                    $this->line("  Payment ID:     #{$session->payment_id}");
                }
                if ($session->transaction_id) {
                    $this->line("  Transaction ID: {$session->transaction_id}");
                }

                // Show updated loan balance
                $loan = $session->loan->fresh();
                $this->line("  Loan Balance:   \${$loan->balance}");
                $this->line("  Remaining:      \${$loan->remaining_principal}");

            } catch (\Exception $e) {
                $this->error("❌ Processing failed: {$e->getMessage()}");
                return self::FAILURE;
            }
        } else {
            // Dispatch to queue
            ProcessPaymentWebhook::dispatch($transaction->id)->onQueue('payments');
            $this->info("\nDispatched to payments queue. Run queue worker to process.");
        }

        return self::SUCCESS;
    }
}
