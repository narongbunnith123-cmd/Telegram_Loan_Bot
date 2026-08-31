<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Tenant;
use App\Services\Telegram\TelegramSender;

class BalanceCommand
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
            $this->sender->sendToGroup($tenant->id, $chatId,
                "❌ You are not registered as a borrower.");
            return;
        }

        $loans = Loan::where('borrower_id', $borrower->id)
            ->whereIn('status', ['active', 'overdue'])
            ->get();

        if ($loans->isEmpty()) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "✅ You have no outstanding balance.");
            return;
        }

        $settings = is_array($loans->first()->group->settings)
            ? $loans->first()->group->settings : [];
        $currency = $settings['currency'] ?? '$';
        $total    = $loans->sum('balance');

        $this->sender->sendToGroup($tenant->id, $chatId,
            "💰 *Your Total Balance*\n\n"
            . "Outstanding: {$currency}{$total}\n"
            . "Active Loans: {$loans->count()}");
    }
}
