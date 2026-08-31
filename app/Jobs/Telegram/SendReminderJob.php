<?php

namespace App\Jobs\Telegram;

use App\Models\Loan;
use App\Models\Reminder;
use App\Services\Telegram\TelegramSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300; // 5 minutes between retries

    public function __construct(
        private Loan $loan,
        private string $type = 'overdue',
        private ?int $reminderId = null,
    ) {}

    /**
     * Unique job ID to prevent duplicate sends on retry.
     */
    public function uniqueId(): string
    {
        return "reminder:{$this->loan->id}:{$this->type}:{$this->reminderId}";
    }

    public function handle(TelegramSender $sender): void
    {
        $loan = $this->loan->fresh(['borrower', 'group']);

        if (!$loan || !$loan->borrower || !$loan->group) return;

        // Don't send if loan status changed while job was queued
        if (!in_array($loan->status, ['active', 'overdue'])) {
            $this->markSkipped('Loan status changed');
            return;
        }

        // Load the pre-created reminder record
        $reminder = $this->reminderId ? Reminder::find($this->reminderId) : null;

        if ($reminder && $reminder->status === 'sent') {
            return; // Already sent — idempotency
        }

        // Get the message (from reminder record or build inline)
        $message = $reminder?->rendered_message
            ?? $reminder?->message_snapshot
            ?? $this->buildFallbackMessage($loan);

        $targetType = $reminder?->target_type ?? 'group';

        try {
            if ($targetType === 'dm' && $loan->borrower->telegram_user_id) {
                // Try DM first
                $sender->sendToDM(
                    $loan->tenant_id,
                    $loan->borrower->telegram_user_id,
                    $message
                );
            } else {
                // Send to group
                $sender->sendToGroup(
                    $loan->tenant_id,
                    $loan->group->telegram_group_id,
                    $message
                );
            }

            // Mark as sent
            if ($reminder) {
                $reminder->update(['status' => 'sent', 'sent_at' => now()]);
            } else {
                // Legacy mode: create reminder record
                Reminder::create([
                    'tenant_id'        => $loan->tenant_id,
                    'loan_id'          => $loan->id,
                    'borrower_id'      => $loan->borrower_id,
                    'type'             => $this->type,
                    'target_type'      => 'group',
                    'message_snapshot' => $message,
                    'rendered_message' => $message,
                    'scheduled_at'     => now(),
                    'sent_at'          => now(),
                    'status'           => 'sent',
                ]);
            }
        } catch (\Exception $e) {
            if ($reminder) {
                $reminder->update([
                    'status'        => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
            }

            Log::error("Reminder send failed for loan #{$loan->id}: {$e->getMessage()}");
            throw $e; // Retry via queue
        }
    }

    /**
     * Mark reminder as skipped.
     */
    private function markSkipped(string $reason): void
    {
        if ($this->reminderId) {
            Reminder::where('id', $this->reminderId)
                ->update(['status' => 'skipped', 'error_message' => $reason]);
        }
    }

    /**
     * Fallback message builder for legacy mode (no template/rule).
     */
    private function buildFallbackMessage(Loan $loan): string
    {
        $borrower = $loan->borrower;
        $username = $borrower->telegram_username
            ? "@{$borrower->telegram_username}"
            : $borrower->name;

        $settings = is_array($loan->group->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        if ($this->type === 'approaching') {
            $daysLeft = $loan->due_date ? max(0, (int) now()->diffInDays($loan->due_date, false)) : 0;
            $urgency = $daysLeft <= 1 ? '🔴' : ($daysLeft <= 2 ? '🟠' : '🟡');

            return "⏰ <b>Payment Due Soon</b>\n\n"
                . "{$urgency} {$username}, your payment is due "
                . ($daysLeft === 0 ? '<b>today</b>' : "in <b>{$daysLeft} day" . ($daysLeft > 1 ? 's' : '') . "</b>")
                . "!\n\n"
                . "💰 Balance: {$currency}" . number_format($loan->balance, 2) . "\n"
                . ($loan->due_date ? "📆 Due Date: {$loan->due_date->format('d M Y')}\n\n" : "\n")
                . "Use /pay to submit payment proof.";
        }

        return "⚠️ <b>Loan Reminder</b>\n\n"
            . "{$username} has an outstanding payment.\n\n"
            . "💰 Balance: {$currency}" . number_format($loan->balance, 2) . "\n"
            . ($loan->days_overdue > 0 ? "📅 Unpaid: {$loan->days_overdue} days\n" : "")
            . "📈 Today's Interest: {$currency}" . number_format($loan->calculateDailyInterest(), 2) . "\n"
            . ($loan->due_date ? "📆 Due Date: {$loan->due_date->format('d M Y')}\n\n" : "\n")
            . "Please settle your payment as soon as possible.";
    }
}
