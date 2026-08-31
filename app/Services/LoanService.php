<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\User;
use App\Jobs\Telegram\SendLoanCreatedJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanService
{
    public function __construct(
        private InstallmentService $installmentService,
    ) {
    }

    /**
     * Create a new loan.
     * Single source of truth — used by Web dashboard and Telegram bot.
     *
     * @param int        $tenantId   Tenant ID
     * @param array      $data       Loan data
     * @param User|null  $createdBy  The admin creating this loan (null-safe for queue/cron)
     * @return Loan
     */
    public function createLoan(int $tenantId, array $data, ?User $createdBy = null): Loan
    {
        return DB::transaction(function () use ($tenantId, $data, $createdBy) {
            $principal = (float) $data['principal'];
            $interestType = $data['interest_type'] ?? 'percentage';
            $interestValue = (float) ($data['interest_value'] ?? 0);
            $loanType = $data['loan_type'] ?? 'lump_sum';
            $isInstallment = $loanType === 'installment';
            $loanDate = Carbon::parse($data['loan_date'] ?? now());

            // ── Calculate daily_interest_rate from interest settings ──
            // For percentage: interest_value is % per day → divide by 100
            // For fixed: interest_value is $ per day → divide by principal to get rate
            $dailyInterestRate = $interestType === 'fixed'
                ? ($principal > 0 ? $interestValue / $principal : 0)
                : $interestValue / 100;

            // ── Build loan data ──
            $loanData = [
                'tenant_id' => $tenantId,
                'group_id' => $data['group_id'],
                'borrower_id' => $data['borrower_id'],
                'principal' => $principal,
                'balance' => $principal,
                'remaining_principal' => $principal,
                'accrued_interest' => 0,
                'daily_interest_rate' => $dailyInterestRate,
                'interest_type' => $interestType,
                'interest_value' => $interestValue,
                'loan_date' => $loanDate,
                'loan_type' => $loanType,
                'penalty_type' => $data['penalty_type'] ?? 'none',
                'penalty_value' => ($data['penalty_type'] ?? 'none') !== 'none'
                    ? ($data['penalty_value'] ?? 0) : null,
                'grace_days' => $data['grace_days'] ?? 3,
                'reminders_enabled' => $data['reminders_enabled'] ?? true,
                'status' => 'active',
                'created_by' => $createdBy?->id,
                'notes' => $data['notes'] ?? null,
            ];

            // ── Installment-specific fields ──
            if ($isInstallment) {
                $durationMonths = (int) ($data['duration_months'] ?? 1);
                $loanData['duration_months'] = $durationMonths;
                $loanData['monthly_installment'] = round($principal / $durationMonths, 2);
                $loanData['due_date'] = $loanDate->copy()->addMonths($durationMonths);
            } else {
                // Lump-sum: due_date may be null (revolving loan)
                $loanData['due_date'] = isset($data['due_date']) ? Carbon::parse($data['due_date']) : null;
            }

            $loan = Loan::create($loanData);

            // ── Generate installment schedule ──
            if ($isInstallment) {
                $this->installmentService->generateSchedule($loan);
            }

            // ── Dispatch Telegram notification ──
            try {
                SendLoanCreatedJob::dispatch($loan)->onQueue('telegram');
            } catch (\Exception $e) {
                Log::warning("Failed to dispatch loan creation notification for loan #{$loan->id}: {$e->getMessage()}");
            }

            // ── Activity log ──
            activity()
                ->performedOn($loan)
                ->causedBy($createdBy)
                ->withProperties([
                    'principal' => $principal,
                    'interest' => "{$interestValue} ({$interestType})",
                    'loan_type' => $loanType,
                ])
                ->log('Loan created');

            return $loan;
        });
    }

    /**
     * Cancel a loan.
     *
     * @param Loan      $loan
     * @param User|null $cancelledBy
     * @return void
     */
    public function cancelLoan(Loan $loan, ?User $cancelledBy = null): void
    {
        $loan->update(['status' => 'cancelled']);

        activity()
            ->performedOn($loan)
            ->causedBy($cancelledBy)
            ->log('Loan cancelled');
    }

    /**
     * Get the display name for a loan type.
     * Internal: lump_sum / installment
     * Display: Revolving Loan / Fixed Term Loan
     */
    public static function displayLoanType(string $internalType): string
    {
        return match ($internalType) {
            'lump_sum' => 'Revolving Loan',
            'installment' => 'Fixed Term Loan',
            default => ucfirst(str_replace('_', ' ', $internalType)),
        };
    }
}
