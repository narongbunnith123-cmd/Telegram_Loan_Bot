<?php

namespace App\Services\Telegram\Conversations;

use App\Models\Loan;
use App\Models\TelegramGroup;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Services\Telegram\TelegramSender;

class OverdueConversation extends BaseConversation
{
    public function __construct(
        private TelegramSender $sender,
    ) {
    }

    public static function action(): string
    {
        return 'overdue_check';
    }

    public function firstStep(): string
    {
        return 'show_overdue';
    }

    public function steps(): array
    {
        return ['show_overdue'];
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
        return $this->checkOverdue($tenant);
    }

    public function handleCallback(
        Tenant $tenant,
        TelegramSession $session,
        string $callbackData,
        array $callbackQuery,
    ): ConversationResult {
        return $this->checkOverdue($tenant);
    }

    /**
     * Generate overdue loans summary across all groups for this tenant.
     */
    public function checkOverdue(Tenant $tenant): ConversationResult
    {
        $loans = Loan::where('tenant_id', $tenant->id)
            ->whereIn('status', ['overdue', 'active'])
            ->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'active')
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', today());
                    });
            })
            ->with(['borrower', 'group'])
            ->orderBy('due_date')
            ->get();

        if ($loans->isEmpty()) {
            return ConversationResult::complete(
                "🎉 <b>No Overdue Loans!</b>\n\n"
                . "All loans are on track. Great job! 👏"
            );
        }

        $msg = "🔴 <b>Overdue Loans</b> ({$loans->count()} total)\n\n";

        $totalOutstanding = 0;
        $totalDailyInterest = 0;

        foreach ($loans as $i => $loan) {
            $num = $i + 1;
            $borrower = $loan->borrower;
            $group = $loan->group;

            $name = $borrower->telegram_username
                ? "@{$this->esc($borrower->telegram_username)}"
                : $this->esc($borrower->name);

            $interest = $loan->calculateDailyInterest();
            $daysOverdue = $loan->days_overdue;
            $totalOutstanding += $loan->balance;
            $totalDailyInterest += $interest;

            $groupName = $group ? $this->esc($group->name) : 'N/A';
            $dueLabel = $loan->due_date
                ? $loan->due_date->format('d M Y')
                : 'No end date';

            $msg .= "{$num}. {$name}\n"
                . "   📁 {$groupName}\n"
                . "   💰 Balance: $" . number_format($loan->balance, 2) . "\n"
                . "   ⏰ " . ($daysOverdue > 0 ? "{$daysOverdue} days overdue" : "Active") . "\n"
                . "   📅 Due: {$dueLabel}\n"
                . "   📈 +$" . number_format($interest, 2) . "/day\n\n";
        }

        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💰 Total Outstanding: <b>$" . number_format($totalOutstanding, 2) . "</b>\n"
            . "📈 Daily Interest: <b>$" . number_format($totalDailyInterest, 2) . "</b>";

        return ConversationResult::complete($msg);
    }
}
