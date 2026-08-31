<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class GroupStatsCommand
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

        // Borrower stats
        $totalBorrowers    = Borrower::where('tenant_id', $tenant->id)->where('group_id', $group->id)->count();
        $blacklisted       = Borrower::where('tenant_id', $tenant->id)->where('group_id', $group->id)->where('status', 'blacklisted')->count();

        // Loan stats
        $activeLoans   = Loan::where('tenant_id', $tenant->id)->where('group_id', $group->id)->where('status', 'active')->count();
        $overdueLoans  = Loan::where('tenant_id', $tenant->id)->where('group_id', $group->id)->where('status', 'overdue')->count();
        $paidLoans     = Loan::where('tenant_id', $tenant->id)->where('group_id', $group->id)->where('status', 'paid')->count();

        // Balance
        $totalOutstanding = Loan::where('tenant_id', $tenant->id)
            ->where('group_id', $group->id)
            ->whereIn('status', ['active', 'overdue'])
            ->sum('balance');

        // Daily interest
        $dailyInterest = 0;
        $activeLoansCollection = Loan::where('tenant_id', $tenant->id)
            ->where('group_id', $group->id)
            ->whereIn('status', ['active', 'overdue'])
            ->get();
        foreach ($activeLoansCollection as $loan) {
            $dailyInterest += $loan->calculateDailyInterest();
        }

        // This month payments
        $collectedThisMonth = Payment::where('tenant_id', $tenant->id)
            ->whereHas('loan', fn($q) => $q->where('group_id', $group->id))
            ->where('status', 'approved')
            ->whereMonth('approved_at', now()->month)
            ->whereYear('approved_at', now()->year)
            ->sum('amount');

        $pendingPayments = Payment::where('tenant_id', $tenant->id)
            ->whereHas('loan', fn($q) => $q->where('group_id', $group->id))
            ->where('status', 'pending')
            ->count();

        $groupName = $this->esc($group->name);

        $msg = "📊 <b>Group Stats — {$groupName}</b>\n\n"
            . "👥 Borrowers: {$totalBorrowers}";
        if ($blacklisted > 0) {
            $msg .= " ({$blacklisted} blacklisted)";
        }
        $msg .= "\n"
            . "🟡 Active Loans: {$activeLoans}\n"
            . "🔴 Overdue: {$overdueLoans}\n"
            . "🟢 Paid: {$paidLoans}\n\n"
            . "💰 Total Outstanding: {$currency}" . number_format($totalOutstanding, 2) . "\n"
            . "📈 Daily Interest: {$currency}" . number_format($dailyInterest, 2) . "\n"
            . "✅ Collected This Month: {$currency}" . number_format($collectedThisMonth, 2) . "\n";

        if ($pendingPayments > 0) {
            $msg .= "⏳ Pending Approvals: {$pendingPayments}";
        }

        $this->sender->sendToGroup($tenant->id, $chatId, $msg);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
