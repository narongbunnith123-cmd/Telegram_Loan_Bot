<?php

namespace App\Services\Payment;

use Carbon\Carbon;

/**
 * Data Transfer Object for parsed webhook data.
 * Provides a unified interface for webhook data from different gateways.
 */
class ParsedWebhook
{
    public function __construct(
        public string $transactionId,
        public string $referenceCode,
        public float $amount,
        public string $currency,
        public Carbon $paidAt,
        public ?string $receiptUrl = null,
        public string $gateway = 'simulated'
    ) {}
}
