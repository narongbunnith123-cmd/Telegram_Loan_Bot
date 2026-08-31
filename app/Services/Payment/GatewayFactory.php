<?php

namespace App\Services\Payment;

use App\Exceptions\UnsupportedGatewayException;
use App\Services\Payment\Gateways\AbaPaywayGatewayService;
use App\Services\Payment\Gateways\KHQRGatewayService;
use App\Services\Payment\Gateways\MockGatewayService;
use App\Services\Payment\Gateways\PaymentGatewayInterface;

/**
 * Gateway Factory — resolves the correct payment gateway adapter by name.
 *
 * Centralizes gateway instantiation so the rest of the system
 * only works with PaymentGatewayInterface, never concrete classes.
 *
 * Adding a new gateway:
 * 1. Create a class implementing PaymentGatewayInterface
 * 2. Add it to the match() in resolve()
 * 3. Add config in config/payment.php
 */
class GatewayFactory
{
    /**
     * Resolve a gateway adapter by name.
     *
     * @param string $gatewayName  e.g. 'mock', 'khqr', 'aba'
     * @return PaymentGatewayInterface
     * @throws UnsupportedGatewayException
     */
    public function resolve(string $gatewayName): PaymentGatewayInterface
    {
        return match ($gatewayName) {
            'mock', 'simulated' => app(MockGatewayService::class),
            'aba' => app(AbaPaywayGatewayService::class),
            'khqr' => app(KHQRGatewayService::class),
            default => throw new UnsupportedGatewayException("Unsupported gateway: {$gatewayName}"),
        };
    }

    /**
     * Get all available gateway names.
     *
     * @return array<string>
     */
    public function availableGateways(): array
    {
        return ['mock', 'aba', 'khqr'];
    }

    /**
     * Check if a gateway name is supported.
     */
    public function isSupported(string $gatewayName): bool
    {
        try {
            $this->resolve($gatewayName);
            return true;
        } catch (UnsupportedGatewayException) {
            return false;
        }
    }
}
