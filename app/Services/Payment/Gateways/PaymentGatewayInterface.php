<?php

namespace App\Services\Payment\Gateways;

use App\Models\PaymentSession;
use App\Services\Payment\ParsedWebhook;

/**
 * Payment Gateway Interface.
 *
 * Abstracts all payment provider interactions behind a common contract.
 * Each gateway adapter (Mock, KHQR, ABA, etc.) implements this interface.
 *
 * This prevents provider lock-in and allows adding new payment providers
 * without rewriting core payment architecture.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment request with the gateway.
     * Returns QR payload, checkout URL, or other payment details.
     *
     * @param PaymentSession $session  The payment session to create payment for
     * @return GatewayResponse
     */
    public function createPayment(PaymentSession $session): GatewayResponse;

    /**
     * Verify a webhook signature from this gateway.
     *
     * @param string      $payload    Raw request body
     * @param string|null $signature  Signature from webhook header
     * @return bool
     */
    public function verifyWebhook(string $payload, ?string $signature): bool;

    /**
     * Parse a webhook payload from this gateway into a unified DTO.
     *
     * @param array $payload  Decoded webhook payload
     * @return ParsedWebhook
     */
    public function parseWebhookPayload(array $payload): ParsedWebhook;

    /**
     * Query the status of a transaction from the gateway.
     *
     * @param string $transactionId  The gateway's transaction ID
     * @return TransactionStatus|null
     */
    public function queryTransaction(string $transactionId): ?TransactionStatus;

    /**
     * Request a refund for a transaction.
     *
     * @param string $transactionId  The gateway's transaction ID
     * @param float  $amount         Amount to refund
     * @return RefundResult
     */
    public function refund(string $transactionId, float $amount): RefundResult;

    /**
     * Get the gateway name identifier.
     *
     * @return string  e.g. 'mock', 'khqr', 'aba'
     */
    public function getName(): string;

    /**
     * Check if this gateway supports a given currency.
     *
     * @param string $currency  ISO currency code (e.g. 'USD', 'KHR')
     * @return bool
     */
    public function supportsCurrency(string $currency): bool;
}
