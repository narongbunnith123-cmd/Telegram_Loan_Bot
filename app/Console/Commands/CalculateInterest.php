<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanInterestLog;
use Illuminate\Console\Command;

class CalculateInterest extends Command
{
    protected $signature = 'loans:calculate-interest';
    protected $description = 'Apply daily interest to all active and overdue loans';

    public function handle(): void
    {
        Loan::whereIn('status', ['active', 'overdue'])
            ->where('balance', '>', 0)
            ->chunk(200, function ($loans) {
                foreach ($loans as $loan) {
                    // Prevent double-charging if cron runs twice in one day
                    $alreadyDone = LoanInterestLog::where('loan_id', $loan->id)
                        ->where('calculated_date', today())
                        ->exists();
                    if ($alreadyDone) continue;

                    $interest = $loan->calculateDailyInterest();
                    if ($interest <= 0) continue;

                    LoanInterestLog::create([
                        'tenant_id'       => $loan->tenant_id,
                        'loan_id'         => $loan->id,
                        'interest_applied' => $interest,
                        'balance_before'  => $loan->remaining_principal,
                        'balance_after'   => $loan->remaining_principal,
                        'days_overdue'    => $loan->days_overdue,
                        'calculated_date' => today(),
                    ]);

                    $loan->increment('accrued_interest', $interest);
                    $loan->recalculateBalance();
                }
            });

        $this->info('Interest calculated for all active loans.');
    }
}
