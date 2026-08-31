<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Tenant;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class StartCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');
        $text   = data_get($message, 'text', '');

        // Handle deep link: /start BRW-XXXXXX (borrower self-registration)
        $parts   = explode(' ', $text, 2);
        $payload = $parts[1] ?? null;

        if ($payload && str_starts_with($payload, 'BRW-')) {
            $this->handleDeepLink($tenant, $message, $payload);
            return;
        }

        // Normal /start or /help — show command list
        $this->showHelp($tenant, $message);
    }

    /**
     * Handle deep link self-registration: /start BRW-A3F72B
     */
    private function handleDeepLink(Tenant $tenant, array $message, string $code): void
    {
        $userId   = (string) data_get($message, 'from.id');
        $chatId   = (string) data_get($message, 'chat.id');
        $username = data_get($message, 'from.username');
        $firstName = data_get($message, 'from.first_name', '');

        $borrower = Borrower::where('tenant_id', $tenant->id)
            ->where('borrower_code', $code)
            ->first();

        if (!$borrower) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "❌ Invalid or expired link code: <code>{$this->esc($code)}</code>");
            return;
        }

        if ($borrower->isLinked()) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "✅ This account is already linked to <b>{$this->esc($borrower->name)}</b>.");
            return;
        }

        // Check if this telegram_user_id is already linked to another borrower
        $existingLink = Borrower::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $userId)
            ->where('id', '!=', $borrower->id)
            ->first();

        if ($existingLink) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "⚠️ Your Telegram is already linked to <b>{$this->esc($existingLink->name)}</b>. Contact admin if this is wrong.");
            return;
        }

        // Link the borrower
        $borrower->update([
            'telegram_user_id'    => $userId,
            'telegram_username'   => $username,
            'verification_status' => 'linked',
            'linked_at'           => now(),
        ]);

        $this->sender->sendToGroup($tenant->id, $chatId,
            "✅ <b>Account Linked Successfully!</b>\n\n"
            . "👤 Name: {$this->esc($borrower->name)}\n"
            . "📱 Telegram: @{$this->esc($username ?? $firstName)}\n"
            . "🔗 Code: <code>{$this->esc($code)}</code>\n\n"
            . "You can now use:\n"
            . "/myloan — View your loans\n"
            . "/balance — Check your balance\n"
            . "/pay — Submit payment proof");
    }

    private function showHelp(Tenant $tenant, array $message): void
    {
        $userId  = (string) data_get($message, 'from.id');
        $chatId  = (string) data_get($message, 'chat.id');
        $isAdmin = $this->guard->isAdmin($tenant, $userId);

        $text = "👋 <b>Welcome to the Loan Management Bot!</b>\n\n";

        // Borrower commands
        $text .= "📋 <b>Borrower Commands:</b>\n"
            . "/myloan — View your loan summary\n"
            . "/balance — Check your current balance\n"
            . "/pay — Submit a payment proof\n"
            . "/statement — View payment history\n\n";

        if ($isAdmin) {
            $admin = $this->guard->getAdmin($tenant, $userId);
            $text .= "🔑 <b>Admin Commands</b> (linked as {$this->esc($admin->name)}):\n"
                . "/newloan — Create a new loan\n"
                . "/adduser — Register a borrower\n"
                . "/import — Import borrower via forward\n"
                . "/approve — Approve a payment\n"
                . "/reject — Reject a payment\n"
                . "/loans — List active loans\n"
                . "/overdue — View overdue loans\n"
                . "/cancelloan — Cancel a loan\n"
                . "/groupstats — Group summary\n"
                . "/settings — Configure group settings\n"
                . "/adminhelp — Show admin commands\n";
        } else {
            $text .= "🔗 <b>Admin?</b> Use <code>/link CODE</code> to connect your account.";
        }

        $this->sender->sendToGroup($tenant->id, $chatId, $text);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
