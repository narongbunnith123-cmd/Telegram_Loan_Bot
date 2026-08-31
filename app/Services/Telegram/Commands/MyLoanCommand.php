<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Tenant;
use App\Services\Telegram\TelegramSender;

class MyLoanCommand
{
    public function __construct(private TelegramSender $sender) {}

    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        $borrower = Borrower::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $userId)
            ->first();

        if (!$borrower) {
            $this->sender->sendToGroup(
                $tenant->id, $chatId,
                "❌ You are not registered as a borrower in this group."
            );
            return;
        }

        $loan = Loan::where('borrower_id', $borrower->id)
            ->whereIn('status', ['active', 'overdue'])
            ->latest()
            ->first();

        if (!$loan) {
            $this->sender->sendToGroup(
                $tenant->id, $chatId,
                "✅ You have no active loans. Great job!"
            );
            return;
        }

        $group    = $loan->group;
        $settings = is_array($group->settings) ? $group->settings : [];
        $currency = $settings['currency'] ?? '$';
        $interest = $loan->calculateDailyInterest();

        $statusEmoji = $loan->status === 'overdue' ? '🔴' : '🟡';

        $text = "📋 *Your Loan Summary*\n\n"
            . "{$statusEmoji} Status: {$loan->status}\n"
            . "💰 Balance: {$currency}{$loan->balance}\n"
            . ($loan->due_date ? "📅 Due Date: {$loan->due_date->format('d M Y')}\n" : "")
            . "📈 Daily Interest: {$currency}{$interest}\n"
            . ($loan->days_overdue > 0 ? "⏰ Unpaid: {$loan->days_overdue} days\n" : "")
            . "\nUse /pay to submit a payment proof.";

        $this->sender->sendToGroup($tenant->id, $chatId, $text);
    }
}
