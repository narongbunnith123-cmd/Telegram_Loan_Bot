<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentSession extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'borrower_id',
        'payment_id',
        'reference_code',
        'amount',
        'currency',
        'gateway_name',
        'status',
        'qr_payload',
        'checkout_url',
        'transaction_id',
        'webhook_payload',
        'metadata',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'webhook_payload' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get related payment transactions (matched by reference_code).
     */
    public function transactions()
    {
        return PaymentTransaction::where('tenant_id', $this->tenant_id)
            ->whereJsonContains('payload->reference', $this->reference_code);
    }

    // ── Scopes ─────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeForBorrower($query, int $borrowerId)
    {
        return $query->where('borrower_id', $borrowerId);
    }

    // ── Status Helpers ─────────────────────────────

    /**
     * Check if this session has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if this session is still active (pending + not expired).
     */
    public function isActive(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Check if this session has been paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Get the remaining time in minutes until expiry.
     */
    public function getRemainingMinutesAttribute(): ?int
    {
        if (!$this->expires_at)
            return null;
        if ($this->expires_at->isPast())
            return 0;
        return (int) now()->diffInMinutes($this->expires_at);
    }

    /**
     * Get a human-readable remaining time string.
     */
    public function getRemainingTimeAttribute(): ?string
    {
        $minutes = $this->remaining_minutes;
        if ($minutes === null)
            return null;
        if ($minutes <= 0)
            return 'Expired';
        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }
        return "{$minutes}m";
    }

    // ── State Transition Methods ───────────────────

    /**
     * Mark this session as paid.
     */
    public function markPaid(int $paymentId, string $transactionId, ?array $webhookPayload = null): void
    {
        $this->update([
            'status' => 'paid',
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
            'webhook_payload' => $webhookPayload,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark this session as expired.
     */
    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Mark this session as failed with an error reason.
     */
    public function markFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    /**
     * Cancel this session.
     */
    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // ── Static Helpers ─────────────────────────────

    /**
     * Find an active (pending, not expired) session by reference code.
     */
    public static function findActiveByReference(string $referenceCode): ?self
    {
        return static::where('reference_code', $referenceCode)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Find any session by reference code (regardless of status).
     */
    public static function findByReference(string $referenceCode): ?self
    {
        return static::where('reference_code', $referenceCode)->first();
    }
}
