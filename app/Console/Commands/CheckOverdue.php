<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;

class CheckOverdue extends Command
{
    protected $signature = 'loans:check-overdue';
    protected $description = 'Mark loans past due_date as overdue';

    public function handle(): void
    {
        $updated = Loan::where('status', 'active')
            ->where('due_date', '<', today())
            ->where('balance', '>', 0)
            ->update(['status' => 'overdue']);

        $this->info("Marked {$updated} loans as overdue.");
    }
}
