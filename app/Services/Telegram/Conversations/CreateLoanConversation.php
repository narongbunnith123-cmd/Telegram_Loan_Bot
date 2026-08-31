<?php

namespace App\Services\Telegram\Conversations;

use App\Models\Borrower;
use App\Models\TelegramGroup;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Services\LoanService;
use App\Services\Telegram\TelegramSender;

class CreateLoanConversation extends BaseConversation
{
    public function __construct(
        private LoanService $loanService,
        private TelegramSender $sender,
    ) {
    }

    public static function action(): string
    {
        return 'create_loan';
    }

    public function firstStep(): string
    {
        return 'select_group';
    }

    public function steps(): array
    {
        return [
            'select_group',
            'select_borrower',
            'enter_amount',
            'enter_interest',
            'select_loan_type',
            'enter_duration',
            'confirm',
        ];
    }

    public function previousStep(string $currentStep): ?string
    {
        return match ($currentStep) {
            'select_borrower' => 'select_group',
            'enter_amount' => 'select_borrower',
            'enter_interest' => 'enter_amount',
            'select_loan_type' => 'enter_interest',
            'enter_duration' => 'select_loan_type',
            'confirm' => 'select_loan_type',
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
            'select_borrower' => $this->handleSelectBorrower($tenant, $session, $input),
            'enter_amount' => $this->handleEnterAmount($session, $input),
            'enter_interest' => $this->handleEnterInterest($session, $input),
            'select_loan_type' => $this->handleSelectLoanType($session, $input),
            'enter_duration' => $this->handleEnterDuration($session, $input),
            'confirm' => $this->handleConfirm($tenant, $session, $input),
            default => ConversationResult::error("❌ Unknown step."),
        };
    }

    public function handleCallback(
        Tenant $tenant,
        TelegramSession $session,
        string $callbackData,
        array $callbackQuery,
    ): ConversationResult {
        if (str_starts_with($callbackData, 'group:')) {
            $groupId = (int) str_replace('group:', '', $callbackData);
            return $this->selectGroup($tenant, $session, $groupId);
        }

        if (str_starts_with($callbackData, 'borrower:')) {
            $borrowerId = (int) str_replace('borrower:', '', $callbackData);
            return $this->selectBorrower($tenant, $session, $borrowerId);
        }

        if ($callbackData === 'loan_revolving') {
            return ConversationResult::advance(
                'enter_duration',
                "📅 <b>Enter loan duration in days</b> (or type <b>NONE</b> for no end date):\n\nExample: <code>30</code>",
                mergeData: ['loan_type' => 'lump_sum'],
            );
        }

        if ($callbackData === 'loan_installment') {
            return ConversationResult::advance(
                'enter_duration',
                "📅 <b>Enter duration in months</b>:\n\nExample: <code>6</code> (6 monthly installments)",
                mergeData: ['loan_type' => 'installment'],
            );
        }

        if ($callbackData === 'confirm_yes') {
            return $this->executeLoanCreation($tenant, $session);
        }

        if ($callbackData === 'confirm_no') {
            return ConversationResult::complete("❌ Loan creation cancelled.");
        }

        return ConversationResult::stay("❌ Invalid selection.");
    }

    /**
     * Prompt: Select group (entry point).
     */
    public function promptSelectGroup(Tenant $tenant, TelegramSession $session): ConversationResult
    {
        $groups = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();

        if ($groups->isEmpty()) {
            return ConversationResult::error("❌ No active groups found.");
        }

        $rows = $groups->map(fn($g) => [
            ['text' => "📂 {$g->name}", 'callback_data' => "group:{$g->id}"],
        ])->toArray();

        return ConversationResult::advance(
            'select_group',
            "💰 <b>Create New Loan</b>\n\n📂 Select a group:",
            inlineKeyboard: $rows,
        );
    }

    private function handleSelectGroup(Tenant $tenant, TelegramSession $session, string $input): ConversationResult
    {
        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('name', 'like', "%{$input}%")
            ->first();

        if (!$group) {
            return ConversationResult::stay("❌ Group not found. Select from buttons above.");
        }

        return $this->selectGroup($tenant, $session, $group->id);
    }

