<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\BorrowerService;
use App\Services\LoanService;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class NewLoanCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
        private LoanService $loanService,
        private BorrowerService $borrowerService,
    ) {
    }

    /**
     * Usage: /newloan @username 500 2 30
     * Args:  borrower   principal interest days
     */
    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized to use admin commands.");
            return;
        }

        $text = data_get($message, 'text', '');
        $parts = preg_split('/\s+/', trim($text));
        // parts[0] = /newloan, [1] = @username, [2] = principal, [3] = interest, [4] = days

        if (count($parts) < 4) {
            $this->sender->sendToGroup(
                $tenant->id,
                $chatId,
                "📝 <b>Create New Loan</b>\n\n"
                . "Usage: <code>/newloan @username amount interest [days]</code>\n\n"
                . "Example: <code>/newloan @john 500 2 30</code>\n"
                . "Creates: \$500 loan at 2%/day for 30 days\n\n"
                . "<b>No days = no end date</b> (interest-only):\n"
                . "<code>/newloan @john 500 2</code>\n\n"
                . "Interest defaults to percentage. Add <code>f</code> for fixed:\n"
                . "<code>/newloan @john 500 5f 30</code> \= \$5/day fixed"
            );
            return;
        }

        $usernameRaw = $parts[1];
        $principal = (float) $parts[2];
        $interestRaw = $parts[3];
        $days = isset($parts[4]) ? (int) $parts[4] : null;  // null = no end date

        // Validate amounts
        if ($principal <= 0 || ($days !== null && $days <= 0)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ Invalid amount or days. Please enter numbers only.");
            return;
        }

        // Parse interest type
        $interestType = 'percentage';
        $interestValue = (float) $interestRaw;
        if (str_ends_with(strtolower($interestRaw), 'f')) {
            $interestType = 'fixed';
            $interestValue = (float) rtrim($interestRaw, 'fF');
        }

        // Find group
        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)
            ->where('status', 'active')
            ->first();

        if (!$group) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ This group is not active or not registered.");
            return;
        }

        // Find borrower using shared BorrowerService
        $borrower = $this->borrowerService->findByIdentifier($tenant->id, $usernameRaw);

        if (!$borrower) {
            $username = ltrim($usernameRaw, '@');
            $this->sender->sendToGroup(
                $tenant->id,
                $chatId,
                "❌ Borrower <code>@{$this->esc($username)}</code> not found.\n"
                . "Use <code>/adduser @{$this->esc($username)} Full Name</code> to register them first."
            );
            return;
        }

        // Create loan using shared LoanService — same logic as web dashboard
        $admin = $this->guard->getAdmin($tenant, $userId);

        $loan = $this->loanService->createLoan(
            tenantId: $tenant->id,
            data: [
                'group_id' => $group->id,
                'borrower_id' => $borrower->id,
                'principal' => $principal,
                'interest_type' => $interestType,
                'interest_value' => $interestValue,
                'loan_type' => 'lump_sum',
                'loan_date' => now()->toDateString(),
                'due_date' => $days ? now()->addDays($days)->toDateString() : null,
            ],
            createdBy: $admin,
        );

        $currency = data_get($group->settings, 'currency', '$');
        $intLabel = $interestType === 'fixed'
            ? "{$currency}{$interestValue}/day"
            : "{$interestValue}%/day";

        $confirmMsg = "✅ <b>Loan #{$loan->id} Created</b>\n\n"
            . "👤 Borrower: @{$this->esc($borrower->telegram_username ?? $borrower->name)}\n"
            . "💰 Amount: {$currency}{$principal}\n"
            . "📈 Interest: {$intLabel}\n";

        if ($days) {
            $confirmMsg .= "📅 Due: {$loan->due_date->format('d M Y')}\n"
                . "⏱ Term: {$days} days";
        } else {
            $confirmMsg .= "📅 No end date (interest-only loan)";
        }

        $this->sender->sendToGroup($tenant->id, $chatId, $confirmMsg);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
