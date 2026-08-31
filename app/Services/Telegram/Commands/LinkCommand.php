<?php

namespace App\Services\Telegram\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class LinkCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');
        $args   = trim(str_replace('/link', '', data_get($message, 'text', '')));

        // If already linked
        if ($this->guard->isAdmin($tenant, $userId)) {
            $admin = $this->guard->getAdmin($tenant, $userId);
            $this->sender->sendToGroup($tenant->id, $chatId,
                "✅ You are already linked as <b>{$this->esc($admin->name)}</b>.");
            return;
        }

        // If a code was provided: /link ABC123
        if (!empty($args)) {
            $code = strtoupper(trim($args));
            $user = $this->guard->linkWithCode($code, $userId);

            if ($user) {
                $this->sender->sendToGroup($tenant->id, $chatId,
                    "🔗 <b>Account Linked Successfully!</b>\n\n"
                    . "Welcome, <b>{$this->esc($user->name)}</b>! You now have admin access.\n\n"
                    . "Type /adminhelp to see admin commands.");
            } else {
                $this->sender->sendToGroup($tenant->id, $chatId,
                    "❌ Invalid or expired code. Please generate a new code from the web dashboard.");
            }
            return;
        }

        // No code provided — show instructions
        $this->sender->sendToGroup($tenant->id, $chatId,
            "🔗 <b>Link Your Telegram Account</b>\n\n"
            . "1. Log in to the web dashboard\n"
            . "2. Go to Bot Setup → Link Telegram\n"
            . "3. Click \"Generate Link Code\"\n"
            . "4. Come back here and type:\n\n"
            . "<code>/link YOUR_CODE</code>\n\n"
            . "The code expires in 10 minutes.");
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
