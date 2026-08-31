<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class ReminderTemplate extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id', 'name', 'reminder_type', 'target_type',
        'tone', 'message_template', 'enabled', 'is_default',
    ];

    protected $casts = [
        'enabled'    => 'boolean',
        'is_default' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function rules()  { return $this->hasMany(ReminderRule::class, 'template_id'); }

    // ── Scopes ─────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ── Placeholder System ─────────────────────────

    /**
     * Available placeholders for template rendering.
     */
    public static function availablePlaceholders(): array
    {
        return [
            '{borrower_name}'        => 'Borrower full name',
            '{borrower_username}'    => 'Telegram @username',
            '{loan_amount}'          => 'Original loan principal',
            '{outstanding_balance}'  => 'Current outstanding balance',
            '{remaining_principal}'  => 'Remaining unpaid principal',
            '{unpaid_interest}'      => 'Total unpaid accrued interest',
            '{due_date}'             => 'Due date (formatted)',
            '{days_overdue}'         => 'Number of days overdue',
            '{daily_interest}'       => 'Daily interest amount',
            '{today_interest}'       => 'Today\'s interest amount',
            '{unpaid_days}'          => 'Consecutive unpaid days',
            '{total_unpaid}'         => 'Total unpaid interest (cumulative)',
            '{payment_amount}'       => 'Payment amount received',
            '{next_daily_interest}'  => 'Next day\'s interest amount',
            '{penalty_amount}'       => 'Current penalty amount',
            '{installment_number}'   => 'Installment number (e.g. #3)',
            '{installment_amount}'   => 'Installment base amount',
            '{total_due}'            => 'Total due (balance owed)',
            '{group_name}'           => 'Telegram group name',
            '{currency}'             => 'Currency symbol',
        ];
    }

    /**
     * Render this template with loan/installment data.
     */
    public function render(Loan $loan, ?LoanInstallment $installment = null): string
    {
        $borrower = $loan->borrower;
        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        // Calculate unpaid interest (accrued - what's been paid towards interest)
        $totalPaid = $loan->payments()->where('status', 'approved')->sum('amount');
        $paidToPrincipal = $loan->principal - $loan->remaining_principal;
        $paidToInterest = max(0, $totalPaid - $paidToPrincipal);
        $unpaidInterest = max(0, $loan->accrued_interest - $paidToInterest);

        $replacements = [
            '{borrower_name}'        => $borrower?->name ?? 'Unknown',
            '{borrower_username}'    => $borrower?->telegram_username ? '@' . $borrower->telegram_username : ($borrower?->name ?? 'Unknown'),
            '{loan_amount}'          => $currency . number_format($loan->principal, 2),
            '{outstanding_balance}'  => $currency . number_format($loan->balance, 2),
            '{remaining_principal}'  => $currency . number_format($loan->remaining_principal, 2),
            '{unpaid_interest}'      => $currency . number_format($unpaidInterest, 2),
            '{due_date}'             => $loan->due_date ? $loan->due_date->format('d M Y') : '—',
            '{days_overdue}'         => (string) ($loan->days_overdue ?? 0),
            '{daily_interest}'       => $currency . number_format($loan->calculateDailyInterest(), 2),
            '{today_interest}'       => $currency . number_format($loan->calculateDailyInterest(), 2),
            '{unpaid_days}'          => '0',
            '{total_unpaid}'         => $currency . number_format($unpaidInterest, 2),
            '{payment_amount}'       => $currency . '0.00',
            '{next_daily_interest}'  => $currency . number_format($loan->calculateDailyInterest(), 2),
            '{penalty_amount}'       => $currency . '0.00',
            '{installment_number}'   => '—',
            '{installment_amount}'   => '—',
            '{total_due}'            => $currency . number_format($loan->balance, 2),
            '{group_name}'           => $loan->group?->name ?? 'Unknown',
            '{currency}'             => $currency,
        ];

        // Override with installment-specific data if available
        if ($installment) {
            $replacements['{due_date}']            = $installment->due_date->format('d M Y');
            $replacements['{penalty_amount}']      = $currency . number_format($installment->penalty_amount, 2);
            $replacements['{installment_number}']  = '#' . $installment->installment_number;
            $replacements['{installment_amount}']  = $currency . number_format($installment->base_amount, 2);
            $replacements['{total_due}']           = $currency . number_format($installment->total_due, 2);
            $replacements['{outstanding_balance}'] = $currency . number_format($installment->remaining, 2);
            $replacements['{days_overdue}']        = (string) max(0, $installment->days_late ?? 0);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $this->message_template);
    }

    /**
     * Render this template with DailyInterestTracker data (for daily interest reminders).
     */
    public function renderWithTracker(Loan $loan, DailyInterestTracker $tracker): string
    {
        $borrower = $loan->borrower;
        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $currency = $settings['currency'] ?? '$';

        $pastUnpaid = max(0, $tracker->cumulative_unpaid - $tracker->interest_amount);

        $replacements = [
            '{borrower_name}'        => $borrower?->name ?? 'Unknown',
            '{borrower_username}'    => $borrower?->telegram_username ? '@' . $borrower->telegram_username : ($borrower?->name ?? 'Unknown'),
            '{loan_amount}'          => $currency . number_format($loan->principal, 2),
            '{outstanding_balance}'  => $currency . number_format($loan->balance, 2),
            '{remaining_principal}'  => $currency . number_format($loan->remaining_principal, 2),
            '{unpaid_interest}'      => $currency . number_format($pastUnpaid, 2),
            '{due_date}'             => $loan->due_date ? $loan->due_date->format('d M Y') : '',
            '{days_overdue}'         => (string) ($loan->days_overdue ?? 0),
            '{daily_interest}'       => $currency . number_format($tracker->interest_amount, 2),
            '{today_interest}'       => $currency . number_format($tracker->interest_amount, 2),
            '{unpaid_days}'          => (string) $tracker->consecutive_unpaid_days,
            '{total_unpaid}'         => $currency . number_format($tracker->cumulative_unpaid, 2),
            '{total_due}'            => $currency . number_format($tracker->cumulative_unpaid, 2),
            '{payment_amount}'       => $currency . '0.00',
            '{next_daily_interest}'  => $currency . number_format($loan->calculateDailyInterest(), 2),
            '{penalty_amount}'       => $currency . '0.00',
            '{installment_number}'   => '—',
            '{installment_amount}'   => '—',
            '{group_name}'           => $loan->group?->name ?? 'Unknown',
            '{currency}'             => $currency,
        ];

        // Remove {due_date} line entirely if no due date (replace with empty)
        $rendered = str_replace(array_keys($replacements), array_values($replacements), $this->message_template);

        // Clean up empty due date lines (if template has "📆 Due Date: \n")
        $rendered = preg_replace('/📆 Due Date:\s*\n?/', '', $rendered);
        // Clean up double blank lines
        $rendered = preg_replace('/\n{3,}/', "\n\n", $rendered);

        return trim($rendered);
    }
}
