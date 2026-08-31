<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Tenant;
use App\Services\Telegram\TelegramSender;

class PayCommand
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

        $loan = Loan::where('borrower_id', $borrower->id)
            ->whereIn('status', ['active', 'overdue'])
            ->latest()
            ->first();

        if (!$loan) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "✅ You have no active loans to pay.");
            return;
        }

        $this->sender->sendToGroup($tenant->id, $chatId,
            "📤 *Submit Payment Proof*\n\n"
            . "Please send a screenshot or photo of your payment receipt as a reply to this message.\n\n"
            . "An admin will review and confirm your payment.");
    }
}
