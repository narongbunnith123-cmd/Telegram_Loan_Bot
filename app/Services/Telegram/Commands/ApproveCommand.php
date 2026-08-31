<?php

namespace App\Services\Telegram\Commands;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\PaymentService;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class ApproveCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
        private PaymentService $paymentService,
    ) {
    }

    /**
     * Usage: /approve 123
     * Also called from inline button callbacks.
     */
    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized.");
            return;
        }

        $text = data_get($message, 'text', '');
        $parts = preg_split('/\s+/', trim($text));
        $paymentId = (int) ($parts[1] ?? 0);

        if (!$paymentId) {
            $this->sender->sendToGroup(
                $tenant->id,
                $chatId,
                "📝 Usage: <code>/approve PAYMENT_ID</code>\n\nExample: <code>/approve 123</code>"
            );
            return;
        }

        $result = $this->approvePayment($tenant, $paymentId, $userId);
        $this->sender->sendToGroup($tenant->id, $chatId, $result);
    }

    /**
     * Approve a payment by ID. Returns the response message.
     * Shared between text command and inline button callback.
     * Uses shared PaymentService for consistent business logic.
     */
    public function approvePayment(Tenant $tenant, int $paymentId, string $adminTelegramId): string
    {
        $payment = Payment::where('tenant_id', $tenant->id)
            ->where('id', $paymentId)
            ->with('loan.borrower', 'loan.group')
            ->first();

        if (!$payment) {
            return "❌ Payment #{$paymentId} not found.";
        }

        if ($payment->status !== 'pending') {
            return "⚠️ Payment #{$paymentId} is already <b>{$payment->status}</b>.";
        }

        $admin = $this->guard->getAdmin($tenant, $adminTelegramId);

        try {
            // Use shared PaymentService — same logic as web dashboard
            $this->paymentService->approvePayment($payment, $admin);
        } catch (\Exception $e) {
            return "❌ Error approving payment: {$this->esc($e->getMessage())}";
        }

        $loan = $payment->loan->fresh();
        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';
        $borrowerName = $this->esc($payment->loan->borrower->name ?? 'Unknown');

        $msg = "✅ <b>Payment #{$paymentId} Approved</b>\n\n"
            . "👤 Borrower: {$borrowerName}\n"
            . "💵 Amount: {$currency}" . number_format($payment->amount, 2) . "\n"
            . "💰 New Balance: {$currency}" . number_format($loan->balance, 2);

        if ($loan->status === 'paid' || $loan->status === 'completed') {
            $msg .= "\n\n🎉 <b>Loan #{$loan->id} is now fully paid!</b>";
        }

        return $msg;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