    private function selectGroup(Tenant $tenant, TelegramSession $session, int $groupId): ConversationResult
    {
        $group = TelegramGroup::find($groupId);
        if (!$group) {
            return ConversationResult::stay("❌ Group not found.");
        }

        // Show active borrowers as inline buttons
        $borrowers = Borrower::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->take(20)
            ->get();

        if ($borrowers->isEmpty()) {
            return ConversationResult::error(
                "❌ No active borrowers found.\n\nUse <b>➕ Borrower</b> to register one first."
            );
        }

        $rows = $borrowers->map(fn($b) => [
            ['text' => "👤 {$b->name}", 'callback_data' => "borrower:{$b->id}"],
        ])->toArray();

        return ConversationResult::advance(
            'select_borrower',
            "📂 Group: <b>{$this->esc($group->name)}</b>\n\n👤 <b>Select borrower:</b>",
            inlineKeyboard: $rows,
            mergeData: ['group_id' => $groupId, 'group_name' => $group->name],
        );
    }

    private function handleSelectBorrower(Tenant $tenant, TelegramSession $session, string $input): ConversationResult
    {
        $borrower = Borrower::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where(fn($q) => $q->where('name', 'like', "%{$input}%")
                ->orWhere('telegram_username', ltrim($input, '@')))
            ->first();

        if (!$borrower) {
            return ConversationResult::stay("❌ Borrower not found. Select from buttons or type a name.");
        }

        return $this->selectBorrower($tenant, $session, $borrower->id);
    }

    private function selectBorrower(Tenant $tenant, TelegramSession $session, int $borrowerId): ConversationResult
    {
        $borrower = Borrower::find($borrowerId);
        if (!$borrower) {
            return ConversationResult::stay("❌ Borrower not found.");
        }

        return ConversationResult::advance(
            'enter_amount',
            "👤 Borrower: <b>{$this->esc($borrower->name)}</b>\n\n💰 <b>Enter loan amount:</b>\n\nExample: <code>500</code>",
            mergeData: ['borrower_id' => $borrowerId, 'borrower_name' => $borrower->name],
        );
    }

    private function handleEnterAmount(TelegramSession $session, string $input): ConversationResult
    {
        $amount = (float) str_replace([',', ' '], '', $input);

        if ($amount <= 0) {
            return ConversationResult::stay("❌ Amount must be greater than 0. Please enter a valid amount:");
        }

        return ConversationResult::advance(
            'enter_interest',
            "💰 Amount: <b>" . number_format($amount, 2) . "</b>\n\n"
            . "📈 <b>Enter daily interest rate:</b>\n\n"
            . "Examples:\n"
            . "• <code>2</code> → 2% per day\n"
            . "• <code>5f</code> → \$5/day fixed",
            mergeData: ['principal' => $amount],
        );
    }

    private function handleEnterInterest(TelegramSession $session, string $input): ConversationResult
    {
        $raw = trim($input);

        $interestType = 'percentage';
        $interestValue = (float) $raw;

        if (str_ends_with(strtolower($raw), 'f')) {
            $interestType = 'fixed';
            $interestValue = (float) rtrim($raw, 'fF');
        }

        if ($interestValue < 0) {
            return ConversationResult::stay("❌ Interest must be 0 or more. Try again:");
        }

        $label = $interestType === 'fixed'
            ? "\${$interestValue}/day"
            : "{$interestValue}%/day";

        $rows = [
            [
                ['text' => '🔄 Revolving Loan', 'callback_data' => 'loan_revolving'],
                ['text' => '📅 Fixed Term Loan', 'callback_data' => 'loan_installment'],
            ],
        ];

        return ConversationResult::advance(
            'select_loan_type',
            "📈 Interest: <b>{$label}</b>\n\n📋 <b>Select loan type:</b>\n\n"
            . "🔄 <b>Revolving</b> — Daily interest, flexible repayment\n"
            . "📅 <b>Fixed Term</b> — Monthly installments",
            inlineKeyboard: $rows,
            mergeData: [
                'interest_type' => $interestType,
                'interest_value' => $interestValue,
            ],
        );
    }

    private function handleSelectLoanType(TelegramSession $session, string $input): ConversationResult
    {
        $lower = strtolower(trim($input));

        return match (true) {
            str_contains($lower, 'revolving'), str_contains($lower, 'lump') => ConversationResult::advance(
                'enter_duration',
                "📅 <b>Enter duration in days</b> (or type <b>NONE</b> for no end date):",
                mergeData: ['loan_type' => 'lump_sum'],
            ),
            str_contains($lower, 'fixed'), str_contains($lower, 'installment') => ConversationResult::advance(
                'enter_duration',
                "📅 <b>Enter duration in months</b>:\n\nExample: <code>6</code>",
                mergeData: ['loan_type' => 'installment'],
            ),
            default => ConversationResult::stay("❌ Please select a loan type from the buttons."),
        };
    }

