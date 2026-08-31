<?php

namespace App\Jobs\Telegram;

use App\Models\Reminder;
use App\Services\Telegram\TelegramSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendScheduledReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private int $reminderId)
    {
        $this->onQueue('reminders');
    }

    public function handle(TelegramSender $sender): void
    {
        $reminder = Reminder::find($this->reminderId);

        if (!$reminder || $reminder->status !== 'scheduled') {
            return;
        }

        if ($reminder->scheduled_at && $reminder->scheduled_at->lt(now()->subDay())) {
            $reminder->update([
                'status' => 'failed',
                'error_message' => 'Scheduled reminder was missed by more than 24 hours.',
            ]);
            return;
        }

        $claimed = Reminder::whereKey($reminder->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'pending']);

        if (!$claimed) {
            return;
        }

        $chatId = $reminder->telegram_chat_id;
        $message = $reminder->rendered_message ?? $reminder->message_snapshot;

        if (!$chatId || !$message) {
            $reminder->update(['status' => 'failed', 'error_message' => 'Missing chat_id or message']);
            return;
        }

        $sent = $sender->sendToGroup($reminder->tenant_id, $chatId, $message);

        $reminder->update($sent
            ? ['status' => 'sent', 'sent_at' => now(), 'error_message' => null]
            : ['status' => 'failed', 'error_message' => 'Telegram send failed. Check Laravel logs for details.']
        );
    }
}
