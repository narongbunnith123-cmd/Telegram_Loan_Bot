<?php

namespace App\Services\Telegram\Conversations;

use App\Models\BorrowerPaymentMethod;
use App\Models\TelegramGroup;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Services\BorrowerService;
use App\Services\Telegram\TelegramSender;

class CreateBorrowerConversation extends BaseConversation
{
    public function __construct(
        private BorrowerService $borrowerService,
        private TelegramSender $sender,
    ) {
    }

    public static function action(): string
    {
        return 'create_borrower';
    }

    public function firstStep(): string
    {
        return 'select_group';
    }

    public function steps(): array
    {
        return [
            'select_group',
            'enter_name',
            'enter_phone',
            'payment_method',
            'bank_details',
            'confirm',
        ];
    }

    public function previousStep(string $currentStep): ?string
    {
        return match ($currentStep) {
            'enter_name' => 'select_group',
            'enter_phone' => 'enter_name',
            'payment_method' => 'enter_phone',
            'bank_details' => 'payment_method',
            'confirm' => 'payment_method',
            default => null,
        };
    }

    public function handleStep(
        Tenant $tenant,
        TelegramSession $session,
        string $input,
        array $message,
    ): ConversationResult {
        return match ($session->current_step) {
            'select_group' => $this->handleSelectGroup($tenant, $session, $input),
            'enter_name' => $this->handleEnterName($session, $input),
            'enter_phone' => $this->handleEnterPhone($session, $input),
            'payment_method' => $this->handlePaymentMethod($session, $input),
            'bank_details' => $this->handleBankDetails($session, $input),
            'confirm' => $this->handleConfirm($tenant, $session, $input, $message),
            default => ConversationResult::error("❌ Unknown step. Use /cancel to restart."),
        };
    }

    public function handleCallback(
        Tenant $tenant,
        TelegramSession $session,
        string $callbackData,
        array $callbackQuery,
    ): ConversationResult {
        // Handle inline button selections
        if (str_starts_with($callbackData, 'group:')) {
            $groupId = (int) str_replace('group:', '', $callbackData);
            return $this->selectGroup($tenant, $session, $groupId);
        }

        if ($callbackData === 'pm_bank') {
            $session->setData('payment_type', 'bank')->save();
            return ConversationResult::advance(
                'bank_details',
                "🏦 <b>Enter Bank Details</b>\n\nPlease enter bank name and account number:\n<code>Bank Name - Account Number - Account Holder</code>\n\nExample: <code>ABA - 001234567 - John Doe</code>",
                mergeData: ['payment_type' => 'bank'],
            );
        }

        if ($callbackData === 'pm_cash' || $callbackData === 'pm_wallet') {
            $type = $callbackData === 'pm_cash' ? 'cash' : 'wallet';
            $session->setData('payment_type', $type)->save();
            return $this->showConfirmation($session);
        }

        if ($callbackData === 'pm_skip') {
            $session->setData('payment_type', null)->save();
            return $this->showConfirmation($session);
        }

        if ($callbackData === 'confirm_yes') {
            return $this->executeBorrowerCreation($tenant, $session);
        }

        if ($callbackData === 'confirm_force_create') {
            return $this->executeBorrowerCreation($tenant, $session, skipDuplicates: true);
        }

        if ($callbackData === 'confirm_no') {
            return ConversationResult::complete("❌ Borrower creation cancelled.");
        }

        return ConversationResult::stay("❌ Invalid selection. Please try again.");
    }

    /**
     * Step 1: Show group selection buttons.
     */
    public function promptSelectGroup(Tenant $tenant, TelegramSession $session): ConversationResult
    {
        $groups = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();

        if ($groups->isEmpty()) {
            return ConversationResult::error("❌ No active groups found. Please register a group first.");
        }

        $rows = $groups->map(fn($g) => [
            ['text' => "📂 {$g->name}", 'callback_data' => "group:{$g->id}"],
        ])->toArray();

        return ConversationResult::advance(
            'select_group',
            "➕ <b>Create New Borrower</b>\n\n📂 Select a group:",
            inlineKeyboard: $rows,
        );
    }

    private function handleSelectGroup(Tenant $tenant, TelegramSession $session, string $input): ConversationResult
    {
        // Text input fallback — try to find group by name
        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('name', 'like', "%{$input}%")
            ->first();

        if (!$group) {
            return ConversationResult::stay("❌ Group not found. Please select from the buttons above or type a group name.");
        }

        return $this->selectGroup($tenant, $session, $group->id);
    }

