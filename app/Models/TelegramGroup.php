<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'telegram_group_id',
        'name',
        'status',
        'settings',
        'joined_at',
        'reminder_time_1',
        'reminder_time_2',
    ];

    protected $casts = [
        'settings'  => 'array',
        'joined_at' => 'datetime',
    ];

    public function tenant()    { return $this->belongsTo(Tenant::class); }
    public function loans()     { return $this->hasMany(Loan::class, 'group_id'); }
    public function participants() { return $this->hasMany(GroupParticipant::class, 'group_id'); }

    /**
     * Get borrowers who have loans in this group.
     */
    public function borrowers()
    {
        $borrowerIds = $this->loans()->pluck('borrower_id')->unique();
        return Borrower::whereIn('id', $borrowerIds);
    }
}
