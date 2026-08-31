<?php

namespace App\Console\Commands;

use App\Services\PenaltyService;
use App\Services\ReminderEngine;
use Illuminate\Console\Command;

class ProcessOverdue extends Command
{
    protected $signature = 'loans:process-overdue';
    protected $description = 'Process overdue installments: mark overdue status + apply daily penalties + queue reminders';

    public function handle(PenaltyService $penaltyService, ReminderEngine $reminderEngine): void
    {
        $this->info('Processing overdue installments...');

        // Step 1: Mark overdue installments
        $marked = $penaltyService->markOverdueInstallments();
        $this->info("  → Marked {$marked} installments as overdue.");

        // Step 2: Apply daily penalties (idempotent — safe to re-run)
        $result = $penaltyService->processAllOverdue();
        $this->info("  → Penalties: {$result['processed']} applied, {$result['skipped']} skipped, {$result['errors']} errors.");

        // Step 3: Queue Telegram reminders for overdue loans (Plan Step 6)
        if ($result['processed'] > 0) {
            $reminderResult = $reminderEngine->processAll();
            $this->info("  → Reminders: {$reminderResult['dispatched']} dispatched, {$reminderResult['skipped']} skipped.");
        }

        $this->info('Done.');
    }
}
