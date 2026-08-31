<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\PenaltyLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenaltyService
{
    /**
     * Process all overdue installments across all tenants.
     * IDEMPOTENT — safe to re-run (unique constraint on penalty_logs prevents double-charging).
     */
    public function processAllOverdue(): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = 0;

        // Find all installment loans with overdue installments
        $overdueInstallments = LoanInstallment::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', today())
            ->whereHas('loan', function ($q) {
                $q->where('loan_type', 'installment')
                  ->where('penalty_type', '!=', 'none')
                  ->whereNotNull('penalty_value')
                  ->whereIn('status', ['active', 'overdue']);
            })
            ->with('loan')
            ->cursor();

        foreach ($overdueInstallments as $installment) {
            try {
                $result = $this->processInstallment($installment);
                if ($result) {
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error("Penalty processing failed for installment #{$installment->id}: {$e->getMessage()}");
            }
        }

        return [
            'processed' => $processed,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ];
    }

    /**
     * Process a single overdue installment — apply today's penalty if applicable.
     */
    public function processInstallment(LoanInstallment $installment): bool
    {
        $loan = $installment->loan;
        $today = today();

        // Check grace period
        $graceDays = $loan->grace_days ?? 3;
        $daysLate = (int) $installment->due_date->diffInDays($today);

        if ($daysLate <= $graceDays) {
            return false; // Still within grace period
        }

        // Check if penalty already applied today (idempotency)
        $alreadyApplied = PenaltyLog::where('installment_id', $installment->id)
            ->where('penalty_date', $today)
            ->exists();

        if ($alreadyApplied) {
            return false;
        }

        // Check penalty cap
        if ($this->isPenaltyCapped($installment, $loan)) {
            return false;
        }

        // Calculate penalty
        $penaltyAmount = $this->calculateDailyPenalty($installment, $loan);

        if ($penaltyAmount <= 0) {
            return false;
        }

        // Apply penalty in a transaction
        DB::transaction(function () use ($installment, $loan, $penaltyAmount, $daysLate, $today) {
            $balanceBefore = (float) $installment->balance;

            // Update installment
            $installment->penalty_amount = (float) $installment->penalty_amount + $penaltyAmount;
            $installment->late_days = $daysLate;
            $installment->status = 'overdue';
            $installment->recalculateBalance()->save();

            // Create penalty log
            PenaltyLog::create([
                'tenant_id'      => $loan->tenant_id,
                'loan_id'        => $loan->id,
                'installment_id' => $installment->id,
                'penalty_date'   => $today,
                'penalty_amount' => $penaltyAmount,
                'days_late'      => $daysLate,
                'balance_before' => $balanceBefore,
                'balance_after'  => (float) $installment->balance,
            ]);

            // Update loan status if needed
            if ($loan->status !== 'overdue') {
                $loan->update(['status' => 'overdue']);
            }
        });

        return true;
    }

    /**
     * Calculate the daily penalty for an installment.
     */
    public function calculateDailyPenalty(LoanInstallment $installment, ?Loan $loan = null): float
    {
        $loan = $loan ?? $installment->loan;
        return $loan->calculateDailyPenalty((float) $installment->base_amount);
    }

    /**
     * Check if the penalty has been capped for this installment.
     */
    public function isPenaltyCapped(LoanInstallment $installment, ?Loan $loan = null): bool
    {
        $loan = $loan ?? $installment->loan;

        if (!$loan->max_penalty_percent) {
            return false;
        }

        $maxPenalty = (float) $installment->base_amount * ($loan->max_penalty_percent / 100);
        return (float) $installment->penalty_amount >= $maxPenalty;
    }

    /**
     * Waive all penalties on an installment (admin action).
     */
    public function waivePenalties(LoanInstallment $installment): void
    {
        DB::transaction(function () use ($installment) {
            $installment->penalty_amount = 0;
            $installment->recalculateBalance()->save();
        });
    }

    /**
     * Also mark overdue status on pending installments past due date.
     */
    public function markOverdueInstallments(): int
    {
        return LoanInstallment::query()
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', today())
            ->whereHas('loan', fn($q) => $q->whereIn('status', ['active', 'overdue']))
            ->update(['status' => 'overdue']);
    }
}
