<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id', 'name', 'reminder_type', 'days_offset',
        'frequency_type', 'send_to_dm', 'send_to_group', 'send_to_admin',
        'template_id', 'cooldown_hours', 'send_time', 'enabled',
    ];

    protected $casts = [
        'send_to_dm'     => 'boolean',
        'send_to_group'  => 'boolean',
        'send_to_admin'  => 'boolean',
        'enabled'        => 'boolean',
        'cooldown_hours' => 'integer',
        'days_offset'    => 'integer',
    ];

    // ── Relationships ──────────────────────────────

    public function tenant()   { return $this->belongsTo(Tenant::class); }
    public function template() { return $this->belongsTo(ReminderTemplate::class, 'template_id'); }

    // ── Scopes ─────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    // ── Methods ────────────────────────────────────

    /**
     * Determine if this rule should fire for the given days difference.
     *
     * @param int $daysDiff  Positive = overdue, 0 = due today, negative = before due
     */
    public function shouldFire(int $daysDiff): bool
    {
        if (!$this->enabled) return false;

        return match ($this->reminder_type) {
            'before_due' => $daysDiff < 0 && $daysDiff >= $this->days_offset,
            'due_today'  => $daysDiff === 0,
            'overdue'    => $daysDiff > 0 && $daysDiff >= $this->days_offset,
            'escalation' => $daysDiff >= $this->days_offset,
            default      => false,
        };
    }

    /**
     * Check if enough time has passed since the last reminder for this rule + loan.
     */
    public function isOnCooldown(int $loanId): bool
    {
        $lastSent = Reminder::where('loan_id', $loanId)
            ->where('rule_id', $this->id)
            ->where('status', 'sent')
            ->latest('sent_at')
            ->value('sent_at');

        if (!$lastSent) return false;

        return now()->diffInHours($lastSent) < $this->cooldown_hours;
    }

    /**
     * Check frequency: should we send today based on frequency_type?
     */
    public function matchesFrequency(int $daysDiff): bool
    {
        $absDays = abs($daysDiff);

        return match ($this->frequency_type) {
            'once'        => true, // handled by cooldown
            'daily'       => true,
            'every_2_days' => $absDays % 2 === 0,
            'weekly'      => $absDays % 7 === 0,
            default       => false,
        };
    }
}
