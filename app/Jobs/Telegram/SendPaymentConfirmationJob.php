<?php

namespace App\Jobs\Telegram;

use App\Models\Payment;
use App\Services\Telegram\TelegramSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private Payment $payment,
    ) {
    }

    public function handle(TelegramSender $sender): void
    {
        $payment = $this->payment->fresh(['loan.borrower', 'loan.group', 'installment']);

        if (!$payment || !$payment->loan || !$payment->loan->borrower || !$payment->loan->group) {
            Log::warning("SendPaymentConfirmationJob: Missing data for payment #{$this->payment->id}");
            return;
        }

        $loan = $payment->loan;
        $borrower = $loan->borrower;
        $username = $borrower->telegram_username
            ? "@{$borrower->telegram_username}"
            : $borrower->name;

        $settings = is_array($loan->group->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        // Use HTML formatting (not MarkdownV2)
        $message = "✅ <b>Payment Received</b>\n\n"
            . "👤 Borrower: {$username}\n"
            . "💵 Paid: {$currency}" . number_format($payment->amount, 2) . "\n";

        if ($payment->penalty_paid > 0) {
            $message .= "⚠️ Penalty Paid: {$currency}" . number_format($payment->penalty_paid, 2) . "\n";
        }

        // Show reference code if present (session-based payment)
        if ($payment->reference_code) {
            $message .= "🔖 Reference: <code>{$payment->reference_code}</code>\n";
        }

        // Show gateway if present
        if ($payment->gateway_name) {
            $gatewayLabel = match ($payment->gateway_name) {
                'mock' => 'Test',
                'khqr' => 'KHQR',
                'aba' => 'ABA Bank',
                default => ucfirst($payment->gateway_name),
            };
            $message .= "💳 Via: {$gatewayLabel}\n";
        }

        if ($payment->installment) {
            $inst = $payment->installment;
            $message .= "\n📋 Installment: #{$inst->installment_number}\n"
                . "   Status: " . ucfirst($inst->status) . "\n";
        }

        // Refresh loan balance
        $loan->refresh();
        $message .= "\n📊 <b>Loan Summary:</b>\n"
            . "   Remaining Principal: {$currency}" . number_format($loan->remaining_principal, 2) . "\n"
            . "   Remaining Balance: {$currency}" . number_format($loan->balance, 2) . "\n";

        if ($loan->remaining_principal <= 0 && $loan->balance <= 0) {
            $message .= "\n🎉 <b>Loan fully paid!</b> Congratulations!";
        } else {
            // Show next daily interest so borrower sees it's lower now
            $nextInterest = $loan->calculateDailyInterest();
            if ($nextInterest > 0) {
                $message .= "   📅 New Daily Interest: {$currency}" . number_format($nextInterest, 2) . "/day\n";
            }

            if ($loan->isInstallmentLoan()) {
                $next = $loan->nextDueInstallment();
                if ($next) {
                    $message .= "   📆 Next Due: {$next->due_date->format('d M Y')} ({$currency}" . number_format($next->base_amount, 2) . ")\n";
                }
            }
        }

        $message .= "\nThank you for your payment! 🙏";

        $sent = $sender->sendToGroup(
            $loan->tenant_id,
            $loan->group->telegram_group_id,
            $message
        );

        if (!$sent) {
            Log::error("SendPaymentConfirmationJob: Failed to send for payment #{$payment->id}");
        }
    }
}
