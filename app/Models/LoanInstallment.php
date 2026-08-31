<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInstallment extends Model
{
    use HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'loan_id', 'installment_number',
        'due_date', 'base_amount', 'penalty_amount',
        'paid_amount', 'balance', 'late_days',
        'status', 'paid_at',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'paid_at'        => 'datetime',
        'base_amount'    => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'balance'        => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────

    public function loan()        { return $this->belongsTo(Loan::class); }
    public function payments()    { return $this->hasMany(Payment::class, 'installment_id'); }
    public function penaltyLogs() { return $this->hasMany(PenaltyLog::class, 'installment_id'); }

    // ── Scopes ─────────────────────────────────────

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['pending', 'partial', 'overdue']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '>=', today());
    }

    // ── Computed Attributes ────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['pending', 'partial'])
            && $this->due_date->isPast();
    }

    public function getTotalDueAttribute(): float
    {
        return (float) $this->base_amount + (float) $this->penalty_amount;
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->total_due - (float) $this->paid_amount);
    }

    public function getDaysLateAttribute(): int
    {
        if ($this->due_date->isFuture()) return 0;
        return (int) $this->due_date->diffInDays(now());
    }

    // ── Methods ────────────────────────────────────

    /**
     * Recalculate the balance field from base + penalty - paid.
     */
    public function recalculateBalance(): self
    {
        $this->balance = max(0, $this->total_due - (float) $this->paid_amount);
        return $this;
    }
}
