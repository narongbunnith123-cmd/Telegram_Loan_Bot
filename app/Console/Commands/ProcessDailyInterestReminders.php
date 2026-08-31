<?php

namespace App\Console\Commands;

use App\Models\DailyInterestTracker;
use App\Models\Loan;
use App\Models\ReminderTemplate;
use App\Models\TelegramGroup;
use App\Services\Telegram\TelegramSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDailyInterestReminders extends Command
{
    protected $signature = 'reminders:daily-interest
        {--create-records : Create tracker records for today}
        {--send-reminders : Send pending reminders}
        {--force : Ignore time checks, send immediately}
        {--backfill : Backfill missing tracker records for past days}';

    protected $description = 'Create daily interest tracker records and send interest reminders';

    public function handle(TelegramSender $sender): void
    {
        if ($this->option('backfill')) {
            $this->backfillMissingRecords();
        }

        if ($this->option('create-records')) {
            $this->createDailyRecords();
        }

        if ($this->option('send-reminders')) {
            $this->sendReminders($sender);
        }

        // If no flag specified, do both create + send
        if (!$this->option('create-records') && !$this->option('send-reminders') && !$this->option('backfill')) {
            $this->createDailyRecords();
            $this->sendReminders($sender);
        }
    }

    /**
     * Create tracker records for all active loans for today.
     * Also auto-backfills any missed days since the last tracker record.
     */
    private function createDailyRecords(): void
    {
        $loans = Loan::whereIn('status', ['active', 'overdue'])
            ->where('remaining_principal', '>', 0)
            ->with(['borrower'])
            ->get();

        $created = 0;

        foreach ($loans as $loan) {
            // Auto-backfill: check if there are missed days since last tracker
            $lastTrackerDate = DailyInterestTracker::where('loan_id', $loan->id)
                ->orderByDesc('date')
                ->value('date');

            if ($lastTrackerDate) {
                $lastDate = Carbon::parse($lastTrackerDate);
                $startDate = $lastDate->copy()->addDay();

                // Fill in any missed days between last record and today
                while ($startDate->lt(today())) {
                    $created += $this->createRecordForDate($loan, $startDate);
                    $startDate->addDay();
                }
            }

            // Create today's record
            $created += $this->createRecordForDate($loan, today());
        }

        $this->info("Created {$created} daily interest tracker records.");
        Log::info("ProcessDailyInterestReminders: Created {$created} tracker records for " . today()->format('Y-m-d'));
    }

    /**
     * Create a tracker record for a specific loan and date.
     * Returns 1 if created, 0 if skipped.
     */
    private function createRecordForDate(Loan $loan, $date): int
    {
        $date = Carbon::parse($date)->startOfDay();

        // Skip if already exists
        if (DailyInterestTracker::where('loan_id', $loan->id)->where('date', $date)->exists()) {
            return 0;
        }

        $dailyInterest = $loan->calculateDailyInterest();
        if ($dailyInterest <= 0)
            return 0;

        // Count consecutive unpaid days before this date
        $consecutiveUnpaid = DailyInterestTracker::where('loan_id', $loan->id)
            ->where('date', '<', $date)
            ->orderByDesc('date')
            ->get()
            ->takeWhile(fn($t) => !$t->is_paid)
            ->count();

        // Calculate cumulative unpaid interest up to (but not including) this date
        $pastUnpaid = DailyInterestTracker::where('loan_id', $loan->id)
            ->where('is_paid', false)
            ->where('date', '<', $date)
            ->sum('interest_amount');

        $cumulativeUnpaid = $pastUnpaid + $dailyInterest;

        DailyInterestTracker::create([
            'tenant_id' => $loan->tenant_id,
            'loan_id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'date' => $date,
            'interest_amount' => $dailyInterest,
            'cumulative_unpaid' => $cumulativeUnpaid,
            'consecutive_unpaid_days' => $consecutiveUnpaid,
            'stage' => DailyInterestTracker::calculateStage($consecutiveUnpaid),
        ]);

        $borrowerName = $loan->borrower?->name ?? 'Unknown';
        Log::info("Tracker created: loan #{$loan->id} ({$borrowerName}), date {$date->format('Y-m-d')}, interest {$dailyInterest}, stage " . DailyInterestTracker::calculateStage($consecutiveUnpaid));

        return 1;
    }

    /**
     * Backfill missing tracker records from the loan start date or last record.
     * Useful when schedule:work was down for multiple days.
     */
    private function backfillMissingRecords(): void
    {
        $loans = Loan::whereIn('status', ['active', 'overdue'])
            ->where('remaining_principal', '>', 0)
            ->with(['borrower'])
            ->get();

        $totalCreated = 0;

        foreach ($loans as $loan) {
            $lastTrackerDate = DailyInterestTracker::where('loan_id', $loan->id)
                ->orderByDesc('date')
                ->value('date');

            // Start from last tracker date + 1 day, or loan_date if no trackers exist
            $startDate = $lastTrackerDate
                ? Carbon::parse($lastTrackerDate)->addDay()
                : Carbon::parse($loan->loan_date);

            // Don't go further back than 30 days
            $minDate = today()->subDays(30);
            if ($startDate->lt($minDate)) {
                $startDate = $minDate;
            }

            $loanCreated = 0;
            $currentDate = $startDate->copy();

            while ($currentDate->lte(today())) {
                $loanCreated += $this->createRecordForDate($loan, $currentDate);
                $currentDate->addDay();
            }

            if ($loanCreated > 0) {
                $borrowerName = $loan->borrower?->name ?? 'Unknown';
                $this->info("  Backfilled {$loanCreated} records for loan #{$loan->id} ({$borrowerName})");
            }

            $totalCreated += $loanCreated;
        }

        $this->info("Backfill complete: {$totalCreated} total records created.");
        Log::info("ProcessDailyInterestReminders backfill: Created {$totalCreated} records.");
    }

    /**
     * Send reminders based on configurable times, using editable templates.
     */
    private function sendReminders(TelegramSender $sender): void
    {
        $currentTime = now()->format('H:i');
        $forceMode = $this->option('force');
        $sent = 0;
        $skipped = 0;

        // Get all unpaid trackers for today with loan + group data
        $trackers = DailyInterestTracker::forToday()
            ->unpaid()
            ->with(['loan.borrower', 'loan.group'])
            ->get();

        if ($trackers->isEmpty()) {
            $this->warn("No unpaid tracker records for today. Run --create-records first or --backfill if records are missing.");
            Log::warning("ProcessDailyInterestReminders: No unpaid trackers for today " . today()->format('Y-m-d'));
            return;
        }

        $this->info("Found {$trackers->count()} unpaid trackers for today. Current time: {$currentTime}");

        // Pre-load templates by tenant (cache per tenant)
        $templateCache = [];

        foreach ($trackers as $tracker) {
            $loan = $tracker->loan;
            if (!$loan || !$loan->borrower || !$loan->group) {
                $reason = !$loan ? 'no loan' : (!$loan->borrower ? 'no borrower' : 'no group');
                $this->warn("  Skipping tracker #{$tracker->id}: {$reason}");
                Log::warning("Skipping tracker #{$tracker->id}: {$reason}");
                $skipped++;
                continue;
            }

            $group = $loan->group;
            $borrowerName = $loan->borrower->telegram_username
                ? '@' . $loan->borrower->telegram_username
                : $loan->borrower->name;
            $reminderTime1 = $group->reminder_time_1 ?? '17:00';
            $reminderTime2 = $group->reminder_time_2 ?? '21:00';

            // Load templates for this tenant (cached)
            if (!isset($templateCache[$loan->tenant_id])) {
                $templateCache[$loan->tenant_id] = ReminderTemplate::where('tenant_id', $loan->tenant_id)
                    ->whereIn('reminder_type', ['interest_normal', 'interest_warning', 'interest_escalation', 'interest_second'])
                    ->where('enabled', true)
                    ->get()
                    ->keyBy('reminder_type');
            }
            $templates = $templateCache[$loan->tenant_id];

            // ── 1st Reminder ──────────────────────────────────────
            if (!$tracker->reminder_1_sent && ($forceMode || $currentTime >= $reminderTime1)) {
                // Pick template by stage
                $templateType = match ($tracker->stage) {
                    'warning' => 'interest_warning',
                    'escalation' => 'interest_escalation',
                    default => 'interest_normal',
                };

                $template = $templates[$templateType] ?? null;
                $message = $template
                    ? $template->renderWithTracker($loan, $tracker)
                    : $this->buildFallbackMessage($tracker, $loan);

                $this->info("  Sending 1st reminder to {$borrowerName} (loan #{$loan->id}, stage: {$tracker->stage})...");

                $success = $sender->sendToGroup(
                    $loan->tenant_id,
                    $group->telegram_group_id,
                    $message
                );

                if ($success) {
                    $tracker->update([
                        'reminder_1_sent' => true,
                        'reminder_1_sent_at' => now(),
                    ]);
                    $sent++;
                    $this->info("    ✅ 1st reminder sent successfully");
                    Log::info("Daily interest 1st reminder sent: loan #{$loan->id}, borrower {$borrowerName}");
                } else {
                    $this->error("    ❌ 1st reminder FAILED for {$borrowerName}");
                    Log::error("Daily interest 1st reminder failed: loan #{$loan->id}, borrower {$borrowerName}");
                }

                // In force mode, don't also send 2nd immediately — let borrower pay first
                if ($forceMode)
                    continue;
            } elseif (!$tracker->reminder_1_sent) {
                $this->line("  ⏳ {$borrowerName}: 1st reminder waiting (current: {$currentTime}, scheduled: {$reminderTime1})");
            }

            // ── 2nd Reminder ──────────────────────────────────────
            // Reload tracker from DB to get fresh reminder_1_sent state
            // (in case 1st was sent in a previous scheduler run)
            $tracker->refresh();

            if ($tracker->reminder_1_sent && !$tracker->reminder_2_sent && ($forceMode || $currentTime >= $reminderTime2)) {
                $template = $templates['interest_second'] ?? null;
                $message = $template
                    ? $template->renderWithTracker($loan, $tracker)
                    : $this->buildFallback2ndMessage($tracker, $loan);

                $this->info("  Sending 2nd reminder to {$borrowerName} (loan #{$loan->id})...");

                $success = $sender->sendToGroup(
                    $loan->tenant_id,
                    $group->telegram_group_id,
                    $message
                );

                if ($success) {
                    $tracker->update([
                        'reminder_2_sent' => true,
                        'reminder_2_sent_at' => now(),
                    ]);
                    $sent++;
                    $this->info("    ✅ 2nd reminder sent successfully");
                    Log::info("Daily interest 2nd reminder sent: loan #{$loan->id}, borrower {$borrowerName}");
                } else {
                    $this->error("    ❌ 2nd reminder FAILED for {$borrowerName}");
                    Log::error("Daily interest 2nd reminder failed: loan #{$loan->id}, borrower {$borrowerName}");
                }
            } elseif ($tracker->reminder_1_sent && !$tracker->reminder_2_sent) {
                $this->line("  ⏳ {$borrowerName}: 2nd reminder waiting (current: {$currentTime}, scheduled: {$reminderTime2})");
            }
        }

        $this->info("Sent {$sent} interest reminders, skipped {$skipped}.");
        Log::info("ProcessDailyInterestReminders: Sent {$sent}, skipped {$skipped} for " . today()->format('Y-m-d'));
    }

    /**
     * Fallback 1st reminder if no template found in DB.
     */
    private function buildFallbackMessage(DailyInterestTracker $tracker, Loan $loan): string
    {
        $borrower = $loan->borrower;
        $username = $borrower->telegram_username ? "@{$borrower->telegram_username}" : $borrower->name;
        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        return "📌 <b>Daily Interest Reminder</b>\n\n"
            . "{$username}\n\n"
            . "💰 Borrowed: {$currency}" . number_format($loan->principal, 2) . "\n"
            . "📅 Today's Interest: {$currency}" . number_format($tracker->interest_amount, 2) . "\n"
            . "💵 Total Due: {$currency}" . number_format($tracker->cumulative_unpaid, 2) . "\n"
            . "📊 Remaining Principal: {$currency}" . number_format($loan->remaining_principal, 2) . "\n\n"
            . "Please pay today's interest before tonight.";
    }

    /**
     * Fallback 2nd reminder if no template found in DB.
     */
    private function buildFallback2ndMessage(DailyInterestTracker $tracker, Loan $loan): string
    {
        $borrower = $loan->borrower;
        $username = $borrower->telegram_username ? "@{$borrower->telegram_username}" : $borrower->name;
        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        return "⚠️ <b>Second Reminder</b>\n\n"
            . "{$username}\n\n"
            . "Your interest payment is still unpaid today.\n\n"
            . "🔴 Total Due: {$currency}" . number_format($tracker->cumulative_unpaid, 2) . "\n\n"
            . "Please complete payment to avoid additional accumulation tomorrow.";
    }
}
