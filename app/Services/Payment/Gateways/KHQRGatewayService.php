<?php

namespace App\Services\Payment\Gateways;

use App\Models\PaymentSession;
use App\Services\Payment\ParsedWebhook;
use Carbon\Carbon;

/**
 * KHQR Gateway Service — Stub implementation.
 *
 * This is the primary real gateway target for Cambodia market.
 * KHQR supports multiple banking apps (ABA, ACLEDA, Bakong, etc.)
 * scanning the same QR code.
 *
 * Currently a placeholder — implement when real API credentials are available.
 *
 * Required from KHQR provider:
 * - Merchant registration / business verification
 * - API key / merchant ID
 * - Webhook secret for signature validation
 * - QR generation API endpoint
 *
 * Config location: config/payment.php → gateways.khqr
 */
class KHQRGatewayService implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'khqr';
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['USD', 'KHR']);
    }

    /**
     * Create a payment via KHQR API.
     *
     * @throws \RuntimeException Until real integration is implemented
     */
    public function createPayment(PaymentSession $session): GatewayResponse
    {
        // TODO: Implement real KHQR API call
        // Steps:
        // 1. Call KHQR merchant API to generate QR code
        // 2. Include reference_code in the QR merchant data
        // 3. Set amount and currency in QR payload
        // 4. Return the QR payload + checkout URL (if supported)

        $merchantId = config('payment.gateways.khqr.merchant_id');
        $apiKey = config('payment.gateways.khqr.api_key');

        if (!$merchantId || !$apiKey) {
            return GatewayResponse::failed(
                'KHQR gateway not configured. Set KHQR_MERCHANT_ID and KHQR_API_KEY in .env'
            );
        }

        // Placeholder — replace with real API call
        return GatewayResponse::failed(
            'KHQR gateway integration not yet implemented. Use mock gateway for testing.'
        );
    }

    /**
     * Verify KHQR webhook signature using HMAC-SHA256.
     */
    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $secret = config('payment.gateways.khqr.webhook_secret');
        if (!$secret) {
            return false;
        }

        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computed, $signature);
    }

    /**
     * Parse KHQR webhook payload.
     */
    public function parseWebhookPayload(array $payload): ParsedWebhook
    {
        return new ParsedWebhook(
            transactionId: $payload['transaction_id'] ?? '',
            referenceCode: $payload['reference'] ?? '',
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'KHR',
            paidAt: Carbon::parse($payload['timestamp'] ?? now()),
            receiptUrl: null,
            gateway: 'khqr',
        );
    }

    /**
     * Query transaction status from KHQR.
     */
    public function queryTransaction(string $transactionId): ?TransactionStatus
    {
        // TODO: Implement real KHQR transaction query
        return null;
    }

    /**
     * Refund a KHQR transaction.
     */
    public function refund(string $transactionId, float $amount): RefundResult
    {
        // TODO: Implement real KHQR refund
        return RefundResult::failed('KHQR refund not yet implemented.');
    }
}
