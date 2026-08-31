<?php

namespace App\Services\Telegram\Conversations;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Services\PaymentService;
use App\Services\Telegram\TelegramSender;

class RecordPaymentConversation extends BaseConversation
{
    public function __construct(
        private PaymentService $paymentService,
        private TelegramSender $sender,
    ) {
    }

    public static function action(): string
    {
        return 'record_payment';
    }

    public function firstStep(): string
    {
        return 'select_borrower';
    }

    public function steps(): array
    {
        return ['select_borrower', 'select_loan', 'enter_amount', 'select_method', 'enter_note', 'confirm'];
    }

    public function previousStep(string $currentStep): ?string
    {
        return match ($currentStep) {
            'select_loan' => 'select_borrower',
            'enter_amount' => 'select_loan',
            'select_method' => 'enter_amount',
            'enter_note' => 'select_method',
            'confirm' => 'enter_note',
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
            'select_borrower' => $this->handleSelectBorrower($tenant, $session, $input),
            'select_loan' => $this->handleSelectLoan($tenant, $session, $input),
            'enter_amount' => $this->handleEnterAmount($session, $input),
            'select_method' => $this->handleSelectMethod($session, $input),
            'enter_note' => $this->handleEnterNote($session, $input),
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
        if (str_starts_with($callbackData, 'borrower:')) {
            $borrowerId = (int) str_replace('borrower:', '', $callbackData);
            return $this->selectBorrower($tenant, $session, $borrowerId);
        }

        if (str_starts_with($callbackData, 'loan:')) {
            $loanId = (int) str_replace('loan:', '', $callbackData);
            return $this->selectLoan($session, $loanId);
        }

        if (str_starts_with($callbackData, 'method_')) {
            $method = str_replace('method_', '', $callbackData);
            return $this->selectMethod($session, $method);
        }

        if ($callbackData === 'confirm_yes') {
            return $this->executePayment($tenant, $session);
        }

        if ($callbackData === 'confirm_no') {
            return ConversationResult::complete("❌ Payment cancelled.");
        }

        return ConversationResult::stay("❌ Invalid selection.");
    }

    /**
     * Entry point: Show borrowers with active loans.
     */
    public function promptSelectBorrower(Tenant $tenant, TelegramSession $session): ConversationResult
    {
        $borrowers = Borrower::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereHas('loans', fn($q) => $q->whereIn('status', ['active', 'overdue']))
            ->orderBy('name')
            ->take(20)
            ->get();

        if ($borrowers->isEmpty()) {
            return ConversationResult::error("❌ No borrowers with active loans found.");
        }

        $rows = $borrowers->map(fn($b) => [
            ['text' => "👤 {$b->name}", 'callback_data' => "borrower:{$b->id}"],
        ])->toArray();

        return ConversationResult::advance(
            'select_borrower',
            "💵 <b>Record Payment</b>\n\n👤 <b>Select borrower:</b>",
            inlineKeyboard: $rows,
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

        $loans = Loan::where('borrower_id', $borrowerId)
            ->whereIn('status', ['active', 'overdue'])
            ->with('group')
            ->get();

        if ($loans->isEmpty()) {
            return ConversationResult::error("❌ {$this->esc($borrower->name)} has no active loans.");
        }

        // If only one loan, skip selection
        if ($loans->count() === 1) {
            $loan = $loans->first();
            return $this->selectLoan($session, $loan->id, [
                'borrower_id' => $borrowerId,
                'borrower_name' => $borrower->name,
            ]);
        }

        $settings = is_array($loans->first()->group?->settings) ? $loans->first()->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        $rows = $loans->map(fn($l) => [
            ['text' => "💰 #{$l->id} — {$currency}" . number_format($l->balance, 2), 'callback_data' => "loan:{$l->id}"],
        ])->toArray();

        return ConversationResult::advance(
            'select_loan',
            "👤 Borrower: <b>{$this->esc($borrower->name)}</b>\n\n💰 <b>Select loan:</b>",
            inlineKeyboard: $rows,
            mergeData: ['borrower_id' => $borrowerId, 'borrower_name' => $borrower->name],
        );
    }

    private function handleSelectLoan(Tenant $tenant, TelegramSession $session, string $input): ConversationResult
    {
        $loanId = (int) str_replace('#', '', $input);
        if ($loanId <= 0) {
            return ConversationResult::stay("❌ Enter a valid loan number.");
        }
        return $this->selectLoan($session, $loanId);
    }

    private function selectLoan(TelegramSession $session, int $loanId, array $extraMerge = []): ConversationResult
    {
        $loan = Loan::with('group')->find($loanId);
        if (!$loan) {
            return ConversationResult::stay("❌ Loan not found.");
        }

        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        return ConversationResult::advance(
            'enter_amount',
            "💰 <b>Loan #{$loan->id}</b>\n"
            . "💵 Balance: {$currency}" . number_format($loan->balance, 2) . "\n"
            . "📊 Principal: {$currency}" . number_format($loan->remaining_principal, 2) . "\n\n"
            . "💵 <b>Enter payment amount:</b>",
            mergeData: array_merge($extraMerge, [
                'loan_id' => $loanId,
                'currency' => $currency,
                'loan_balance' => $loan->balance,
            ]),
        );
    }

    private function handleEnterAmount(TelegramSession $session, string $input): ConversationResult
    {
        $amount = (float) str_replace([',', ' '], '', $input);

        if ($amount <= 0) {
            return ConversationResult::stay("❌ Amount must be greater than 0.");
        }

        $rows = [
            [
                ['text' => '🏦 Bank', 'callback_data' => 'method_bank'],
                ['text' => '💵 Cash', 'callback_data' => 'method_cash'],
            ],
            [
                ['text' => '📱 Wallet', 'callback_data' => 'method_wallet'],
                ['text' => '⏭ Skip', 'callback_data' => 'method_skip'],
            ],
        ];

        $currency = $session->getData('currency', '$');

        return ConversationResult::advance(
            'select_method',
            "💵 Amount: <b>{$currency}" . number_format($amount, 2) . "</b>\n\n"
            . "💳 <b>Payment method:</b>",
            inlineKeyboard: $rows,
            mergeData: ['amount' => $amount],
        );
    }

    private function handleSelectMethod(TelegramSession $session, string $input): ConversationResult
    {
        $lower = strtolower(trim($input));

        $method = match ($lower) {
            'bank' => 'bank',
            'cash' => 'cash',
            'wallet' => 'wallet',
            'skip', 'none' => null,
            default => null,
        };

        return $this->selectMethod($session, $method ?? 'skip');
    }

    private function selectMethod(TelegramSession $session, string $method): ConversationResult
    {
        $methodLabel = match ($method) {
            'bank' => '🏦 Bank',
            'cash' => '💵 Cash',
            'wallet' => '📱 Wallet',
            default => 'Not specified',
        };

        return ConversationResult::advance(
            'enter_note',
            "💳 Method: <b>{$methodLabel}</b>\n\n📝 <b>Add a note</b> (or type <b>SKIP</b>):",
            mergeData: ['method' => $method === 'skip' ? null : $method],
        );
    }

    private function handleEnterNote(TelegramSession $session, string $input): ConversationResult
    {
        $note = strtoupper(trim($input)) === 'SKIP' ? null : trim($input);

        $data = array_merge($session->temp_data ?? [], ['notes' => $note]);
        $currency = $data['currency'] ?? '$';

        $msg = "📋 <b>Confirm Payment</b>\n\n"
            . "👤 Borrower: <b>{$this->esc($data['borrower_name'] ?? 'N/A')}</b>\n"
            . "💰 Loan: <b>#{$data['loan_id']}</b>\n"
            . "💵 Amount: <b>{$currency}" . number_format($data['amount'] ?? 0, 2) . "</b>\n"
            . "💳 Method: <b>" . ($data['method'] ?? 'N/A') . "</b>\n"
            . ($note ? "📝 Note: {$this->esc($note)}\n" : "")
            . "\nIs this correct?";

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
            mergeData: ['notes' => $note],
        );
    }

    private function handleConfirm(Tenant $tenant, TelegramSession $session, string $input): ConversationResult
    {
        $lower = strtolower(trim($input));

        if (in_array($lower, ['yes', 'y', 'confirm', '✅'])) {
            return $this->executePayment($tenant, $session);
        }

        if (in_array($lower, ['no', 'n', 'cancel', '❌'])) {
            return ConversationResult::complete("❌ Payment cancelled.");
        }

        return ConversationResult::stay("Please tap <b>✅ Confirm</b> or <b>❌ Cancel</b>.");
    }

    private function executePayment(Tenant $tenant, TelegramSession $session): ConversationResult
    {
        $data = $session->temp_data ?? [];
        $loan = Loan::find($data['loan_id']);

        if (!$loan) {
            return ConversationResult::error("❌ Loan not found.");
        }

        $admin = \App\Models\User::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $session->telegram_user_id)
            ->first();

        try {
            $payment = $this->paymentService->recordPayment(
                loan: $loan,
                data: [
                    'amount' => $data['amount'],
                    'type' => 'partial',
                    'method' => $data['method'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                approver: $admin,
                autoApprove: true,
            );

            $currency = $data['currency'] ?? '$';
            $loan->refresh();

            $msg = "✅ <b>Payment Recorded!</b>\n\n"
                . "👤 Borrower: {$this->esc($data['borrower_name'] ?? 'N/A')}\n"
                . "💵 Amount: {$currency}" . number_format($data['amount'], 2) . "\n"
                . "💰 New Balance: {$currency}" . number_format($loan->balance, 2) . "\n";

            if ($loan->status === 'paid' || $loan->status === 'completed') {
                $msg .= "\n🎉 <b>Loan fully paid!</b>";
            }

            $msg .= "\n✅ Confirmation sent to group.";

            return ConversationResult::complete($msg);
        } catch (\Exception $e) {
            return ConversationResult::error("❌ Error: {$this->esc($e->getMessage())}");
        }
    }
}
