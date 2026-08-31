<?php

namespace App\Services\Payment;

use App\Exceptions\UnsupportedGatewayException;
use Carbon\Carbon;

class GatewayPayloadParser
{
    /**
     * Parse gateway-specific webhook payload into unified ParsedWebhook DTO.
     *
     * @param string $gateway Gateway name
     * @param array $payload Webhook payload
     * @return ParsedWebhook
     * @throws UnsupportedGatewayException
     */
    public function parse(string $gateway, array $payload): ParsedWebhook
    {
        return match ($gateway) {
            'simulated', 'mock' => $this->parseSimulated($payload),
            'aba' => $this->parseABA($payload),
            'khqr' => $this->parseKHQR($payload),
            'stripe' => $this->parseStripe($payload),
            'paypal' => $this->parsePayPal($payload),
            default => throw new UnsupportedGatewayException("Unsupported gateway: {$gateway}")
        };
    }

    /**
     * Parse simulated gateway payload.
     */
    private function parseSimulated(array $payload): ParsedWebhook
    {
        return new ParsedWebhook(
            transactionId: $payload['transaction_id'],
            referenceCode: $payload['reference'],
            amount: (float) $payload['amount'],
            currency: $payload['currency'] ?? 'USD',
            paidAt: isset($payload['payment_date'])
            ? Carbon::parse($payload['payment_date'])
            : (isset($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now()),
            receiptUrl: $payload['receipt_url'] ?? null,
            gateway: $payload['gateway'] ?? 'simulated'
        );
    }

    /**
     * Parse ABA Bank webhook (pushback) payload.
     *
     * ABA sends: tran_id (our reference), status ("00" = success),
     * apv (approval code), amount, payment_date
     */
    private function parseABA(array $payload): ParsedWebhook
    {
        return new ParsedWebhook(
            transactionId: $payload['apv'] ?? $payload['tran_id'] ?? '',
            referenceCode: $payload['tran_id'] ?? '', // tran_id = our PAY-T1-L15-... reference
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'USD',
            paidAt: isset($payload['payment_date'])
            ? Carbon::parse($payload['payment_date'])
            : now(),
            receiptUrl: null,
            gateway: 'aba'
        );
    }

    /**
     * Parse KHQR webhook payload.
     */
    private function parseKHQR(array $payload): ParsedWebhook
    {
        return new ParsedWebhook(
            transactionId: $payload['transaction_id'],
            referenceCode: $payload['reference'],
            amount: (float) $payload['amount'],
            currency: $payload['currency'] ?? 'KHR',
            paidAt: Carbon::parse($payload['timestamp']),
            receiptUrl: null,
            gateway: 'khqr'
        );
    }

    /**
     * Parse Stripe webhook payload.
     */
    private function parseStripe(array $payload): ParsedWebhook
    {
        $paymentIntent = $payload['data']['object'];

        return new ParsedWebhook(
            transactionId: $paymentIntent['id'],
            referenceCode: $paymentIntent['metadata']['reference'] ?? '',
            amount: (float) ($paymentIntent['amount'] / 100), // Stripe uses cents
            currency: strtoupper($paymentIntent['currency']),
            paidAt: Carbon::createFromTimestamp($paymentIntent['created']),
            receiptUrl: $paymentIntent['charges']['data'][0]['receipt_url'] ?? null,
            gateway: 'stripe'
        );
    }

    /**
     * Parse PayPal webhook payload.
     */
    private function parsePayPal(array $payload): ParsedWebhook
    {
        $resource = $payload['resource'];

        return new ParsedWebhook(
            transactionId: $resource['id'],
            referenceCode: $resource['custom_id'] ?? '',
            amount: (float) $resource['amount']['value'],
            currency: $resource['amount']['currency_code'],
            paidAt: Carbon::parse($resource['create_time']),
            receiptUrl: null,
            gateway: 'paypal'
        );
    }
}