    private function selectGroup(Tenant $tenant, TelegramSession $session, int $groupId): ConversationResult
    {
        $group = TelegramGroup::where('tenant_id', $tenant->id)->find($groupId);
        if (!$group) {
            return ConversationResult::stay("❌ Group not found.");
        }

        return ConversationResult::advance(
            'enter_name',
            "📂 Group: <b>{$this->esc($group->name)}</b>\n\n👤 <b>Enter borrower's full name:</b>",
            mergeData: ['group_id' => $groupId, 'group_name' => $group->name],
        );
    }

    /**
     * Step 2: Enter borrower name.
     */
    private function handleEnterName(TelegramSession $session, string $input): ConversationResult
    {
        $name = trim($input);

        if (mb_strlen($name) < 2) {
            return ConversationResult::stay("❌ Name must be at least 2 characters. Please try again:");
        }

        if (mb_strlen($name) > 255) {
            return ConversationResult::stay("❌ Name is too long (max 255 characters). Please try again:");
        }

        return ConversationResult::advance(
            'enter_phone',
            "👤 Name: <b>{$this->esc($name)}</b>\n\n📱 <b>Enter phone number</b> (or type <b>SKIP</b>):",
            mergeData: ['name' => $name],
        );
    }

    /**
     * Step 3: Enter phone number.
     */
    private function handleEnterPhone(TelegramSession $session, string $input): ConversationResult
    {
        $phone = strtoupper(trim($input)) === 'SKIP' ? null : trim($input);

        if ($phone !== null && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone)) {
            return ConversationResult::stay("❌ Invalid phone format. Please enter a valid phone number or type <b>SKIP</b>:");
        }

        $rows = [
            [
                ['text' => '🏦 Bank', 'callback_data' => 'pm_bank'],
                ['text' => '💵 Cash', 'callback_data' => 'pm_cash'],
            ],
            [
                ['text' => '📱 Wallet', 'callback_data' => 'pm_wallet'],
                ['text' => '⏭ Skip', 'callback_data' => 'pm_skip'],
            ],
        ];

        $phoneLabel = $phone ? "<b>{$this->esc($phone)}</b>" : "<i>Skipped</i>";

