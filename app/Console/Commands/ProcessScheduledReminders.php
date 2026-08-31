<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\Telegram\TelegramSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledReminders extends Command
{
    protected $signature = 'reminders:process-scheduled';
    protected $description = 'Send scheduled reminders that are due';

    public function handle(TelegramSender $sender): int
    {
        $due = Reminder::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with(['loan.borrower', 'loan.group'])
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($due as $reminder) {
            if ($reminder->scheduled_at && $reminder->scheduled_at->lt(now()->subDay())) {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => 'Scheduled reminder was missed by more than 24 hours.',
                ]);
                $failed++;
                continue;
            }

            $claimed = Reminder::whereKey($reminder->id)
                ->where('status', 'scheduled')
                ->update(['status' => 'pending']);

            if (!$claimed) {
                continue;
            }

            $chatId = $reminder->telegram_chat_id;
            $message = $reminder->rendered_message ?? $reminder->message_snapshot;

            if (!$chatId || !$message) {
                $reminder->update(['status' => 'failed', 'error_message' => 'Missing chat_id or message']);
                $failed++;
                continue;
            }

            try {
                $sentSuccessfully = $sender->sendToGroup(
                    $reminder->tenant_id,
                    $chatId,
                    $message
                );

                if (!$sentSuccessfully) {
                    throw new \RuntimeException('Telegram send failed. Check Laravel logs for details.');
                }

                $reminder->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
                $sent++;
            } catch (\Exception $e) {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
                $failed++;
                Log::error("Scheduled reminder #{$reminder->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$due->count()} scheduled reminders: {$sent} sent, {$failed} failed.");
        return self::SUCCESS;
    }
}
