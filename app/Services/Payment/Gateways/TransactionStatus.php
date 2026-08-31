<?php

namespace App\Services\Payment\Gateways;

/**
 * DTO for transaction status query results.
 */
class TransactionStatus
{
    public function __construct(
        public string $transactionId,
        public string $status,     // 'pending', 'completed', 'failed', 'refunded'
        public float $amount,
        public string $currency,
        public ?string $paidAt = null,
        public array $metadata = [],
    ) {
    }
}
