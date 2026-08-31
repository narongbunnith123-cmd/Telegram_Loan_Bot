<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\ReminderTemplate;
use App\Jobs\Telegram\SendReminderJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ReminderEngine
{
    /**
     * Process all loans that need reminders.
     * Called by the scheduler hourly.
     */
    public function processAll(): array
    {
        $dispatched = 0;
        $skipped = 0;

        // Get all active/overdue loans with reminders enabled
        $loans = Loan::query()
            ->whereIn('status', ['active', 'overdue'])
            ->where('balance', '>', 0)
            ->where('reminders_enabled', true)
            ->with(['borrower', 'group', 'installments'])
            ->cursor();

        foreach ($loans as $loan) {
            if (!$loan->borrower || !$loan->group)
                continue;

            // Rate limit per tenant: max 30 reminders per minute
            $rateLimitKey = "reminders:tenant:{$loan->tenant_id}";
            if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
                $skipped++;
                continue;
            }

            $matchedRules = $this->getMatchingRules($loan);

            foreach ($matchedRules as $match) {
                $rule = $match['rule'];

                // Check if it's time to send (send_time restriction)
                if ($rule->send_time && now()->format('H:i') < $rule->send_time) {
                    $skipped++;
                    continue;
                }

                if ($this->canSend($loan, $match)) {
                    $this->dispatchReminder($loan, $match);
                    RateLimiter::hit($rateLimitKey, 60);
                    $dispatched++;
                } else {
                    $skipped++;
                }
            }
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    /**
     * Find reminder rules that match this loan's current state.
     */
    public function getMatchingRules(Loan $loan): array
    {
        $rules = ReminderRule::where('tenant_id', $loan->tenant_id)
            ->where('enabled', true)
            ->get();

        $matching = [];

        if ($loan->isInstallmentLoan()) {
            // Check rules against the next due/overdue installment
            $installment = $loan->nextDueInstallment();
            if (!$installment)
                return [];

            $daysDiff = (int) $installment->due_date->diffInDays(today(), false);

            foreach ($rules as $rule) {
                if ($rule->shouldFire($daysDiff) && $rule->matchesFrequency($daysDiff)) {
                    $matching[] = ['rule' => $rule, 'installment' => $installment];
                }
            }
        } else {
            // Lump-sum: check rules against the loan due date
            // Skip loans without due_date — they only get daily interest reminders
            if (!$loan->due_date)
                return [];

            $daysDiff = (int) $loan->due_date->diffInDays(today(), false);

            foreach ($rules as $rule) {
                if ($rule->shouldFire($daysDiff) && $rule->matchesFrequency($daysDiff)) {
                    $matching[] = ['rule' => $rule, 'installment' => null];
                }
            }
        }

        return $matching;
    }

    /**
     * Check if a reminder can be sent (cooldown + duplicate prevention).
     */
    public function canSend(Loan $loan, array $ruleData): bool
    {
        $rule = $ruleData['rule'];

        // Check cooldown
        if ($rule->isOnCooldown($loan->id)) {
            return false;
        }

        // Check idempotency: don't send same rule + loan + date combination
        $idempotencyKey = $this->generateIdempotencyKey($loan, $rule, $ruleData['installment']);

        return !Reminder::where('idempotency_key', $idempotencyKey)->exists();
    }

    /**
     * Dispatch the reminder job for a loan + rule combination.
     */
    public function dispatchReminder(Loan $loan, array $ruleData): void
    {
        $rule = $ruleData['rule'];
        $installment = $ruleData['installment'];

        // Determine which targets to send to
        $targets = [];
        if ($rule->send_to_group)
            $targets[] = 'group';
        if ($rule->send_to_dm)
            $targets[] = 'dm';
        if ($rule->send_to_admin)
            $targets[] = 'admin';

        foreach ($targets as $target) {
            $idempotencyKey = $this->generateIdempotencyKey($loan, $rule, $installment, $target);

            // Skip if already queued
            if (Reminder::where('idempotency_key', $idempotencyKey)->exists())
                continue;

            // Find the best template
            $template = $rule->template ?? $this->findDefaultTemplate($loan->tenant_id, $rule->reminder_type, $target);

            // Create reminder record first (pending)
            $renderedMessage = $template
                ? $template->render($loan, $installment)
                : $this->buildFallbackMessage($loan, $installment, $rule->reminder_type);

            // Resolve telegram_chat_id based on target
            $telegramChatId = match ($target) {
                'group' => $loan->group?->telegram_group_id,
                'dm' => $loan->borrower?->telegram_user_id,
                'admin' => null, // Admin alerts use admin's chat_id resolved at send time
                default => null,
            };

            $reminder = Reminder::create([
                'tenant_id' => $loan->tenant_id,
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
                'rule_id' => $rule->id,
                'template_id' => $template?->id,
                'installment_id' => $installment?->id,
                'type' => $rule->reminder_type,
                'target_type' => $target,
                'telegram_chat_id' => $telegramChatId,
                'message_snapshot' => $renderedMessage,
                'rendered_message' => $renderedMessage,
                'scheduled_at' => now(),
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Dispatch the job
            SendReminderJob::dispatch($loan, $rule->reminder_type, $reminder->id)
                ->onQueue('reminders');
        }

        // Update loan's last reminder timestamp
        $loan->update(['last_reminder_sent_at' => now()]);
    }

    /**
     * Find a default template for a given type and target.
     */
    private function findDefaultTemplate(int $tenantId, string $reminderType, string $targetType): ?ReminderTemplate
    {
        return ReminderTemplate::query()
            ->forTenant($tenantId)
            ->where('reminder_type', $reminderType)
            ->where('target_type', $targetType)
            ->where('enabled', true)
            ->orderByDesc('tenant_id') // tenant-specific takes priority over system default
            ->first();
    }

    /**
     * Generate a unique idempotency key for duplicate prevention.
     */
    private function generateIdempotencyKey(Loan $loan, ReminderRule $rule, ?LoanInstallment $installment, string $target = 'group'): string
    {
        $parts = [
            $loan->id,
            $rule->id,
            $installment?->id ?? 'loan',
            $target,
            today()->toDateString(),
        ];

        return implode(':', $parts);
    }

    /**
     * Build a fallback message when no template is available.
     */
    private function buildFallbackMessage(Loan $loan, ?LoanInstallment $installment, string $type): string
    {
        $borrower = $loan->borrower;
        $name = $borrower?->telegram_username
            ? '@' . $borrower->telegram_username
            : ($borrower?->name ?? 'Unknown');

        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        if ($installment) {
            return "⚠️ Loan Reminder\n\n"
                . "Borrower: {$name}\n"
                . "Installment: #{$installment->installment_number}\n"
                . "Amount Due: {$currency}" . number_format($installment->total_due, 2) . "\n"
                . "Due Date: {$installment->due_date->format('d M Y')}\n"
                . ($installment->penalty_amount > 0 ? "Penalty: {$currency}" . number_format($installment->penalty_amount, 2) . "\n" : "")
                . "\nPlease settle your payment.";
        }

        return "⚠️ Loan Reminder\n\n"
            . "Borrower: {$name}\n"
            . "Balance: {$currency}" . number_format($loan->balance, 2) . "\n"
            . ($loan->due_date ? "Due Date: {$loan->due_date->format('d M Y')}\n" : "")
            . "\nPlease settle your payment.";
    }
}
