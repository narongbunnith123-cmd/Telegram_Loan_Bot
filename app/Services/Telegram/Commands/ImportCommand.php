<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class ImportCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    /**
     * Admin types /import → Bot asks to forward a message.
     */
    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized.");
            return;
        }

        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)->first();

        if (!$group) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ Group not registered.");
            return;
        }

        // Create import session (5 minute TTL)
        $cacheKey = "import_session:{$tenant->id}:{$userId}:{$chatId}";
        cache()->put($cacheKey, [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(5));

        $this->sender->sendToGroup($tenant->id, $chatId,
            "📥 <b>Import Borrower via Forward</b>\n\n"
            . "Forward a message from the person you want to register as a borrower.\n\n"
            . "⏱ You have 5 minutes.\n\n"
            . "💡 _The forwarded message must be from the borrower's actual Telegram account._");
    }

    /**
     * Handle the forwarded message — create a draft borrower.
     */
    public function handleForward(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        $cacheKey = "import_session:{$tenant->id}:{$userId}:{$chatId}";
        $session = cache()->pull($cacheKey); // consume the session

        if (!$session) return;

        // Try to extract forward_from (blocked by privacy if not available)
        $forwardFrom = data_get($message, 'forward_from');
        $forwardName = data_get($message, 'forward_sender_name');

        if ($forwardFrom) {
            // Privacy allows — we get full info
            $telegramId = (string) data_get($forwardFrom, 'id');
            $username   = data_get($forwardFrom, 'username');
            $firstName  = data_get($forwardFrom, 'first_name', '');
            $lastName   = data_get($forwardFrom, 'last_name', '');
            $fullName   = trim("{$firstName} {$lastName}");

            // Duplicate check
            $existing = Borrower::where('tenant_id', $tenant->id)
                ->where('telegram_user_id', $telegramId)->first();

            if ($existing) {
                $this->sender->sendToGroup($tenant->id, $chatId,
                    "⚠️ <b>Borrower Already Exists!</b>\n\n"
                    . "👤 {$this->esc($existing->name)}\n"
                    . "📱 @{$this->esc($existing->telegram_username ?? 'N/A')}\n"
                    . "🔗 Code: <code>{$this->esc($existing->borrower_code ?? 'N/A')}</code>\n\n"
                    . "Use <code>/newloan</code> to create a loan for this borrower.");
                return;
            }

            // Create borrower with full Telegram info
            $borrower = Borrower::create([
                'tenant_id'           => $tenant->id,
                'name'                => $fullName ?: 'Unknown',
                'telegram_user_id'    => $telegramId,
                'telegram_username'   => $username,
                'borrower_code'       => Borrower::generateCode(),
                'verification_status' => 'linked',
                'onboarding_source'   => 'telegram_forward',
                'linked_at'           => now(),
                'created_by'          => null,
                'status'              => 'active',
            ]);

            $this->sender->sendToGroup($tenant->id, $chatId,
                "✅ <b>Borrower Imported Successfully!</b>\n\n"
                . "👤 Name: {$this->esc($borrower->name)}\n"
                . "📱 Telegram: @{$this->esc($username ?? 'N/A')}\n"
                . "🔗 Status: 🟢 Linked\n"
                . "🏷 Code: <code>{$this->esc($borrower->borrower_code)}</code>\n\n"
                . "Use <code>/newloan @{$this->esc($username ?? $borrower->name)} amount interest days</code> to create a loan.");

        } else {
            // Privacy blocked — only get forward_sender_name (string)
            $name = $forwardName ?: 'Unknown Borrower';

            $borrower = Borrower::create([
                'tenant_id'           => $tenant->id,
                'name'                => $name,
                'borrower_code'       => Borrower::generateCode(),
                'verification_status' => 'pending',
                'onboarding_source'   => 'telegram_forward',
                'status'              => 'active',
            ]);

            $deepLink = $borrower->deep_link ?? 'N/A';

            $this->sender->sendToGroup($tenant->id, $chatId,
                "⚠️ <b>Borrower Created (Privacy Limited)</b>\n\n"
                . "👤 Name: {$this->esc($name)}\n"
                . "📱 Telegram: Not available (privacy blocked)\n"
                . "🔗 Status: 🟡 Pending\n"
                . "🏷 Code: <code>{$this->esc($borrower->borrower_code)}</code>\n\n"
                . "Share this link with the borrower to complete linking:\n"
                . "<code>{$this->esc($deepLink)}</code>");
        }
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
