<?php

namespace App\Services\Telegram\Commands;

use App\Models\Loan;
use App\Models\Tenant;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;
use Illuminate\Support\Facades\Cache;

class CancelLoanCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    /**
     * Usage: /cancelloan 45
     * Requires confirmation: admin must reply YES within 60 seconds.
     * Uses a cache key to track pending confirmations.
     */
    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized.");
            return;
        }

        $text  = data_get($message, 'text', '');
        $parts = preg_split('/\s+/', trim($text));

        // Check for pending confirmation: /cancelloan YES
        $confirmKey = "cancel_loan:{$tenant->id}:{$userId}";
        $pendingLoanId = Cache::get($confirmKey);

        if ($pendingLoanId && strtoupper($parts[1] ?? '') === 'YES') {
            $this->executeCancel($tenant, $chatId, $userId, $pendingLoanId);
            Cache::forget($confirmKey);
            return;
        }

        $loanId = (int) ($parts[1] ?? 0);

        if (!$loanId) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "📝 Usage: <code>/cancelloan LOAN_ID</code>\n\nExample: <code>/cancelloan 45</code>");
            return;
        }

        $loan = Loan::where('tenant_id', $tenant->id)
            ->where('id', $loanId)
            ->with('borrower')
            ->first();

        if (!$loan) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ Loan \\#{$loanId} not found.");
            return;
        }

        if (in_array($loan->status, ['cancelled', 'paid'])) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "⚠️ Loan \\#{$loanId} is already <b>{$loan->status}</b>. Cannot cancel.");
            return;
        }

        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';
        $borrowerName = $this->esc($loan->borrower->name ?? 'Unknown');

        // Store pending confirmation (expires in 60 seconds)
        Cache::put($confirmKey, $loanId, 60);

        $this->sender->sendToGroup($tenant->id, $chatId,
            "⚠️ <b>Confirm Cancellation</b>\n\n"
            . "Loan \\#{$loanId}\n"
            . "👤 Borrower: {$borrowerName}\n"
            . "💰 Balance: {$currency}{$loan->balance}\n\n"
            . "Type <code>/cancelloan YES</code> within 60 seconds to confirm.");
    }

    private function executeCancel(Tenant $tenant, string $chatId, string $userId, int $loanId): void
    {
        $loan = Loan::where('tenant_id', $tenant->id)
            ->where('id', $loanId)
            ->first();

        if (!$loan || in_array($loan->status, ['cancelled', 'paid'])) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ Cannot cancel this loan.");
            return;
        }

        $loan->update(['status' => 'cancelled']);

        $this->sender->sendToGroup($tenant->id, $chatId,
            "✅ <b>Loan \\#{$loanId} Cancelled</b>\n\nThis loan has been cancelled and reminders stopped.");
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
