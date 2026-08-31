<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'source',
        'transaction_id',
        'payload',
        'signature',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ─────────────────────────────────────

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeDuplicate($query)
    {
        return $query->where('status', 'duplicate');
    }

    // ── Helper Methods ─────────────────────────────

    /**
     * Mark transaction as successfully processed.
     */
    public function markProcessed(Payment $payment): void
    {
        $this->update([
            'status' => 'processed',
            'payment_id' => $payment->id,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark transaction as failed with error message.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark transaction as duplicate (idempotency check).
     */
    public function markDuplicate(): void
    {
        $this->update([
            'status' => 'duplicate',
        ]);
    }

    /**
     * Check if transaction is in a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['processed', 'failed', 'duplicate']);
    }

    /**
     * Check if transaction can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed';
    }
}
