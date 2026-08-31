<?php

namespace App\Services\Telegram\Conversations;

use App\Models\Loan;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Services\ReminderEngine;
use App\Services\Telegram\TelegramSender;

class SendReminderConversation extends BaseConversation
{
    public function __construct(
        private TelegramSender $sender,
        private ReminderEngine $reminderEngine,
    ) {
    }

    public static function action(): string
    {
        return 'send_reminder';
    }

    public function firstStep(): string
    {
        return 'send_now';
    }

    public function steps(): array
    {
        return ['send_now'];
    }

    public function previousStep(string $currentStep): ?string
    {
        return null;
    }

    public function handleStep(
        Tenant $tenant,
        TelegramSession $session,
        string $input,
        array $message,
    ): ConversationResult {
        return $this->sendReminders($tenant);
    }

    public function handleCallback(
        Tenant $tenant,
        TelegramSession $session,
        string $callbackData,
        array $callbackQuery,
    ): ConversationResult {
        return $this->sendReminders($tenant);
    }

    /**
     * Trigger the reminder engine manually and report results.
     */
    public function sendReminders(Tenant $tenant): ConversationResult
    {
        // Count eligible loans first
        $activeLoans = Loan::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'overdue'])
            ->where('balance', '>', 0)
            ->where('reminders_enabled', true)
            ->with(['borrower', 'group'])
            ->get();

        if ($activeLoans->isEmpty()) {
            return ConversationResult::complete(
                "📢 <b>No Reminders Needed</b>\n\n"
                . "There are no active loans requiring reminders."
            );
        }

        // Run the engine
        try {
            $result = $this->reminderEngine->processAll();
            $dispatched = $result['dispatched'] ?? 0;
            $skipped = $result['skipped'] ?? 0;

            if ($dispatched === 0 && $skipped === 0) {
                return ConversationResult::complete(
                    "📢 <b>Reminder Check Complete</b>\n\n"
                    . "📋 Active Loans: <b>{$activeLoans->count()}</b>\n"
                    . "✅ No reminders needed right now.\n\n"
                    . "ℹ️ Reminders are sent automatically based on:\n"
                    . "• Reminder rules & schedule\n"
                    . "• Group reminder time settings\n"
                    . "• Cooldown periods"
                );
            }

            $msg = "📢 <b>Reminders Sent!</b>\n\n"
                . "✅ Dispatched: <b>{$dispatched}</b>\n";

            if ($skipped > 0) {
                $msg .= "⏭ Skipped: <b>{$skipped}</b> (cooldown/rate limit)\n";
            }

            $msg .= "\n📋 Active Loans: {$activeLoans->count()}\n"
                . "🔴 Overdue: {$activeLoans->where('status', 'overdue')->count()}\n\n"
                . "✅ Reminders have been sent to their Telegram groups.";

            return ConversationResult::complete($msg);
        } catch (\Exception $e) {
            return ConversationResult::error(
                "❌ Error sending reminders: {$this->esc($e->getMessage())}"
            );
        }
    }
}
