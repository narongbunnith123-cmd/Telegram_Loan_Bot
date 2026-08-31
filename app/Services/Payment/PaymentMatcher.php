<?php

namespace App\Services\Payment;

use App\Exceptions\TenantMismatchException;
use App\Models\Payment;

class PaymentMatcher
{
    /**
     * Find a pending payment by reference code within a tenant.
     *
     * @param string $referenceCode
     * @param int $tenantId
     * @return Payment|null
     */
    public function findByReference(string $referenceCode, int $tenantId): ?Payment
    {
        return Payment::where('reference_code', $referenceCode)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * Validate that payment and its loan belong to the correct tenant.
     *
     * @param Payment $payment
     * @param int $tenantId
     * @return void
     * @throws TenantMismatchException
     */
    public function validateTenant(Payment $payment, int $tenantId): void
    {
        if ($payment->tenant_id !== $tenantId) {
            throw new TenantMismatchException(
                "Payment {$payment->id} belongs to tenant {$payment->tenant_id}, not {$tenantId}"
            );
        }

        if ($payment->loan->tenant_id !== $tenantId) {
            throw new TenantMismatchException(
                "Loan {$payment->loan_id} belongs to tenant {$payment->loan->tenant_id}, not {$tenantId}"
            );
        }
    }
}
