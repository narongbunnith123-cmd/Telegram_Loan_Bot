<?php

namespace App\Services\Telegram\Commands;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\PaymentService;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class RejectCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
        private PaymentService $paymentService,
    ) {
    }

    /**
     * Usage: /reject 123 Optional reason text
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
        $parts = preg_split('/\s+/', trim($text), 3);
        $paymentId = (int) ($parts[1] ?? 0);
        $reason = $parts[2] ?? null;

        if (!$paymentId) {
            $this->sender->sendToGroup(
                $tenant->id,
                $chatId,
                "📝 Usage: <code>/reject PAYMENT_ID reason</code>\n\nExample: <code>/reject 123 Blurry screenshot</code>"
            );
            return;
        }

        $result = $this->rejectPayment($tenant, $paymentId, $adminTelegramId = $userId, $reason);
        $this->sender->sendToGroup($tenant->id, $chatId, $result);
    }

    /**
     * Reject a payment by ID. Shared between text command and callback.
     * Uses shared PaymentService for consistent logic.
     */
    public function rejectPayment(Tenant $tenant, int $paymentId, string $adminTelegramId, ?string $reason = null): string
    {
        $payment = Payment::where('tenant_id', $tenant->id)
            ->where('id', $paymentId)
            ->with('loan.borrower', 'loan.group')
            ->first();

        if (!$payment) {
            return "❌ Payment \\#{$paymentId} not found.";
        }

        if ($payment->status !== 'pending') {
            return "⚠️ Payment \\#{$paymentId} is already <b>{$payment->status}</b>.";
        }

        $admin = $this->guard->getAdmin($tenant, $adminTelegramId);

        try {
            $this->paymentService->rejectPayment($payment, $admin);
        } catch (\Exception $e) {
            return "❌ Error: {$this->esc($e->getMessage())}";
        }

        $borrowerName = $this->esc($payment->loan->borrower->name ?? 'Unknown');
        $settings = is_array($payment->loan->group?->settings) ? $payment->loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        $msg = "❌ <b>Payment \\#{$paymentId} Rejected</b>\n\n"
            . "👤 Borrower: {$borrowerName}\n"
            . "💵 Amount: {$currency}{$payment->amount}";

        if ($reason) {
            $msg .= "\n📝 Reason: {$this->esc($reason)}";
        }

        return $msg;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
