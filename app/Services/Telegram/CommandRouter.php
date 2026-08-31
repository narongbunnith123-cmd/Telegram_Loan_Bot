<?php

namespace App\Services\Telegram;

use App\Models\Borrower;
use App\Models\GroupParticipant;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Models\User;
use App\Services\Telegram\Commands\AddUserCommand;
use App\Services\Telegram\Commands\ApproveCommand;
use App\Services\Telegram\Commands\BalanceCommand;
use App\Services\Telegram\Commands\CancelLoanCommand;
use App\Services\Telegram\Commands\GroupStatsCommand;
use App\Services\Telegram\Commands\LinkCommand;
use App\Services\Telegram\Commands\LoansListCommand;
use App\Services\Telegram\Commands\MyLoanCommand;
use App\Services\Telegram\Commands\NewLoanCommand;
use App\Services\Telegram\Commands\ImportCommand;
use App\Services\Telegram\Commands\OverdueCommand;
use App\Services\Telegram\Commands\PayCommand;
use App\Services\Telegram\Commands\RejectCommand;
use App\Services\Telegram\Commands\RequestPayCommand;
use App\Services\Telegram\Commands\SettingsCommand;
use App\Services\Telegram\Commands\StartCommand;
use App\Services\Telegram\Commands\StatementCommand;
use App\Services\Telegram\Conversations\BaseConversation;
use App\Services\Telegram\Conversations\ConversationResult;
use App\Services\Telegram\Conversations\CreateBorrowerConversation;
use App\Services\Telegram\Conversations\CreateLoanConversation;
use App\Services\Telegram\Conversations\OverdueConversation;
use App\Services\Telegram\Conversations\RecordPaymentConversation;
use App\Services\Telegram\Conversations\ReportsConversation;
use App\Services\Telegram\Conversations\SendReminderConversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api as TelegramApi;

class CommandRouter
{
    /**
     * Slash-command registry — still works for backward compatibility + group usage.
     */
    private array $commands = [
        // Borrower commands
        '/start' => StartCommand::class,
        '/help' => StartCommand::class,
        '/myloan' => MyLoanCommand::class,
        '/balance' => BalanceCommand::class,
        '/pay' => PayCommand::class,
        '/statement' => StatementCommand::class,

        // Admin commands
        '/link' => LinkCommand::class,
        '/adminhelp' => StartCommand::class,
        '/newloan' => NewLoanCommand::class,
        '/adduser' => AddUserCommand::class,
        '/approve' => ApproveCommand::class,
        '/reject' => RejectCommand::class,
        '/loans' => LoansListCommand::class,
        '/overdue' => OverdueCommand::class,
        '/cancelloan' => CancelLoanCommand::class,
        '/groupstats' => GroupStatsCommand::class,
        '/settings' => SettingsCommand::class,
        '/import' => ImportCommand::class,
        '/requestpay' => RequestPayCommand::class,
    ];

    /**
     * Map of action names → conversation handler classes.
     */
    private array $conversations = [
        'create_borrower' => CreateBorrowerConversation::class,
        'create_loan' => CreateLoanConversation::class,
        'record_payment' => RecordPaymentConversation::class,
        'reports' => ReportsConversation::class,
        'overdue_check' => OverdueConversation::class,
        'send_reminder' => SendReminderConversation::class,
    ];

    public function route(Tenant $tenant, array $payload): void
    {
        // Handle bot join/leave events
        $this->handleBotJoin($tenant, $payload);

        // Handle callback queries (inline button clicks)
        $this->handleCallbackQuery($tenant, $payload);

        $message = data_get($payload, 'message');
        if (!$message)
            return;

        // Silently track every message sender as a group participant
        $this->trackParticipant($tenant, $message);

        $text = data_get($message, 'text', '');
        $photo = data_get($message, 'photo');
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');
        $chatType = data_get($message, 'chat.type', 'private');
        $isDM = $chatType === 'private';

        // Rate limit: 20 actions per user per minute
        $key = "tg:{$userId}:{$chatId}";
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return;
        }
        RateLimiter::hit($key, 60);

