<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Borrower extends Model
{
    use HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'telegram_user_id',
        'telegram_username',
        'borrower_code',
        'name',
        'phone_number',
        'address',
        'status',
        'verification_status',
        'onboarding_source',
        'linked_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    /* ── Relationships ────────────────────────── */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function paymentMethods()
    {
        return $this->hasMany(BorrowerPaymentMethod::class);
    }

    /**
     * Get all groups this borrower has loans in.
     */
    public function groups()
    {
        $groupIds = $this->loans()->pluck('group_id')->unique();
        return TelegramGroup::whereIn('id', $groupIds)->get();
    }

    /* ── Code Generation ──────────────────────── */

    /**
     * Generate a unique borrower code like BRW-A3F72B
     */
    public static function generateCode(): string
    {
        do {
            $code = 'BRW-' . strtoupper(Str::random(6));
        } while (self::where('borrower_code', $code)->exists());

        return $code;
    }

    /* ── Deep Link ────────────────────────────── */

    /**
     * Get the Telegram deep link for self-registration.
     * e.g. https://t.me/BunnithContactBot?start=BRW-A3F72B
     */
    public function getDeepLinkAttribute(): ?string
    {
        if (!$this->borrower_code)
            return null;

        $botUsername = BotToken::first()?->bot_username;
        if (!$botUsername)
            return null;

        return "https://t.me/{$botUsername}?start={$this->borrower_code}";
    }

    /* ── Status Helpers ───────────────────────── */

    public function isLinked(): bool
    {
        return $this->verification_status === 'linked';
    }
    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }
    public function isUnlinked(): bool
    {
        return $this->verification_status === 'unlinked';
    }
    public function isBlocked(): bool
    {
        return $this->status === 'blacklisted';
    }

    /**
     * Get the verification status badge info.
     */
    public function getVerificationBadgeAttribute(): array
    {
        return match ($this->verification_status) {
            'linked' => ['label' => 'Linked', 'color' => 'badge-active', 'icon' => '🟢'],
            'unverified' => ['label' => 'Unverified', 'color' => 'badge-pending', 'icon' => '🟠'],
            'blocked' => ['label' => 'Blocked', 'color' => 'badge-overdue', 'icon' => '🔴'],
            'inactive' => ['label' => 'Inactive', 'color' => 'badge-overdue', 'icon' => '⚪'],
            default => ['label' => 'Pending', 'color' => 'badge-pending', 'icon' => '🟡'],
        };
    }

    /* ── Duplicate Detection ──────────────────── */

    /**
     * Find potential duplicates before creating a new borrower.
     * Returns a collection of matches with reason.
     */
    public static function findDuplicates(int $tenantId, ?string $telegramUserId, ?string $username, ?string $phone): array
    {
        $duplicates = [];

        if ($telegramUserId) {
            $match = self::where('tenant_id', $tenantId)
                ->where('telegram_user_id', $telegramUserId)->first();
            if ($match)
                $duplicates[] = ['borrower' => $match, 'reason' => 'Same Telegram ID'];
        }

        if ($username) {
            $match = self::where('tenant_id', $tenantId)
                ->where('telegram_username', $username)
                ->when($telegramUserId, fn($q) => $q->where('telegram_user_id', '!=', $telegramUserId))
                ->first();
            if ($match)
                $duplicates[] = ['borrower' => $match, 'reason' => 'Same Telegram username'];
        }

        if ($phone) {
            $match = self::where('tenant_id', $tenantId)
                ->where('phone_number', $phone)->first();
            if ($match)
                $duplicates[] = ['borrower' => $match, 'reason' => 'Same phone number'];
        }

        return $duplicates;
    }
}
