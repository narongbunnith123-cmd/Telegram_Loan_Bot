<?php

namespace App\Services\Payment;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentSession;

class PaymentReferenceGenerator
{
    /**
     * Generate a unique payment reference code for a loan.
     * Legacy format: PAY-LOAN-{loan_id}-{sequence}
     *
     * Kept unchanged for backward compatibility with existing payments.
     *
     * @param Loan $loan
     * @return string
     */
    public function generate(Loan $loan): string
    {
        $sequence = $this->getNextSequence($loan);
        return "PAY-LOAN-{$loan->id}-{$sequence}";
    }

    /**
     * Generate a structured payment reference for a PaymentSession.
     * Format: T{tenant_id}L{loan_id}-{YYYYMMDD}-{sequence}
     *
     * Example: T1L15-20260528-001 (max ~18 chars)
     *
     * ABA PayWay requires tran_id ≤ 20 characters.
     * This format includes:
     * - Tenant ID for multi-tenant reconciliation
     * - Loan ID for quick lookup
     * - Date for daily grouping and auditing
     * - 3-digit sequence (resets daily per loan)
     *
     * @param Loan $loan
     * @return string
     */
    public function generateForSession(Loan $loan): string
    {
        $tenantId = $loan->tenant_id;
        $loanId = $loan->id;
        $date = now()->format('Ymd');
        $sequence = $this->getNextSessionSequence($loan, $date);

        return sprintf('T%dL%d-%s-%03d', $tenantId, $loanId, $date, $sequence);
    }

    /**
     * Parse a structured reference code into its components.
     *
     * @param string $referenceCode  e.g. "PAY-T1-L15-20260528-001"
     * @return array{tenant_id: int, loan_id: int, date: string, sequence: int}|null
     */
    public function parse(string $referenceCode): ?array
    {
        // New compact format: T{t}L{l}-{date}-{seq}
        if (preg_match('/^T(\d+)L(\d+)-(\d{8})-(\d{3})$/', $referenceCode, $matches)) {
            return [
                'tenant_id' => (int) $matches[1],
                'loan_id' => (int) $matches[2],
                'date' => $matches[3],
                'sequence' => (int) $matches[4],
                'format' => 'session',
            ];
        }

        // Old session format (backward compat): PAY-T{t}-L{l}-{date}-{seq}
        if (preg_match('/^PAY-T(\d+)-L(\d+)-(\d{8})-(\d{3})$/', $referenceCode, $matches)) {
            return [
                'tenant_id' => (int) $matches[1],
                'loan_id' => (int) $matches[2],
                'date' => $matches[3],
                'sequence' => (int) $matches[4],
                'format' => 'session',
            ];
        }

        // Legacy format: PAY-LOAN-{loan_id}-{seq}
        if (preg_match('/^PAY-LOAN-(\d+)-(\d+)$/', $referenceCode, $matches)) {
            return [
                'tenant_id' => null,
                'loan_id' => (int) $matches[1],
                'date' => null,
                'sequence' => (int) $matches[2],
                'format' => 'legacy',
            ];
        }

        return null;
    }

    /**
     * Check if a reference code uses the new session format.
     */
    public function isSessionFormat(string $referenceCode): bool
    {
        // Match both new compact (T1L3-...) and old (PAY-T1-L3-...) formats
        return (bool) preg_match('/^(PAY-)?T\d+L?\d+/', $referenceCode);
    }

    /**
     * Get the next sequence number for a loan's payment references (legacy).
     * Queries the last payment with a reference code and increments.
     * Returns 1 for the first payment.
     *
     * @param Loan $loan
     * @return int
     */
    private function getNextSequence(Loan $loan): int
    {
        $lastPayment = Payment::where('loan_id', $loan->id)
            ->whereNotNull('reference_code')
            ->orderByDesc('id')
            ->first();

        if (!$lastPayment || !$lastPayment->reference_code) {
            return 1;
        }

        // Extract sequence from PAY-LOAN-{loan_id}-{sequence}
        $parts = explode('-', $lastPayment->reference_code);
        $lastSequence = (int) end($parts);

        return $lastSequence + 1;
    }

    /**
     * Get the next sequence number for a payment session.
     * Scoped to loan + date (daily reset).
     *
     * @param Loan   $loan
     * @param string $date  YYYYMMDD format
     * @return int
     */
    private function getNextSessionSequence(Loan $loan, string $date): int
    {
        // Search for both new and old format prefixes
        $newPrefix = sprintf('T%dL%d-%s-', $loan->tenant_id, $loan->id, $date);
        $oldPrefix = sprintf('PAY-T%d-L%d-%s-', $loan->tenant_id, $loan->id, $date);

        $lastSession = PaymentSession::where('loan_id', $loan->id)
            ->where(function ($q) use ($newPrefix, $oldPrefix) {
                $q->where('reference_code', 'like', "{$newPrefix}%")
                    ->orWhere('reference_code', 'like', "{$oldPrefix}%");
            })
            ->orderByDesc('id')
            ->first();

        if (!$lastSession) {
            return 1;
        }

        // Extract the 3-digit sequence from the end
        $parts = explode('-', $lastSession->reference_code);
        $lastSequence = (int) end($parts);

        return $lastSequence + 1;
    }
}
