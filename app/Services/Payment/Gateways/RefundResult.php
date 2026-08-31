<?php

namespace App\Services\Payment\Gateways;

/**
 * DTO for refund operation results.
 */
class RefundResult
{
    public function __construct(
        public bool $success,
        public ?string $refundId = null,
        public ?float $amountRefunded = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(string $refundId, float $amount): self
    {
        return new self(success: true, refundId: $refundId, amountRefunded: $amount);
    }

    public static function failed(string $errorMessage): self
    {
        return new self(success: false, errorMessage: $errorMessage);
    }
}
