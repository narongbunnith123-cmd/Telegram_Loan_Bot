<?php

namespace App\Console\Commands;

use App\Services\ReminderEngine;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'loans:send-reminders';
    protected $description = 'Process reminder rules and dispatch Telegram reminder jobs';

    public function handle(ReminderEngine $engine): void
    {
        $this->info('Processing reminders...');

        $result = $engine->processAll();

        $this->info("Dispatched {$result['dispatched']} reminders, skipped {$result['skipped']}.");
    }
}