        // Handle forwarded messages (for /import flow)
        if (data_get($message, 'forward_from') || data_get($message, 'forward_sender_name')) {
            $this->handleForwardedMessage($tenant, $message);
            return;
        }

        // Handle photo messages (payment proof auto-capture) — only in groups
        if ($photo && !str_starts_with($text, '/') && !$isDM) {
            $this->handlePhotoProof($tenant, $message);
            return;
        }

        // ── SLASH COMMANDS ──────────────────────────────────
        if (str_starts_with($text, '/')) {
            // Handle /cancel — abort any active conversation
            if (str_starts_with($text, '/cancel')) {
                $this->handleCancel($tenant, $userId, $chatId);
                return;
            }

            // Handle /back — go to previous step in conversation
            if (str_starts_with($text, '/back')) {
                $this->handleBack($tenant, $userId, $chatId);
                return;
            }

            // Handle /menu — show admin menu (DM only)
            if (str_starts_with($text, '/menu') && $isDM) {
                $this->handleMenu($tenant, $userId, $chatId);
                return;
            }

            // Strip bot username suffix: /start@mybotname → /start
            $command = explode('@', explode(' ', $text)[0])[0];

            if (isset($this->commands[$command])) {
                app($this->commands[$command])->handle($tenant, $message);
            }

            return;
        }

        // ── NON-COMMAND TEXT (DMs only from here) ──────────
        // In groups, ignore non-command text
        if (!$isDM)
            return;

        $guard = app(AdminGuard::class);
        $conversationManager = app(ConversationManager::class);

        // Check if the text matches a reply keyboard menu button FIRST
        // (so tapping a new menu button cancels any active conversation)
        $action = ConversationManager::mapMenuTextToAction($text);
        if ($action) {
            // Cancel any active conversation before starting a new one
            $session = $conversationManager->getSession($tenant->id, $userId, $chatId);
            if ($session && $session->hasActiveConversation()) {
                $conversationManager->cancelConversation($session);
            }
            $this->startConversation($tenant, $userId, $chatId, $action);
            return;
        }

        // Check for active conversation session
        $session = $conversationManager->getSession($tenant->id, $userId, $chatId);

        if ($session && $session->hasActiveConversation()) {
            // Route to active conversation handler
            $this->handleConversationInput($tenant, $session, $text, $message);
            return;
        }

        // Unknown DM text — show the appropriate menu based on role
        $isAdmin = $guard->isAdmin($tenant, $userId);

