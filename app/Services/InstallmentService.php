<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Support\Collection;

class InstallmentService
{
    /**
     * Generate installment schedule when a loan is created with type = 'installment'.
     * Divides principal equally across duration_months.
     */
    public function generateSchedule(Loan $loan): Collection
    {
        if (!$loan->isInstallmentLoan()) {
            throw new \InvalidArgumentException('Cannot generate installments for a lump-sum loan.');
        }

        $months = $loan->duration_months;
        $monthlyAmount = round($loan->principal / $months, 2);

        // Handle rounding remainder on the last installment
        $totalAllocated = $monthlyAmount * ($months - 1);
        $lastAmount = round($loan->principal - $totalAllocated, 2);

        $installments = collect();
        $startDate = $loan->loan_date->copy();

        for ($i = 1; $i <= $months; $i++) {
            $dueDate = $startDate->copy()->addMonths($i);
            $amount = ($i === $months) ? $lastAmount : $monthlyAmount;

            $installments->push(LoanInstallment::create([
                'tenant_id'          => $loan->tenant_id,
                'loan_id'            => $loan->id,
                'installment_number' => $i,
                'due_date'           => $dueDate,
                'base_amount'        => $amount,
                'penalty_amount'     => 0,
                'paid_amount'        => 0,
                'balance'            => $amount,
                'late_days'          => 0,
                'status'             => 'pending',
            ]));
        }

        // Update loan with monthly installment amount
        $loan->update(['monthly_installment' => $monthlyAmount]);

        return $installments;
    }

    /**
     * Get the next unpaid installment for a loan.
     */
    public function getNextDue(Loan $loan): ?LoanInstallment
    {
        return $loan->nextDueInstallment();
    }

    /**
     * Apply a payment to an installment.
     * Allocation order: penalty first → then principal (industry standard).
     *
     * Returns array with allocation details.
     */
    public function applyPayment(LoanInstallment $installment, float $amount): array
    {
        $penaltyDue = (float) $installment->penalty_amount - $this->getPenaltyPaid($installment);
        $penaltyAllocation = min($amount, max(0, $penaltyDue));
        $principalAllocation = $amount - $penaltyAllocation;

        $newPaidAmount = (float) $installment->paid_amount + $amount;
        $totalDue = (float) $installment->base_amount + (float) $installment->penalty_amount;

        $installment->paid_amount = min($newPaidAmount, $totalDue);
        $installment->recalculateBalance();

        // Determine new status
        if ($installment->balance <= 0) {
            $installment->status = 'paid';
            $installment->paid_at = now();
        } elseif ($installment->paid_amount > 0) {
            $installment->status = 'partial';
        }

        $installment->save();

        // Calculate excess for carry-forward
        $excess = max(0, $newPaidAmount - $totalDue);

        return [
            'penalty_allocated'   => round($penaltyAllocation, 2),
            'principal_allocated' => round($principalAllocation, 2),
            'excess'              => round($excess, 2),
            'installment_status'  => $installment->status,
        ];
    }

    /**
     * Carry forward excess payment to the next unpaid installment.
     */
    public function carryForwardCredit(Loan $loan, float $excess): array
    {
        $carried = [];

        while ($excess > 0) {
            $nextInstallment = $loan->nextDueInstallment();
            if (!$nextInstallment) break;

            $result = $this->applyPayment($nextInstallment, $excess);
            $carried[] = [
                'installment_number' => $nextInstallment->installment_number,
                'amount_applied'     => $excess - $result['excess'],
            ];
            $excess = $result['excess'];
        }

        return $carried;
    }

    /**
     * Update overall loan balance based on installment totals.
     */
    public function syncLoanBalance(Loan $loan): void
    {
        $totalBalance = $loan->installments()->sum('balance');
        $loan->balance = max(0, $totalBalance);

        if ($loan->balance <= 0) {
            // Use 'completed' for installment loans per plan (not 'paid')
            $loan->status = $loan->isInstallmentLoan() ? 'completed' : 'paid';
            $loan->paid_at = now();
        }

        $loan->save();
    }

    /**
     * Get total penalty already paid on an installment.
     */
    private function getPenaltyPaid(LoanInstallment $installment): float
    {
        return (float) $installment->payments()
            ->where('status', 'approved')
            ->sum('penalty_paid');
    }
}
