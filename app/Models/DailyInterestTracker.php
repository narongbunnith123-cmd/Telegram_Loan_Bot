<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class DailyInterestTracker extends Model
{
    use HasTenant;

    protected $table = 'daily_interest_tracker';

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'borrower_id',
        'date',
        'interest_amount',
        'cumulative_unpaid',
        'is_paid',
        'paid_at',
        'payment_id',
        'reminder_1_sent',
        'reminder_1_sent_at',
        'reminder_2_sent',
        'reminder_2_sent_at',
        'consecutive_unpaid_days',
        'stage',
    ];

    protected $casts = [
        'date'                  => 'date',
        'paid_at'               => 'datetime',
        'reminder_1_sent_at'    => 'datetime',
        'reminder_2_sent_at'    => 'datetime',
        'is_paid'               => 'boolean',
        'reminder_1_sent'       => 'boolean',
        'reminder_2_sent'       => 'boolean',
        'interest_amount'       => 'decimal:2',
        'cumulative_unpaid'     => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────

    public function loan()     { return $this->belongsTo(Loan::class); }
    public function borrower() { return $this->belongsTo(Borrower::class); }
    public function payment()  { return $this->belongsTo(Payment::class); }

    // ── Scopes ───────────────────────────────────

    public function scopeForToday($query)
    {
        return $query->where('date', today());
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeNeedsReminder1($query)
    {
        return $query->forToday()
            ->unpaid()
            ->where('reminder_1_sent', false);
    }

    public function scopeNeedsReminder2($query)
    {
        return $query->forToday()
            ->unpaid()
            ->where('reminder_1_sent', true)
            ->where('reminder_2_sent', false);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Mark this day's interest as paid.
     */
    public function markPaid(int $paymentId): void
    {
        $this->update([
            'is_paid'    => true,
            'paid_at'    => now(),
            'payment_id' => $paymentId,
        ]);
    }

    /**
     * Determine escalation stage from consecutive unpaid days.
     */
    public static function calculateStage(int $consecutiveDays): string
    {
        if ($consecutiveDays >= 7) return 'escalation';
        if ($consecutiveDays >= 4) return 'warning';
        return 'normal';
    }
}
