<?php

namespace App\Services\Payment;

use App\Exceptions\InvalidSignatureException;

class WebhookSignatureValidator
{
    /**
     * Validate webhook signature based on gateway type.
     *
     * @param string $gateway Gateway name (simulated, aba, khqr, stripe, paypal)
     * @param string $payload Raw request body
     * @param string|null $signature Signature from webhook header
     * @return bool
     * @throws InvalidSignatureException
     */
    public function validate(string $gateway, string $payload, ?string $signature): bool
    {
        // Skip validation for simulated gateway (testing)
        if ($gateway === 'simulated') {
            return true;
        }

        if (empty($signature)) {
            throw new InvalidSignatureException('Signature required for ' . $gateway);
        }

        return match($gateway) {
            'aba' => $this->validateHMAC($payload, $signature, config('payment.gateways.aba.webhook_secret')),
            'khqr' => $this->validateHMAC($payload, $signature, config('payment.gateways.khqr.webhook_secret')),
            'stripe' => $this->validateStripeSignature($payload, $signature),
            'paypal' => $this->validatePayPalSignature($payload, $signature),
            default => false
        };
    }

    /**
     * Validate HMAC-SHA256 signature (used by ABA and KHQR).
     *
     * @param string $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    private function validateHMAC(string $payload, string $signature, string $secret): bool
    {
        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computed, $signature);
    }

    /**
     * Validate Stripe webhook signature.
     * Stripe uses timestamp + signature format.
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    private function validateStripeSignature(string $payload, string $signature): bool
    {
        $secret = config('payment.gateways.stripe.webhook_secret');

        try {
            // Stripe SDK handles signature validation
            \Stripe\Webhook::constructEvent($payload, $signature, $secret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate PayPal webhook signature.
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    private function validatePayPalSignature(string $payload, string $signature): bool
    {
        // PayPal uses certificate-based validation
        // For now, use HMAC with webhook secret
        $secret = config('payment.gateways.paypal.webhook_secret');
        return $this->validateHMAC($payload, $signature, $secret);
    }
}
