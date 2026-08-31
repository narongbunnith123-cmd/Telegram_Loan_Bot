<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'borrower_id',
        'rule_id',
        'template_id',
        'installment_id',
        'type',
        'target_type',
        'telegram_chat_id',
        'message_snapshot',
        'rendered_message',
        'scheduled_at',
        'sent_at',
        'status',
        'error_message',
        'idempotency_key',
        'is_manual',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    // ── Relationships ─────────────────────────────

    public function loan()        { return $this->belongsTo(Loan::class); }
    public function borrower()    { return $this->belongsTo(Borrower::class); }
    public function rule()        { return $this->belongsTo(ReminderRule::class, 'rule_id'); }
    public function template()    { return $this->belongsTo(ReminderTemplate::class, 'template_id'); }
    public function installment() { return $this->belongsTo(LoanInstallment::class, 'installment_id'); }
}
