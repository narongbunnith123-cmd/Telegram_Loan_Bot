<?php

namespace App\Services\Telegram;

use App\Models\TelegramSession;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class ConversationManager
{
    private const SESSION_TIMEOUT_MINUTES = 10;

    public function __construct(
        private TelegramSender $sender,
    ) {
    }

    /**
     * Get the active session for a user, or null if none/expired.
     */
    public function getSession(int $tenantId, string $userId, string $chatId): ?TelegramSession
    {
        $session = TelegramSession::where('tenant_id', $tenantId)
            ->forUser($userId, $chatId)
            ->first();

        if (!$session)
            return null;

        // Auto-expire
        if ($session->isExpired()) {
            $session->clear()->save();
            return null;
        }

        return $session;
    }

    /**
     * Start a new conversation flow.
     */
    public function startConversation(
        int $tenantId,
        string $userId,
        string $chatId,
        string $action,
        string $firstStep,
        array $initialData = [],
    ): TelegramSession {
        // Upsert — clear any existing session
        $session = TelegramSession::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'telegram_user_id' => $userId,
                'telegram_chat_id' => $chatId,
            ],
            [
                'current_action' => $action,
                'current_step' => $firstStep,
                'temp_data' => $initialData,
                'expires_at' => now()->addMinutes(self::SESSION_TIMEOUT_MINUTES),
            ]
        );

        return $session;
    }

    /**
     * Advance the session to the next step.
     */
    public function advanceStep(TelegramSession $session, string $nextStep, array $mergeData = []): TelegramSession
    {
        if (!empty($mergeData)) {
            $session->mergeData($mergeData);
        }

        $session->advanceTo($nextStep)->save();

        return $session;
    }

    /**
     * Go back to a previous step.
     */
    public function goBack(TelegramSession $session, string $previousStep): TelegramSession
    {
        $session->advanceTo($previousStep)->save();
        return $session;
    }

    /**
     * Cancel the current conversation and clear session.
     */
    public function cancelConversation(TelegramSession $session): void
    {
        $session->clear()->save();
    }

    /**
     * End the conversation (successfully completed).
     */
    public function endConversation(TelegramSession $session): void
    {
        $session->clear()->save();
    }

    /**
     * Clean up all expired sessions across all tenants.
     * Called by scheduler.
     */
    public static function cleanExpiredSessions(): int
    {
        return TelegramSession::expired()->delete();
    }

    /**
     * Send the main admin menu with reply keyboard.
     */
    public function sendAdminMenu(int $tenantId, string $chatId, ?string $header = null): void
    {
        $text = $header ?? "📋 <b>Main Menu</b>\n\nSelect an action:";

        $keyboard = [
            ['➕ Borrower', '💰 Loan'],
            ['📢 Reminder', '💵 Payment'],
            ['📊 Reports', '⚠️ Overdue'],
            ['⚙️ Settings'],
        ];

        $this->sender->sendWithReplyKeyboard($tenantId, $chatId, $text, $keyboard);
    }

    /**
     * Send the borrower menu with reply keyboard (simpler).
     */
    public function sendBorrowerMenu(int $tenantId, string $chatId): void
    {
        $text = "📋 <b>Menu</b>\n\nWhat would you like to do?";

        $keyboard = [
            ['💰 My Loans', '💵 Pay'],
            ['📋 Statement'],
        ];

        $this->sender->sendWithReplyKeyboard($tenantId, $chatId, $text, $keyboard);
    }

    /**
     * Map a reply keyboard button text to an action name.
     * Returns null if the text is not a menu button.
     *
     * Note: Telegram may strip or add the variation selector (U+FE0F)
     * from emojis, so we normalize before matching.
     */
    public static function mapMenuTextToAction(string $text): ?string
    {
        // Remove variation selectors (U+FE0F) for consistent matching
        $normalized = str_replace("\u{FE0F}", '', trim($text));

        // Exact match on normalized text
        $map = [
            // Admin menu (normalized — no variation selectors)
            "➕ Borrower" => 'create_borrower',
            "💰 Loan" => 'create_loan',
            "📢 Reminder" => 'send_reminder',
            "💵 Payment" => 'record_payment',
            "📊 Reports" => 'reports',
            "⚠ Overdue" => 'overdue_check',
            "⚙ Settings" => 'settings',
            // Borrower menu
            "💰 My Loans" => 'my_loans',
            "💵 Pay" => 'pay',
            "📋 Statement" => 'statement',
        ];

        // Normalize map keys too (defensive)
        foreach ($map as $key => $action) {
            $normalizedKey = str_replace("\u{FE0F}", '', $key);
            if ($normalized === $normalizedKey) {
                return $action;
            }
        }

        // Keyword fallback — catches slight formatting differences
        $lower = mb_strtolower($normalized);
        return match (true) {
            str_contains($lower, 'borrower') && str_contains($lower, '➕') => 'create_borrower',
            str_contains($lower, 'loan') && !str_contains($lower, 'my') => 'create_loan',
            str_contains($lower, 'reminder') => 'send_reminder',
            str_contains($lower, 'payment') => 'record_payment',
            str_contains($lower, 'reports') => 'reports',
            str_contains($lower, 'overdue') => 'overdue_check',
            str_contains($lower, 'settings') => 'settings',
            str_contains($lower, 'my loans') => 'my_loans',
            str_contains($lower, 'statement') => 'statement',
            default => null,
        };
    }
}