    private function handleEnterDuration(TelegramSession $session, string $input): ConversationResult
    {
        $data = $session->temp_data ?? [];
        $loanType = $data['loan_type'] ?? 'lump_sum';
        $upper = strtoupper(trim($input));

        if ($loanType === 'lump_sum') {
            if ($upper === 'NONE' || $upper === '0') {
                return $this->showConfirmation($session, ['due_date' => null, 'duration_label' => 'No end date']);
            }

            $days = (int) $input;
            if ($days <= 0) {
                return ConversationResult::stay("❌ Enter a valid number of days or type <b>NONE</b>:");
            }

            $dueDate = now()->addDays($days)->toDateString();
            return $this->showConfirmation($session, [
                'due_date' => $dueDate,
                'duration_days' => $days,
                'duration_label' => "{$days} days (due " . now()->addDays($days)->format('d M Y') . ")",
            ]);
        } else {
            $months = (int) $input;
            if ($months <= 0 || $months > 60) {
                return ConversationResult::stay("❌ Enter a valid number of months (1-60):");
            }

            return $this->showConfirmation($session, [
                'duration_months' => $months,
                'duration_label' => "{$months} months",
            ]);
        }
    }

    private function showConfirmation(TelegramSession $session, array $extraData): ConversationResult
    {
        $data = array_merge($session->temp_data ?? [], $extraData);
        $loanType = $data['loan_type'] ?? 'lump_sum';
        $typeLabel = LoanService::displayLoanType($loanType);

        $intLabel = ($data['interest_type'] ?? 'percentage') === 'fixed'
            ? "\${$data['interest_value']}/day"
            : "{$data['interest_value']}%/day";

        $msg = "📋 <b>Confirm New Loan</b>\n\n"
            . "📂 Group: <b>{$this->esc($data['group_name'] ?? 'N/A')}</b>\n"
            . "👤 Borrower: <b>{$this->esc($data['borrower_name'] ?? 'N/A')}</b>\n"
            . "💰 Amount: <b>" . number_format($data['principal'] ?? 0, 2) . "</b>\n"
            . "📈 Interest: <b>{$intLabel}</b>\n"
            . "📋 Type: <b>{$typeLabel}</b>\n"
            . "📅 Duration: <b>{$data['duration_label']}</b>\n\n"
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
            mergeData: $extraData,
        );
    }

    private function handleConfirm(Tenant $tenant, TelegramSession $session, string $input): ConversationResult
    {
        $lower = strtolower(trim($input));

        if (in_array($lower, ['yes', 'y', 'confirm', '✅'])) {
            return $this->executeLoanCreation($tenant, $session);
        }

        if (in_array($lower, ['no', 'n', 'cancel', '❌'])) {
            return ConversationResult::complete("❌ Loan creation cancelled.");
        }

        return ConversationResult::stay("Please tap <b>✅ Confirm</b> or <b>❌ Cancel</b>.");
    }

    private function executeLoanCreation(Tenant $tenant, TelegramSession $session): ConversationResult
    {
        $data = $session->temp_data ?? [];

        $admin = \App\Models\User::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $session->telegram_user_id)
            ->first();

        try {
            $loan = $this->loanService->createLoan(
                tenantId: $tenant->id,
                data: [
                    'group_id' => $data['group_id'],
                    'borrower_id' => $data['borrower_id'],
                    'principal' => $data['principal'],
                    'interest_type' => $data['interest_type'] ?? 'percentage',
                    'interest_value' => $data['interest_value'] ?? 0,
                    'loan_type' => $data['loan_type'] ?? 'lump_sum',
                    'loan_date' => now()->toDateString(),
                    'due_date' => $data['due_date'] ?? null,
                    'duration_months' => $data['duration_months'] ?? null,
                ],
                createdBy: $admin,
            );

            $typeLabel = LoanService::displayLoanType($loan->loan_type ?? 'lump_sum');

            return ConversationResult::complete(
                "✅ <b>Loan #{$loan->id} Created!</b>\n\n"
                . "👤 Borrower: <b>{$this->esc($data['borrower_name'] ?? 'N/A')}</b>\n"
                . "💰 Amount: " . number_format($loan->principal, 2) . "\n"
                . "📋 Type: {$typeLabel}\n"
                . ($loan->due_date ? "📅 Due: {$loan->due_date->format('d M Y')}\n" : "📅 No end date\n")
                . "\n✅ Telegram notification sent to group."
            );
        } catch (\Exception $e) {
            return ConversationResult::error("❌ Error creating loan: {$this->esc($e->getMessage())}");
        }
    }
}
