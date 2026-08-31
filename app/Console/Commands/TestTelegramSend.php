<?php

namespace App\Console\Commands;

use App\Models\BotToken;
use App\Models\Loan;
use App\Services\Telegram\TelegramSender;
use Illuminate\Console\Command;

class TestTelegramSend extends Command
{
    protected $signature = 'test:telegram-send';
    protected $description = 'Test sending a Telegram message for the latest loan';

    public function handle(TelegramSender $sender): void
    {
        $loan = Loan::with(['borrower', 'group'])->where('status', '!=', 'cancelled')->latest()->first();

        if (!$loan) {
            $this->error('No loans found.');
            return;
        }

        $this->info("Loan #{$loan->id}: {$loan->borrower->name}");
        $this->info("Group: {$loan->group->name} (chat_id: {$loan->group->telegram_group_id})");
        $this->info("Tenant: {$loan->tenant_id}");

        $botToken = BotToken::where('tenant_id', $loan->tenant_id)->first();
        if (!$botToken) {
            $this->error("No bot token for tenant {$loan->tenant_id}!");
            return;
        }
        $this->info("Bot token: " . substr($botToken->token, 0, 10) . '...');

        // Test via TelegramSender (which now has SSL fix)
        $message = "🧪 <b>Test Message</b>\n\nThis is a test from the loan system.\nLoan #{$loan->id} - {$loan->borrower->name}\nBalance: $" . number_format($loan->balance, 2);

        $this->info("\nSending to chat_id: {$loan->group->telegram_group_id}");

        $result = $sender->sendToGroup($loan->tenant_id, $loan->group->telegram_group_id, $message);
        $this->info($result ? '✅ SUCCESS! Check your Telegram group.' : '❌ FAILED. Check storage/logs/laravel.log');
    }
}
