<?php

namespace App\Services\Payment\Gateways;

use App\Models\PaymentSession;
use App\Services\Payment\ParsedWebhook;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Mock Gateway Service for testing payment flows.
 *
 * Generates fake QR payloads and checkout URLs.
 * Allows simulating the full payment lifecycle without real bank integration.
 *
 * Use this to prove:
 * - Payment session flow works
 * - QR generation works
 * - Webhook reconciliation works
 * - Telegram notification works
 * - Idempotency works
 */
class MockGatewayService implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'mock';
    }

    public function supportsCurrency(string $currency): bool
    {
        // Mock gateway accepts all currencies
        return true;
    }

    /**
     * Create a mock payment — generates fake QR and checkout URL.
     */
    public function createPayment(PaymentSession $session): GatewayResponse
    {
        // Generate a fake KHQR-style QR payload
        $qrPayload = $this->generateMockQR($session);

        // Generate a fake checkout URL
        $checkoutUrl = url("/mock-pay/{$session->reference_code}");

        // Generate a fake gateway reference
        $gatewayReference = 'MOCK-' . strtoupper(Str::random(8));

        return GatewayResponse::success(
            qrPayload: $qrPayload,
            checkoutUrl: $checkoutUrl,
            gatewayReference: $gatewayReference,
            metadata: [
                'mock' => true,
                'generated_at' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Mock webhook verification — always passes.
     */
    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        return true;
    }

    /**
     * Parse a mock webhook payload.
     */
    public function parseWebhookPayload(array $payload): ParsedWebhook
    {
        return new ParsedWebhook(
            transactionId: $payload['transaction_id'] ?? 'MOCK-' . Str::random(8),
            referenceCode: $payload['reference'] ?? '',
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'USD',
            paidAt: isset($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now(),
            receiptUrl: $payload['receipt_url'] ?? null,
            gateway: 'mock',
        );
    }

    /**
     * Query mock transaction — returns a completed status.
     */
    public function queryTransaction(string $transactionId): ?TransactionStatus
    {
        return new TransactionStatus(
            transactionId: $transactionId,
            status: 'completed',
            amount: 0, // Unknown without session context
            currency: 'USD',
            paidAt: now()->toIso8601String(),
            metadata: ['mock' => true],
        );
    }

    /**
     * Mock refund — always succeeds.
     */
    public function refund(string $transactionId, float $amount): RefundResult
    {
        return RefundResult::success(
            refundId: 'REFUND-' . strtoupper(Str::random(8)),
            amount: $amount,
        );
    }

    // ── Mock-Specific Methods ──────────────────────

    /**
     * Generate a mock webhook payload for a payment session.
     * Use this to simulate a successful payment callback.
     *
     * @param PaymentSession $session
     * @return array  The mock webhook payload
     */
    public function generateWebhookPayload(PaymentSession $session): array
    {
        return [
            'transaction_id' => 'MOCK-TXN-' . strtoupper(Str::random(8)),
            'reference' => $session->reference_code,
            'amount' => (float) $session->amount,
            'currency' => $session->currency,
            'status' => 'success',
            'paid_at' => now()->toIso8601String(),
            'receipt_url' => url("/mock-pay/{$session->reference_code}/receipt"),
            'gateway' => 'mock',
        ];
    }

    /**
     * Generate a fake KHQR-style QR payload.
     *
     * In production, this would be a real KHQR binary payload.
     * For testing, we generate a human-readable mock that includes
     * all the data a real QR code would contain.
     */
    private function generateMockQR(PaymentSession $session): string
    {
        $data = [
            'type' => 'MOCK_KHQR',
            'version' => '1.0',
            'merchant' => 'LoanBot SaaS',
            'reference' => $session->reference_code,
            'amount' => $session->amount,
            'currency' => $session->currency,
            'expires' => $session->expires_at?->toIso8601String(),
        ];

        // Base64 encode to simulate a real QR payload
        return 'MOCKQR:' . base64_encode(json_encode($data));
    }
}
