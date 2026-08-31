<?php

namespace App\Services\Telegram\Conversations;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\TelegramSession;
use App\Models\Tenant;
use App\Services\Telegram\TelegramSender;

class ReportsConversation extends BaseConversation
{
    public function __construct(
        private TelegramSender $sender,
    ) {
    }

    public static function action(): string
    {
        return 'reports';
    }

    public function firstStep(): string
    {
        return 'show_report';
    }

    public function steps(): array
    {
        return ['show_report'];
    }

    public function previousStep(string $currentStep): ?string
    {
        return null;
    }

    public function handleStep(
        Tenant $tenant,
        TelegramSession $session,
        string $input,
        array $message,
    ): ConversationResult {
        return $this->generateReport($tenant);
    }

    public function handleCallback(
        Tenant $tenant,
        TelegramSession $session,
        string $callbackData,
        array $callbackQuery,
    ): ConversationResult {
        return $this->generateReport($tenant);
    }

    /**
     * Generate the quick operational summary and return it.
     */
    public function generateReport(Tenant $tenant): ConversationResult
    {
        $activeLoans = Loan::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'overdue'])
            ->get();

        $totalActive = $activeLoans->count();
        $totalOverdue = $activeLoans->where('status', 'overdue')->count();
        $totalOutstanding = $activeLoans->sum('balance');
        $totalPrincipal = $activeLoans->sum('remaining_principal');

        $todayPayments = Payment::where('tenant_id', $tenant->id)
            ->where('status', 'approved')
            ->whereDate('approved_at', today())
            ->get();

        $collectedToday = $todayPayments->sum('amount');
        $paymentCount = $todayPayments->count();

        $pendingPayments = Payment::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->count();

        $dailyInterest = $activeLoans->sum(fn($loan) => $loan->calculateDailyInterest());

        $appUrl = config('app.url');

        $msg = "📊 <b>Quick Report</b>\n\n"
            . "━━━ Loans ━━━━━━━━━━━━━━\n"
            . "📋 Active: <b>{$totalActive}</b>\n"
            . "🔴 Overdue: <b>{$totalOverdue}</b>\n"
            . "💰 Outstanding: <b>\$" . number_format($totalOutstanding, 2) . "</b>\n"
            . "📊 Principal: <b>\$" . number_format($totalPrincipal, 2) . "</b>\n"
            . "📈 Daily Interest: <b>\$" . number_format($dailyInterest, 2) . "</b>\n\n"
            . "━━━ Today ━━━━━━━━━━━━━━\n"
            . "💵 Collected: <b>\$" . number_format($collectedToday, 2) . "</b> ({$paymentCount} payments)\n"
            . "⏳ Pending: <b>{$pendingPayments}</b> payments\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━\n"
            . "📱 <b>Full dashboard:</b>\n{$appUrl}";

        return ConversationResult::complete($msg);
    }
}
