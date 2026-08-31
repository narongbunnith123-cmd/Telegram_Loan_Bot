<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    protected $fillable = [
        'payment_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'status',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function payment()    { return $this->belongsTo(Payment::class); }
    public function uploadedBy() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
