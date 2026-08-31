<?php

namespace App\Services\Telegram\Commands;

use App\Models\Loan;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class OverdueCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized.");
            return;
        }

        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)
            ->first();

        if (!$group) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ Group not registered.");
            return;
        }

        $settings = is_array($group->settings) ? $group->settings : [];
        $currency = $settings['currency'] ?? '$';

        $loans = Loan::where('tenant_id', $tenant->id)
            ->where('group_id', $group->id)
            ->where('status', 'overdue')
            ->with('borrower')
            ->orderByDesc('due_date')
            ->get();

        if ($loans->isEmpty()) {
            $this->sender->sendToGroup($tenant->id, $chatId, "🎉 No overdue loans! Everything is on track.");
            return;
        }

        $msg = "🔴 <b>Overdue Loans</b> ({$loans->count()} total)\n\n";

        $totalInterest = 0;
        foreach ($loans as $i => $loan) {
            $num = $i + 1;
            $borrower = $loan->borrower;
            $name = $borrower->telegram_username
                ? "@{$this->esc($borrower->telegram_username)}"
                : $this->esc($borrower->name);
            $interest = $loan->calculateDailyInterest();
            $totalInterest += $interest;

            $daysLabel = $loan->days_overdue > 0 ? "{$loan->days_overdue}d unpaid" : "active";

            $msg .= "{$num}. {$name}\n"
                . "   💰 {$currency}{$loan->balance} · ⏰ {$daysLabel} · 📈 \\+{$currency}{$interest}/day\n\n";
        }

        $totalBalance = $loans->sum('balance');
        $msg .= "💰 Total Outstanding: {$currency}{$totalBalance}\n"
            . "📈 Daily Interest: {$currency}{$totalInterest}";

        $this->sender->sendToGroup($tenant->id, $chatId, $msg);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
