<?php

namespace App\Services\Telegram\Commands;

use App\Models\Loan;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class LoansListCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    /**
     * Usage: /loans          — all active/overdue
     *        /loans overdue  — only overdue
     *        /loans paid     — only paid
     */
    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized.");
            return;
        }

        $text   = data_get($message, 'text', '');
        $parts  = preg_split('/\s+/', trim($text));
        $filter = strtolower($parts[1] ?? '');

        // Find group for this chat
        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)
            ->first();

        if (!$group) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ This group is not registered.");
            return;
        }

        $settings = is_array($group->settings) ? $group->settings : [];
        $currency = $settings['currency'] ?? '$';

        $query = Loan::where('tenant_id', $tenant->id)
            ->where('group_id', $group->id)
            ->with('borrower');

        if ($filter === 'overdue') {
            $query->where('status', 'overdue');
            $title = 'Overdue Loans';
        } elseif ($filter === 'paid') {
            $query->where('status', 'paid');
            $title = 'Paid Loans';
        } else {
            $query->whereIn('status', ['active', 'overdue']);
            $title = 'Active Loans';
        }

        $loans = $query->orderByDesc('created_at')->limit(15)->get();

        if ($loans->isEmpty()) {
            $this->sender->sendToGroup($tenant->id, $chatId, "📋 No {$this->esc(strtolower($title))} found in this group.");
            return;
        }

        $msg = "📋 <b>{$this->esc($title)}</b> ({$loans->count()} shown)\n\n";

        foreach ($loans as $i => $loan) {
            $num = $i + 1;
            $emoji = $loan->status === 'overdue' ? '🔴' : ($loan->status === 'paid' ? '🟢' : '🟡');
            $borrower = $loan->borrower;
            $name = $borrower->telegram_username
                ? "@{$this->esc($borrower->telegram_username)}"
                : $this->esc($borrower->name);

            $msg .= "{$num}. {$emoji} {$name} — {$currency}{$loan->balance}";

            if ($loan->status === 'overdue') {
                $msg .= " (overdue {$loan->days_overdue}d)";
            }

            $msg .= " \\[\\#{$loan->id}\\]\n";
        }

        $totalBalance = $loans->sum('balance');
        $msg .= "\n💰 Total: {$currency}{$totalBalance}";

        $this->sender->sendToGroup($tenant->id, $chatId, $msg);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
