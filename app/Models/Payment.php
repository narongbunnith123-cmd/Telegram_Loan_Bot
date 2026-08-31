<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'installment_id',
        'approved_by',
        'amount',
        'penalty_paid',
        'reference_code',
        'transaction_id',
        'gateway_name',
        'type',
        'method',
        'status',
        'notes',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'penalty_paid' => 'decimal:2',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
    ];

    public function loan()        { return $this->belongsTo(Loan::class); }
    public function installment() { return $this->belongsTo(LoanInstallment::class, 'installment_id'); }
    public function approvedBy()  { return $this->belongsTo(User::class, 'approved_by'); }
    public function proofs()      { return $this->hasMany(PaymentProof::class); }
    public function transactions() { return $this->hasMany(PaymentTransaction::class); }

    /**
     * Check if this payment was processed via webhook
     */
    public function isWebhookPayment(): bool
    {
        return !empty($this->transaction_id) && !empty($this->gateway_name);
    }

    /**
     * Check if this payment has a reference code
     */
    public function hasReferenceCode(): bool
    {
        return !empty($this->reference_code);
    }

    /**
     * Accessor for gateway (alias for gateway_name)
     */
    public function getGatewayAttribute()
    {
        return $this->gateway_name;
    }

    /**
     * Mutator for gateway (sets gateway_name)
     */
    public function setGatewayAttribute($value)
    {
        $this->attributes['gateway_name'] = $value;
    }

    /**
     * Get the most recent transaction for this payment
     */
    public function transaction()
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }
}
