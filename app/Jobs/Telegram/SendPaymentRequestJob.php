<?php

namespace App\Jobs\Telegram;

use App\Models\PaymentSession;
use App\Services\Telegram\TelegramSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send payment request message to Telegram group.
 *
 * Dispatched after PaymentRequestService creates a session.
 * Sends:
 * - Borrower info
 * - Amount due
 * - Payment reference code
 * - QR payload info (if available)
 * - Checkout URL (if available)
 * - Expiry countdown
 *
 * Telegram failures do NOT break the payment session.
 */
class SendPaymentRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private PaymentSession $session,
    ) {
    }

    public function handle(TelegramSender $sender): void
    {
        $session = $this->session->fresh(['loan.borrower', 'loan.group']);

        if (!$session || !$session->loan || !$session->loan->borrower || !$session->loan->group) {
            Log::warning("SendPaymentRequestJob: Missing data for session #{$this->session->id}");
            return;
        }

        $loan = $session->loan;
        $borrower = $loan->borrower;
        $group = $loan->group;

        $settings = is_array($group->settings) ? $group->settings : [];
        $currency = $settings['currency'] ?? '$';

        $borrowerName = $borrower->telegram_username
            ? "@{$borrower->telegram_username}"
            : $borrower->name;

        // Build the payment request message
        $message = "💳 <b>Payment Request</b>\n\n"
            . "👤 Borrower: {$borrowerName}\n"
            . "📋 Loan: #{$loan->id}\n"
            . "💵 Amount Due: {$currency}" . number_format($session->amount, 2) . "\n";

        if ($session->currency !== 'USD') {
            $message .= "💱 Currency: {$session->currency}\n";
        }

        $message .= "\n🔖 <b>Reference:</b>\n"
            . "<code>{$session->reference_code}</code>\n";

        // Show gateway info
        $gatewayLabel = match ($session->gateway_name) {
            'mock' => '🧪 Test Payment',
            'khqr' => '🏦 KHQR',
            'aba' => '🏦 ABA Bank',
            default => ucfirst($session->gateway_name),
        };
        $message .= "\n💳 Gateway: {$gatewayLabel}\n";

        // Show checkout URL if available
        if ($session->checkout_url) {
            $message .= "\n🔗 <b>Pay here:</b>\n{$session->checkout_url}\n";
        }

        // Show QR info
        if ($session->qr_payload) {
            $message .= "\n📱 QR code available for scanning.\n";
        }

        // Show expiry
        if ($session->expires_at) {
            $remaining = $session->remaining_time ?? '60m';
            $message .= "\n⏱ <b>Expires in {$remaining}</b>\n";
        }

        // Loan summary
        $message .= "\n📊 <b>Loan Summary:</b>\n"
            . "  Principal: {$currency}" . number_format($loan->remaining_principal, 2) . "\n"
            . "  Balance: {$currency}" . number_format($loan->balance, 2) . "\n";

        if ($loan->calculateDailyInterest() > 0) {
            $message .= "  Daily Interest: {$currency}" . number_format($loan->calculateDailyInterest(), 2) . "\n";
        }

        $message .= "\n🙏 Please complete the payment before it expires.";

        // Send to the group
        $sent = $sender->sendToGroup(
            $loan->tenant_id,
            $group->telegram_group_id,
            $message
        );

        if (!$sent) {
            Log::error("SendPaymentRequestJob: Failed to send for session #{$session->id}");
        }
    }
}
