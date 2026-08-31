<?php

namespace App\Services\Payment;

use App\Models\PaymentTransaction;

class IdempotencyGuard
{
    /**
     * Check if a transaction ID has already been processed for a tenant.
     *
     * @param string $transactionId
     * @param int $tenantId
     * @return PaymentTransaction|null Returns the processed transaction if found, null otherwise
     */
    public function check(string $transactionId, int $tenantId): ?PaymentTransaction
    {
        return PaymentTransaction::where('transaction_id', $transactionId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'processed')
            ->first();
    }

    /**
     * Check if a transaction ID has already been processed.
     *
     * @param string $transactionId
     * @param int $tenantId
     * @return bool
     */
    public function isProcessed(string $transactionId, int $tenantId): bool
    {
        return $this->check($transactionId, $tenantId) !== null;
    }
}
