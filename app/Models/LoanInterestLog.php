<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class LoanInterestLog extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'interest_applied',
        'balance_before',
        'balance_after',
        'days_overdue',
        'calculated_date',
    ];

    protected $casts = [
        'interest_applied' => 'decimal:2',
        'balance_before'   => 'decimal:2',
        'balance_after'    => 'decimal:2',
        'calculated_date'  => 'date',
    ];

    public function loan() { return $this->belongsTo(Loan::class); }
}
