<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\BorrowerService;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class AddUserCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
        private BorrowerService $borrowerService,
    ) {
    }

    /**
     * Usage: /adduser @username Full Name Here
     * Or reply to a user's message: /adduser Full Name Here
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
        $parts = preg_split('/\s+/', trim($text), 3);
        // parts[0] = /adduser, [1] = @username, [2] = Full Name

        if (count($parts) < 3) {
            $this->sender->sendToGroup(
                $tenant->id,
                $chatId,
                "📝 <b>Register Borrower</b>\n\n"
                . "Usage: <code>/adduser @username Full Name</code>\n\n"
                . "Example: <code>/adduser @john John Doe</code>"
            );
            return;
        }

        $usernameRaw = $parts[1];
        $name = $parts[2];
        $username = ltrim($usernameRaw, '@');

        // Find group
        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)
            ->first();

        if (!$group) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ This group is not registered.");
            return;
        }

        // Try to capture telegram_user_id if the message is a reply
        $replyUserId = data_get($message, 'reply_to_message.from.id');

        $admin = $this->guard->getAdmin($tenant, $userId);

        // Use shared BorrowerService — same logic as web dashboard
        $result = $this->borrowerService->createBorrower(
            tenantId: $tenant->id,
            data: [
                'name' => $name,
                'telegram_username' => $username,
                'telegram_user_id' => $replyUserId ? (string) $replyUserId : null,
                'onboarding_source' => 'telegram_command',
            ],
            createdBy: $admin,
        );

        // Handle duplicates
        if (!empty($result['duplicates'])) {
            $existing = $result['duplicates'][0]['borrower'];
            $reason = $result['duplicates'][0]['reason'];

            $this->sender->sendToGroup(
                $tenant->id,
                $chatId,
                "⚠️ Borrower <code>@{$this->esc($username)}</code> already exists ({$this->esc($reason)}).\n\n"
                . "👤 Name: {$this->esc($existing->name)}\n"
                . "🏷 Code: <code>{$this->esc($existing->borrower_code ?? 'N/A')}</code>\n"
                . "Use <code>/newloan @{$this->esc($username)} amount interest days</code> to create a loan."
            );
            return;
        }

        $borrower = $result['borrower'];
        $isLinked = $borrower->isLinked();

        $msg = "✅ <b>Borrower Registered</b>\n\n"
            . "👤 Name: {$this->esc($name)}\n"
            . "📱 Telegram: @{$this->esc($username)}\n"
            . "🏷 Code: <code>{$this->esc($borrower->borrower_code)}</code>\n"
            . "🔗 Status: " . ($isLinked ? '🟢 Linked' : '🟡 Pending') . "\n\n";

        if (!$isLinked) {
            $deepLink = $borrower->deep_link;
            if ($deepLink) {
                $msg .= "📎 Share this link to complete linking:\n<code>{$this->esc($deepLink)}</code>\n\n";
            }
        }

        $msg .= "Use <code>/newloan @{$this->esc($username)} amount interest days</code> to create a loan.";

        $this->sender->sendToGroup($tenant->id, $chatId, $msg);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
