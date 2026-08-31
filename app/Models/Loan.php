<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'group_id',
        'borrower_id',
        'created_by',
        'principal',
        'balance',
        'remaining_principal',
        'accrued_interest',
        'daily_interest_rate',
        'interest_type',
        'interest_value',
        'loan_date',
        'due_date',
        'status',
        'paid_at',
        'notes',
        // Installment fields
        'loan_type',
        'duration_months',
        'monthly_installment',
        'penalty_type',
        'penalty_value',
        'grace_days',
        'max_penalty_percent',
        'reminders_enabled',
        'reminder_stage',
        'last_reminder_sent_at',
        'next_reminder_at',
    ];

    protected $casts = [
        'loan_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'next_reminder_at' => 'datetime',
        'principal' => 'decimal:2',
        'balance' => 'decimal:2',
        'remaining_principal' => 'decimal:2',
        'accrued_interest' => 'decimal:2',
        'daily_interest_rate' => 'decimal:6',
        'monthly_installment' => 'decimal:2',
        'reminders_enabled' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }
    public function group()
    {
        return $this->belongsTo(TelegramGroup::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function interestLogs()
    {
        return $this->hasMany(LoanInterestLog::class);
    }
    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
    public function installments()
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_number');
    }
    public function penaltyLogs()
    {
        return $this->hasMany(PenaltyLog::class);
    }

    // ── Type Checks ────────────────────────────────

    public function isInstallmentLoan(): bool
    {
        return $this->loan_type === 'installment';
    }

    public function isLumpSum(): bool
    {
        return $this->loan_type !== 'installment';
    }

    public function hasPenalties(): bool
    {
        return $this->penalty_type !== 'none' && $this->penalty_type !== null;
    }

    // ── Installment Helpers ────────────────────────

    /**
     * Get the next unpaid installment.
     */
    public function nextDueInstallment(): ?LoanInstallment
    {
        return $this->installments()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_number')
            ->first();
    }

    /**
     * Get all overdue installments (past due date and not fully paid).
     */
    public function overdueInstallments()
    {
        return $this->installments()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', today());
    }

    /**
     * Total penalties across all installments.
     */
    public function totalPenalties(): float
    {
        return (float) $this->installments()->sum('penalty_amount');
    }

    // ── Interest Calculation ───────────────────────

    /**
     * Calculate daily interest from remaining principal.
     * Uses daily_interest_rate (derived from original interest_value/principal).
     * Non-compound: never calculates interest on old interest.
     */
    public function calculateDailyInterest(): float
    {
        if ($this->remaining_principal <= 0)
            return 0;
        return round($this->remaining_principal * $this->daily_interest_rate, 2);
    }

    /**
     * Recalculate balance = remaining_principal + accrued_interest - total_payments.
     */
    public function recalculateBalance(): void
    {
        $totalPaid = $this->payments()->where('status', 'approved')->sum('amount');
        $this->balance = max(0, $this->remaining_principal + $this->accrued_interest - $totalPaid);
        $this->save();
    }

    /**
     * Calculate daily penalty for a given installment balance.
     */
    public function calculateDailyPenalty(float $installmentBalance): float
    {
        if ($this->penalty_type === 'none' || !$this->penalty_value) {
            return 0;
        }

        if ($this->penalty_type === 'fixed') {
            return (float) $this->penalty_value;
        }

        // percentage of installment balance
        return round($installmentBalance * ($this->penalty_value / 100), 2);
    }

    // ── Attributes ─────────────────────────────────

    public function getDaysOverdueAttribute(): int
    {
        if (!in_array($this->status, ['overdue', 'active']))
            return 0;
        if (!$this->due_date)
            return 0; // Revolving loans with no end date
        return max(0, (int) now()->diffInDays($this->due_date, false) * -1);
    }

    // ── Status Helpers ────────────────────────────

    /**
     * Check and auto-mark loan as 'completed' when all installments are paid.
     * Plan status: active → completed (all paid), defaulted (90+ overdue).
     */
    public function checkCompleted(): bool
    {
        if (!$this->isInstallmentLoan())
            return false;

        $allPaid = $this->installments()
            ->where('status', '!=', 'paid')
            ->doesntExist();

        if ($allPaid) {
            $this->update([
                'status' => 'completed',
                'balance' => 0,
                'paid_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if loan should be marked as defaulted (90+ days overdue).
     */
    public function isDefaulted(): bool
    {
        return $this->days_overdue >= 90;
    }

    // ── Scopes ─────────────────────────────────────

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'overdue']);
    }

    public function scopeInstallmentLoans($query)
    {
        return $query->where('loan_type', 'installment');
    }

    public function scopeLumpSum($query)
    {
        return $query->where('loan_type', 'lump_sum');
    }
}
