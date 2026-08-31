<?php

namespace App\Jobs\Telegram;

use App\Models\Loan;
use App\Services\Telegram\TelegramSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLoanCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        private Loan $loan,
    ) {}

    public function handle(TelegramSender $sender): void
    {
        $loan = $this->loan->fresh(['borrower', 'group', 'installments']);

        if (!$loan || !$loan->borrower || !$loan->group) {
            Log::warning("SendLoanCreatedJob: Missing borrower or group for loan #{$this->loan->id}");
            return;
        }

        $borrower = $loan->borrower;
        $username = $borrower->telegram_username
            ? "@{$borrower->telegram_username}"
            : $borrower->name;

        $settings = is_array($loan->group->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        // Use HTML formatting (not MarkdownV2)
        $message = "✅ <b>New Loan Created</b>\n\n"
            . "Borrower: {$username}\n"
            . "Loan Amount: {$currency}" . number_format($loan->principal, 2) . "\n";

        if ($loan->isInstallmentLoan()) {
            $message .= "Monthly Payment: {$currency}" . number_format($loan->monthly_installment, 2) . "\n"
                . "Duration: {$loan->duration_months} months\n";
        }

        if ($loan->hasPenalties()) {
            if ($loan->penalty_type === 'fixed') {
                $message .= "Daily Penalty: {$currency}{$loan->penalty_value}/day\n";
            } else {
                $message .= "Daily Penalty: {$loan->penalty_value}%/day\n";
            }
        }

        if ($loan->due_date) {
            $message .= "📆 Due Date: {$loan->due_date->format('d M Y')}\n";
        }

        if ($loan->isInstallmentLoan()) {
            $firstInstallment = $loan->installments()->orderBy('installment_number')->first();
            if ($firstInstallment) {
                $message .= "First Installment: {$firstInstallment->due_date->format('d M Y')}\n";
            }
        }

        $sent = $sender->sendToGroup(
            $loan->tenant_id,
            $loan->group->telegram_group_id,
            $message
        );

        if (!$sent) {
            Log::error("SendLoanCreatedJob: Failed to send for loan #{$loan->id}");
        }
    }
}
