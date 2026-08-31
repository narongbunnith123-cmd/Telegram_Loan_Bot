<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'update_id',
        'type',
        'payload',
        'status',
        'error_message',
        'received_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'received_at' => 'datetime',
    ];
}
