<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Telegram\TelegramSender;

class StatementCommand
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
            ->with(['payments' => fn($q) => $q->where('status', 'approved')->latest()->limit(5)])
            ->latest()
            ->limit(3)
            ->get();

        if ($loans->isEmpty()) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "📋 No loan history found.");
            return;
        }

        $text = "📋 *Loan Statement*\n\n";

        foreach ($loans as $loan) {
            $settings = is_array($loan->group->settings) ? $loan->group->settings : [];
            $currency = $settings['currency'] ?? '$';

            $text .= "Loan \\#{$loan->id} \\| {$loan->status}\n";
            $text .= "Principal: {$currency}{$loan->principal} \\| Balance: {$currency}{$loan->balance}\n";

            foreach ($loan->payments as $payment) {
                $text .= "  ✅ Paid {$currency}{$payment->amount} on {$payment->approved_at?->format('d M Y')}\n";
            }
            $text .= "\n";
        }

        $this->sender->sendToGroup($tenant->id, $chatId, $text);
    }
}