        return ConversationResult::advance(
            'payment_method',
            "📱 Phone: {$phoneLabel}\n\n💳 <b>Add payment method?</b>\nSelect type or skip:",
            inlineKeyboard: $rows,
            mergeData: ['phone_number' => $phone],
        );
    }

    /**
     * Step 4: Select payment method type (handled via callbacks mostly).
     */
    private function handlePaymentMethod(TelegramSession $session, string $input): ConversationResult
    {
        $lower = strtolower(trim($input));

        return match ($lower) {
            'bank' => $this->handleCallback(new Tenant(), $session, 'pm_bank', []),
            'cash' => $this->handleCallback(new Tenant(), $session, 'pm_cash', []),
            'wallet' => $this->handleCallback(new Tenant(), $session, 'pm_wallet', []),
            'skip' => $this->handleCallback(new Tenant(), $session, 'pm_skip', []),
            default => ConversationResult::stay("❌ Please select a payment method from the buttons, or type: <b>bank</b>, <b>cash</b>, <b>wallet</b>, or <b>skip</b>."),
        };
    }

    /**
     * Step 5: Enter bank details (if bank selected).
     */
    private function handleBankDetails(TelegramSession $session, string $input): ConversationResult
    {
        $parts = array_map('trim', explode('-', $input));

        if (count($parts) < 2) {
            return ConversationResult::stay(
                "❌ Please use the format:\n<code>Bank Name - Account Number - Account Holder</code>\n\nExample: <code>ABA - 001234567 - John Doe</code>"
            );
        }

        $bankName = $parts[0];
        $accountNumber = $parts[1];
        $accountHolder = $parts[2] ?? $session->getData('name', '');

        return $this->showConfirmation($session, [
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_holder' => $accountHolder,
        ]);
    }

    /**
     * Step 6: Show confirmation summary.
     */
    private function showConfirmation(TelegramSession $session, array $extraData = []): ConversationResult
    {
        $mergeData = $extraData;
        $data = array_merge($session->temp_data ?? [], $extraData);

        $paymentType = $data['payment_type'] ?? null;
        $paymentLabel = match ($paymentType) {
            'bank' => "🏦 Bank: {$this->esc($data['bank_name'] ?? 'N/A')} — {$this->esc($data['account_number'] ?? 'N/A')}",
            'cash' => '💵 Cash',
            'wallet' => '📱 Digital Wallet',
            default => '⏭ None',
        };

        $msg = "📋 <b>Confirm New Borrower</b>\n\n"
            . "📂 Group: <b>{$this->esc($data['group_name'] ?? 'N/A')}</b>\n"
            . "👤 Name: <b>{$this->esc($data['name'] ?? 'N/A')}</b>\n"
            . "📱 Phone: " . ($data['phone_number'] ?? '<i>Not provided</i>') . "\n"
            . "💳 Payment: {$paymentLabel}\n\n"
            . "Is this correct?";

        $rows = [
            [
                ['text' => '✅ Confirm', 'callback_data' => 'confirm_yes'],
                ['text' => '❌ Cancel', 'callback_data' => 'confirm_no'],
            ]
        ];

        return ConversationResult::advance(
            'confirm',
            $msg,
            inlineKeyboard: $rows,
            mergeData: $mergeData,
        );
    }

    /**
     * Step 6 (text fallback): Handle yes/no text.
     */
    private function handleConfirm(Tenant $tenant, TelegramSession $session, string $input, array $message): ConversationResult
    {
        $lower = strtolower(trim($input));

        if (in_array($lower, ['yes', 'y', 'confirm', '✅'])) {
            return $this->executeBorrowerCreation($tenant, $session);
        }

        if (in_array($lower, ['no', 'n', 'cancel', '❌'])) {
            return ConversationResult::complete("❌ Borrower creation cancelled.");
        }

        return ConversationResult::stay("Please tap <b>✅ Confirm</b> or <b>❌ Cancel</b>, or type <b>yes</b>/<b>no</b>.");
    }

    /**
     * Execute the actual borrower creation using shared BorrowerService.
     */
    private function executeBorrowerCreation(Tenant $tenant, TelegramSession $session, bool $skipDuplicates = false): ConversationResult
    {
        $data = $session->temp_data ?? [];

        // Get admin user from the session context
        $userId = $session->telegram_user_id;
        $admin = \App\Models\User::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $userId)
            ->first();

        $result = $this->borrowerService->createBorrower(
            tenantId: $tenant->id,
            data: [
                'name' => $data['name'] ?? 'Unknown',
                'phone_number' => $data['phone_number'] ?? null,
                'onboarding_source' => 'telegram_conversation',
            ],
            createdBy: $admin,
            skipDuplicates: $skipDuplicates,
        );

        // Handle duplicates — show reason + "Create Anyway" option
        if (!empty($result['duplicates'])) {
            $duplicateLines = [];
            foreach ($result['duplicates'] as $dup) {
                $existing = $dup['borrower'];
                $reason = $dup['reason'] ?? 'Unknown match';
                $duplicateLines[] = "👤 <b>{$this->esc($existing->name)}</b>"
                    . " (Code: <code>{$this->esc($existing->borrower_code ?? 'N/A')}</code>)"
                    . "\n   📌 Reason: <i>{$this->esc($reason)}</i>";
            }

            $msg = "⚠️ <b>Possible duplicate found!</b>\n\n"
                . implode("\n\n", $duplicateLines) . "\n\n"
                . "You're creating: <b>{$this->esc($data['name'] ?? 'Unknown')}</b>\n\n"
                . "What would you like to do?";

            $rows = [
                [
                    ['text' => '✅ Create Anyway', 'callback_data' => 'confirm_force_create'],
                    ['text' => '❌ Cancel', 'callback_data' => 'confirm_no'],
                ]
            ];

            // Stay on confirm step so buttons work
            return ConversationResult::advance(
                'confirm',
                $msg,
                inlineKeyboard: $rows,
            );
        }

        $borrower = $result['borrower'];

        // Create payment method if specified
        $paymentType = $data['payment_type'] ?? null;
        if ($paymentType) {
            BorrowerPaymentMethod::create([
                'tenant_id' => $tenant->id,
                'borrower_id' => $borrower->id,
                'type' => $paymentType,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'account_holder' => $data['account_holder'] ?? null,
                'is_default' => true,
            ]);
        }

        $deepLink = $borrower->deep_link;
        $msg = "✅ <b>Borrower Created!</b>\n\n"
            . "👤 Name: {$this->esc($borrower->name)}\n"
            . "🏷 Code: <code>{$this->esc($borrower->borrower_code)}</code>\n"
            . "📱 Phone: " . ($data['phone_number'] ?? 'N/A') . "\n"
            . "🔗 Status: 🟡 Pending\n\n";

        if ($deepLink) {
            $msg .= "📎 Share this link to connect their Telegram:\n<code>{$this->esc($deepLink)}</code>\n\n";
        }

        $msg .= "Use /newloan to create a loan for this borrower.";

        return ConversationResult::complete($msg);
    }
}
