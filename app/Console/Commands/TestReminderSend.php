<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Services\ReminderEngine;
use Illuminate\Console\Command;

class TestReminderSend extends Command
{
    protected $signature = 'test:reminder-send {--setup : Set up an overdue installment for testing}';
    protected $description = 'Quick test: dispatch reminder to Telegram';

    public function handle(ReminderEngine $engine): void
    {
        $loan = Loan::with(['borrower', 'group', 'installments'])
            ->whereIn('status', ['overdue', 'active'])
            ->latest()
            ->first();

        if (!$loan) {
            $this->error('No active/overdue loans found.');
            return;
        }

        $this->info("Loan #{$loan->id}: {$loan->borrower->name} ({$loan->status})");
        $this->info("Loan type: {$loan->loan_type}");

        // --setup: force an installment to be overdue
        if ($this->option('setup')) {
            if ($loan->isInstallmentLoan()) {
                $inst = $loan->installments()->whereIn('status', ['pending', 'partial', 'overdue'])->orderBy('installment_number')->first();
                if ($inst) {
                    $inst->update(['due_date' => now()->subDays(5), 'status' => 'overdue']);
                    $this->info("✅ Set installment #{$inst->installment_number} to overdue (due: " . now()->subDays(5)->format('Y-m-d') . ")");
                    $loan->update(['status' => 'overdue']);
                    // Refresh
                    $loan = $loan->fresh(['borrower', 'group', 'installments']);
                }
            }
        }

        // Debug
        if ($loan->isInstallmentLoan()) {
            $nextInst = $loan->nextDueInstallment();
            if ($nextInst) {
                $daysDiff = (int) $nextInst->due_date->diffInDays(today(), false);
                $this->info("Next inst: #{$nextInst->installment_number}, Due: {$nextInst->due_date->format('Y-m-d')}, Status: {$nextInst->status}, daysDiff: {$daysDiff}");
            } else {
                $this->warn("No next due installment!");
            }
        } else {
            if ($loan->due_date) {
                $daysDiff = (int) $loan->due_date->diffInDays(today(), false);
                $this->info("Due: {$loan->due_date->format('Y-m-d')}, daysDiff: {$daysDiff}");
            } else {
                $this->info("No due date set (interest-only loan)");
            }
        }

        // Check rules
        $rules = ReminderRule::where('tenant_id', $loan->tenant_id)->where('enabled', true)->get();
        $daysDiffVal = 0;
        if ($loan->isInstallmentLoan()) {
            $ni = $loan->nextDueInstallment();
            $daysDiffVal = $ni ? (int) $ni->due_date->diffInDays(today(), false) : 0;
        } else {
            $daysDiffVal = $loan->due_date ? (int) $loan->due_date->diffInDays(today(), false) : 0;
        }

        $this->info("\nRules ({$rules->count()}) vs daysDiff={$daysDiffVal}:");
        foreach ($rules as $rule) {
            $fires = $rule->shouldFire($daysDiffVal) ? '✅' : '❌';
            $this->info("  {$fires} {$rule->name} (type:{$rule->reminder_type}, offset:{$rule->days_offset})");
        }

        // Clear old reminders
        Reminder::where('loan_id', $loan->id)->delete();
        $loan->update(['last_reminder_sent_at' => null, 'next_reminder_at' => null, 'reminder_stage' => null]);

        // Run
        $result = $engine->processAll();
        $this->info("\nDispatched: {$result['dispatched']}, Skipped: {$result['skipped']}");

        if ($result['dispatched'] > 0) {
            $this->info("✅ Check your Telegram group!");
        }
    }
}