        if ($isAdmin) {
            $conversationManager->sendAdminMenu(
                $tenant->id,
                $chatId,
                "🤖 I didn't understand that.\n\nUse the menu buttons or type /help for commands."
            );
        } else {
            // Check if this is a linked borrower
            $isBorrower = \App\Models\Borrower::where('tenant_id', $tenant->id)
                ->where('telegram_user_id', $userId)
                ->exists();

            if ($isBorrower) {
                $conversationManager->sendBorrowerMenu($tenant->id, $chatId);
            } else {
                // Unrecognized user — no menu, just a hint
                app(TelegramSender::class)->sendToDM(
                    $tenant->id,
                    $chatId,
                    "👋 Hello! I'm a loan management bot.\n\nIf you are an admin, use /link to connect your account.\nIf you are a borrower, ask your admin for an invite link."
                );
            }
        }
    }

    /**
     * Handle inline button callback queries.
     * Extended to support both payment approve/reject AND conversation callbacks.
     */
    private function handleCallbackQuery(Tenant $tenant, array $payload): void
    {
        $callback = data_get($payload, 'callback_query');
        if (!$callback)
            return;

        $callbackId = data_get($callback, 'id');
        $data = data_get($callback, 'data', '');
        $fromId = (string) data_get($callback, 'from.id');
        $chatId = (string) data_get($callback, 'message.chat.id');
        $messageId = (int) data_get($callback, 'message.message_id');

        $sender = app(TelegramSender::class);
        $guard = app(AdminGuard::class);

        // ── Check for active conversation (handle conversation callbacks first) ──
        $conversationManager = app(ConversationManager::class);
        $session = $conversationManager->getSession($tenant->id, $fromId, $chatId);

        if ($session && $session->hasActiveConversation()) {
            $action = $session->current_action;

            if (isset($this->conversations[$action])) {
                $conversation = app($this->conversations[$action]);
                $result = $conversation->handleCallback($tenant, $session, $data, $callback);
                $this->processConversationResult($tenant, $session, $conversationManager, $result, $chatId);

                $sender->answerCallback($tenant->id, $callbackId, '');
                return;
            }
        }

        // ── Legacy: payment approve/reject callbacks ──
        if (!$guard->isAdmin($tenant, $fromId)) {
            $sender->answerCallback($tenant->id, $callbackId, '⛔ You are not authorized.');
            return;
        }

        // Parse callback data: "approve:123" or "reject:123"
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $paymentId = (int) ($parts[1] ?? 0);

        if (!$paymentId) {
            $sender->answerCallback($tenant->id, $callbackId, '❌ Invalid action.');
            return;
        }

        $result = '';

        if ($action === 'approve') {
            $approveCmd = app(ApproveCommand::class);
            $result = $approveCmd->approvePayment($tenant, $paymentId, $fromId);
            $sender->answerCallback($tenant->id, $callbackId, '✅ Payment approved!');
        } elseif ($action === 'reject') {
            $rejectCmd = app(RejectCommand::class);
            $result = $rejectCmd->rejectPayment($tenant, $paymentId, $fromId);
            $sender->answerCallback($tenant->id, $callbackId, '❌ Payment rejected.');
        } else {
            $sender->answerCallback($tenant->id, $callbackId, '❌ Unknown action.');
            return;
        }

        // Update the original message to show the result (removes buttons)
        $sender->editMessage($tenant->id, $chatId, $messageId, $result);
    }

    /**
     * Start a new conversation flow from a menu button action.
     */
    private function startConversation(Tenant $tenant, string $userId, string $chatId, string $action): void
    {
        $sender = app(TelegramSender::class);

        // Define which actions are admin-only
        $adminActions = ['create_borrower', 'create_loan', 'record_payment', 'reports', 'overdue_check', 'send_reminder', 'settings'];
        $borrowerActions = ['my_loans', 'pay', 'statement'];

        $isAdminAction = in_array($action, $adminActions);
        $isBorrowerAction = in_array($action, $borrowerActions);

        if (!isset($this->conversations[$action]) && !$isBorrowerAction) {
            $sender->sendToDM($tenant->id, $chatId, "❌ This feature is coming soon.");
            return;
        }

        // Verify admin for admin-only actions
        $guard = app(AdminGuard::class);
        $isAdmin = $guard->isAdmin($tenant, $userId);

        if ($isAdminAction && !$isAdmin) {
            $sender->sendToDM($tenant->id, $chatId, "⛔ This action is only available to admins.");
            return;
        }

        // Handle borrower actions (not yet conversation-based)
        if ($isBorrowerAction) {
            $sender->sendToDM($tenant->id, $chatId, "❌ This feature is coming soon. Use the / commands instead:\n\n/myloan — View your loans\n/pay — Make a payment\n/statement — Get loan statement");
            return;
        }

        $conversationManager = app(ConversationManager::class);
        $conversation = app($this->conversations[$action]);

        // Start the session
        $session = $conversationManager->startConversation(
            tenantId: $tenant->id,
            userId: $userId,
            chatId: $chatId,
            action: $action,
            firstStep: $conversation->firstStep(),
        );

        // Get the initial prompt
        $result = match ($action) {
            'create_borrower' => $conversation->promptSelectGroup($tenant, $session),
            'create_loan' => $conversation->promptSelectGroup($tenant, $session),
            'record_payment' => $conversation->promptSelectBorrower($tenant, $session),
            'reports' => $conversation->generateReport($tenant),
            'overdue_check' => $conversation->checkOverdue($tenant),
            'send_reminder' => $conversation->sendReminders($tenant),
            default => ConversationResult::error("❌ Not implemented."),
        };

        $this->processConversationResult($tenant, $session, $conversationManager, $result, $chatId);
    }

    /**
     * Handle text input during an active conversation.
     */
    private function handleConversationInput(
        Tenant $tenant,
        TelegramSession $session,
        string $text,
        array $message,
    ): void {
        $action = $session->current_action;
        $chatId = $session->telegram_chat_id;
        $conversationManager = app(ConversationManager::class);

        if (!isset($this->conversations[$action])) {
            $conversationManager->cancelConversation($session);
            app(TelegramSender::class)->sendToDM(
                $tenant->id,
                $chatId,
                "❌ Unknown conversation. Session cleared."
            );
            return;
        }

        $conversation = app($this->conversations[$action]);
        $result = $conversation->handleStep($tenant, $session, $text, $message);

        $this->processConversationResult($tenant, $session, $conversationManager, $result, $chatId);
    }

    /**
     * Process a ConversationResult — advance/stay/complete/error.
     */
    private function processConversationResult(
        Tenant $tenant,
        TelegramSession $session,
        ConversationManager $conversationManager,
        ConversationResult $result,
        string $chatId,
    ): void {
        $sender = app(TelegramSender::class);

        switch ($result->type) {
            case 'advance':
                $conversationManager->advanceStep($session, $result->nextStep, $result->mergeData);
                $this->sendResultMessage($tenant->id, $chatId, $result, $sender);
                break;

            case 'stay':
                // Validation error — re-prompt same step
                if ($result->message) {
                    $this->sendResultMessage($tenant->id, $chatId, $result, $sender);
                }
                break;

            case 'complete':
                $conversationManager->endConversation($session);
                if ($result->message) {
                    $sender->sendToDM($tenant->id, $chatId, $result->message);
                }
                // Re-show admin menu after completion
                $conversationManager->sendAdminMenu($tenant->id, $chatId);
                break;

            case 'error':
                $conversationManager->cancelConversation($session);
                if ($result->message) {
                    $sender->sendToDM($tenant->id, $chatId, $result->message);
                }
                $conversationManager->sendAdminMenu($tenant->id, $chatId);
                break;

            case 'continue':
                // No action needed — step handled internally
                break;
        }
    }

    /**
     * Send a conversation result message with the appropriate keyboard.
     */
    private function sendResultMessage(int $tenantId, string $chatId, ConversationResult $result, TelegramSender $sender): void
    {
        if ($result->inlineKeyboard) {
            $sender->sendWithInlineKeyboard($tenantId, $chatId, $result->message, $result->inlineKeyboard);
        } elseif ($result->keyboard) {
            $sender->sendWithReplyKeyboard($tenantId, $chatId, $result->message, $result->keyboard);
        } else {
            $sender->sendToDM($tenantId, $chatId, $result->message);
        }
    }

    /**
     * /cancel — cancel the active conversation.
     */
    private function handleCancel(Tenant $tenant, string $userId, string $chatId): void
    {
        $conversationManager = app(ConversationManager::class);
        $session = $conversationManager->getSession($tenant->id, $userId, $chatId);

        if ($session && $session->hasActiveConversation()) {
            $conversationManager->cancelConversation($session);
            $conversationManager->sendAdminMenu(
                $tenant->id,
                $chatId,
                "❌ Cancelled.\n\nSelect an action:"
            );
        } else {
            app(TelegramSender::class)->sendToDM(
                $tenant->id,
                $chatId,
                "ℹ️ No active conversation to cancel."
            );
        }
    }

    /**
     * /back — go to previous step in the conversation.
     */
    private function handleBack(Tenant $tenant, string $userId, string $chatId): void
    {
        $conversationManager = app(ConversationManager::class);
        $session = $conversationManager->getSession($tenant->id, $userId, $chatId);

        if (!$session || !$session->hasActiveConversation()) {
            app(TelegramSender::class)->sendToDM(
                $tenant->id,
                $chatId,
                "ℹ️ No active conversation."
            );
            return;
        }

        $action = $session->current_action;

        if (!isset($this->conversations[$action])) {
            $conversationManager->cancelConversation($session);
            return;
        }

        $conversation = app($this->conversations[$action]);
        $previousStep = $conversation->previousStep($session->current_step);

        if ($previousStep === null) {
            // At first step — cancel the conversation
            $conversationManager->cancelConversation($session);
            $conversationManager->sendAdminMenu($tenant->id, $chatId, "❌ Cancelled (at first step).");
        } else {
            $conversationManager->goBack($session, $previousStep);
            app(TelegramSender::class)->sendToDM(
                $tenant->id,
                $chatId,
                "⬅️ Went back. Please provide the answer again."
            );
        }
    }

    /**
     * /menu — show the admin main menu.
     */
    private function handleMenu(Tenant $tenant, string $userId, string $chatId): void
    {
        $conversationManager = app(ConversationManager::class);
        $session = $conversationManager->getSession($tenant->id, $userId, $chatId);

        // Cancel any active conversation first
        if ($session && $session->hasActiveConversation()) {
            $conversationManager->cancelConversation($session);
        }

        // Determine if admin or borrower
        $guard = app(AdminGuard::class);
        if ($guard->isAdmin($tenant, $userId)) {
            $conversationManager->sendAdminMenu($tenant->id, $chatId);
        } else {
            $conversationManager->sendBorrowerMenu($tenant->id, $chatId);
        }
    }

    /**
     * Handle photo messages — auto-capture as payment proof from borrowers.
     */
    private function handlePhotoProof(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        $sender = app(TelegramSender::class);

        // Find borrower
        $borrower = Borrower::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $userId)
            ->first();

        if (!$borrower)
            return; // Not a registered borrower, ignore photo

        // Find active loan
        $loan = Loan::where('tenant_id', $tenant->id)
            ->where('borrower_id', $borrower->id)
            ->whereIn('status', ['active', 'overdue'])
            ->latest()
            ->first();

        if (!$loan)
            return; // No active loan, ignore photo

        // Get the largest photo (last in array)
        $photos = data_get($message, 'photo', []);
        $photo = end($photos);
        $fileId = data_get($photo, 'file_id');

        if (!$fileId)
            return;

        // Download the photo via Telegram API
        try {
            $botToken = \App\Models\BotToken::where('tenant_id', $tenant->id)->first();
            if (!$botToken)
                return;

            $telegram = new TelegramApi($botToken->token);

            // Fix SSL cert on Windows dev
            $caPath = base_path('cacert.pem');
            if (file_exists($caPath)) {
                $telegram->setHttpClientHandler(
                    new \Telegram\Bot\HttpClients\GuzzleHttpClient(
                        new \GuzzleHttp\Client(['verify' => $caPath])
                    )
                );
            }

            $file = $telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            // Download file
            $fileUrl = "https://api.telegram.org/file/bot{$botToken->token}/{$filePath}";
            $contents = file_get_contents($fileUrl);

            if (!$contents)
                return;

            // Store locally
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $storagePath = "payment_proofs/{$tenant->id}/" . uniqid('tg_') . ".{$extension}";
            Storage::disk('public')->put($storagePath, $contents);

            // Create pending payment
            $caption = data_get($message, 'caption', '');
            $amount = $this->extractAmount($caption) ?? $loan->balance;

            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'loan_id' => $loan->id,
                'amount' => $amount,
                'type' => 'partial',
                'method' => 'other',
                'status' => 'pending',
                'notes' => $caption ?: 'Submitted via Telegram photo',
            ]);

            // Create proof record
            PaymentProof::create([
                'tenant_id' => $tenant->id,
                'payment_id' => $payment->id,
                'file_path' => $storagePath,
                'original_name' => "telegram_proof_{$payment->id}.{$extension}",
                'status' => 'pending',
            ]);

            $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
            $currency = $settings['currency'] ?? '$';

            // Notify the group with the proof receipt
            $borrowerName = $borrower->telegram_username
                ? "@{$borrower->telegram_username}"
                : $borrower->name;

            $proofMsg = "📸 *Payment Proof Received*\n\n"
                . "👤 Borrower: {$this->esc($borrowerName)}\n"
                . "💵 Amount: {$currency}{$amount}\n"
                . "💰 Loan Balance: {$currency}{$loan->balance}\n"
                . "🔖 Payment ID: \\#{$payment->id}\n\n"
                . "Admin: use buttons below or `/approve {$payment->id}`";

            // Send with inline approve/reject buttons
            $buttons = [
                ['text' => '✅ Approve', 'callback_data' => "approve:{$payment->id}"],
                ['text' => '❌ Reject', 'callback_data' => "reject:{$payment->id}"],
            ];

            $sender->sendWithButtons($tenant->id, $chatId, $proofMsg, $buttons);
        } catch (\Exception $e) {
            Log::error("Photo proof capture error: " . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Try to extract a numeric amount from photo caption.
     * E.g., "Paid $50" → 50, "payment 100" → 100
     */
    private function extractAmount(string $caption): ?float
    {
        if (preg_match('/[\$]?\s*(\d+(?:\.\d{1,2})?)/', $caption, $matches)) {
            $val = (float) $matches[1];
            return $val > 0 ? $val : null;
        }
        return null;
    }

    private function handleBotJoin(Tenant $tenant, array $payload): void
    {
        $myChatMember = data_get($payload, 'my_chat_member');
        if (!$myChatMember)
            return;

        $newStatus = data_get($myChatMember, 'new_chat_member.status');
        $chat = data_get($myChatMember, 'chat');

        if (in_array($newStatus, ['member', 'administrator'])) {
            TelegramGroup::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'telegram_group_id' => (string) data_get($chat, 'id'),
                ],
                [
                    'name' => data_get($chat, 'title', 'Unknown Group'),
                    'status' => 'pending',
                    'joined_at' => now(),
                    'settings' => [
                        'currency' => '$',
                        'reminder_frequency' => 'daily',
                        'reminder_time' => '09:00',
                    ],
                ]
            );
        }

        if ($newStatus === 'kicked') {
            TelegramGroup::where('tenant_id', $tenant->id)
                ->where('telegram_group_id', (string) data_get($chat, 'id'))
                ->update(['status' => 'suspended']);
        }
    }

    private function esc(string $text): string
    {
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
            $text
        );
    }

    /**
     * Silently track every message sender as a group participant.
     */
    private function trackParticipant(Tenant $tenant, array $message): void
    {
        $fromId = data_get($message, 'from.id');
        $chatId = data_get($message, 'chat.id');
        if (!$fromId || !$chatId)
            return;
        if (data_get($message, 'from.is_bot'))
            return;

        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', (string) $chatId)->first();
        if (!$group)
            return;

        GroupParticipant::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'group_id' => $group->id,
                'telegram_user_id' => (string) $fromId,
            ],
            [
                'telegram_username' => data_get($message, 'from.username'),
                'first_name' => data_get($message, 'from.first_name'),
                'last_name' => data_get($message, 'from.last_name'),
                'is_bot' => false,
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * Handle forwarded messages — route to ImportCommand for borrower import.
     */
    private function handleForwardedMessage(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        // Only process if admin has an active import session
        $cacheKey = "import_session:{$tenant->id}:{$userId}:{$chatId}";
        if (!cache()->has($cacheKey))
            return;

        app(ImportCommand::class)->handleForward($tenant, $message);
    }
}
