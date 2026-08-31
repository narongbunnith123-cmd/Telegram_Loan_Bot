<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class BorrowerPaymentMethod extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'borrower_id',
        'type',
        'bank_name',
        'account_number',
        'account_holder',
        'label',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    // ── Helpers ─────────────────────────────────────

    /**
     * Get a display label for this payment method.
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->label)
            return $this->label;

        return match ($this->type) {
            'bank' => $this->bank_name ? "{$this->bank_name} ({$this->account_number})" : 'Bank Transfer',
            'cash' => 'Cash',
            'wallet' => 'Digital Wallet',
            default => ucfirst($this->type),
        };
    }
}
