<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class GroupParticipant extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'group_id',
        'telegram_user_id',
        'telegram_username',
        'first_name',
        'last_name',
        'is_bot',
        'last_seen_at',
    ];

    protected $casts = [
        'is_bot'       => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function group() { return $this->belongsTo(TelegramGroup::class, 'group_id'); }

    /**
     * Get a display name for this participant.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        if ($name) return $name;
        if ($this->telegram_username) return '@' . $this->telegram_username;
        return 'User #' . $this->telegram_user_id;
    }
}
