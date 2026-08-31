<?php

namespace App\Services\Payment\Gateways;

/**
 * Data Transfer Object for gateway payment creation responses.
 *
 * Provides a unified interface for the data returned when requesting
 * a payment from any gateway (QR code, checkout URL, etc.).
 */
class GatewayResponse
{
    public function __construct(
        public bool $success,
        public ?string $qrPayload = null,
        public ?string $checkoutUrl = null,
        public ?string $gatewayReference = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Create a successful response.
     */
    public static function success(
        ?string $qrPayload = null,
        ?string $checkoutUrl = null,
        ?string $gatewayReference = null,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            qrPayload: $qrPayload,
            checkoutUrl: $checkoutUrl,
            gatewayReference: $gatewayReference,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed response.
     */
    public static function failed(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }
}
