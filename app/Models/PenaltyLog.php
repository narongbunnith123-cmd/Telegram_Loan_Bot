<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class PenaltyLog extends Model
{
    use HasTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'loan_id', 'installment_id',
        'penalty_date', 'penalty_amount', 'days_late',
        'balance_before', 'balance_after', 'created_at',
    ];

    protected $casts = [
        'penalty_date'   => 'date',
        'penalty_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'created_at'     => 'datetime',
    ];

    public function loan()        { return $this->belongsTo(Loan::class); }
    public function installment() { return $this->belongsTo(LoanInstallment::class, 'installment_id'); }
}
