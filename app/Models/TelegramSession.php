<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class TelegramSession extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'telegram_user_id',
        'telegram_chat_id',
        'current_action',
        'current_step',
        'temp_data',
        'expires_at',
    ];

    protected $casts = [
        'temp_data' => 'array',
        'expires_at' => 'datetime',
    ];

    // ── Scopes ──────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeForUser($query, string $telegramUserId, string $chatId)
    {
        return $query->where('telegram_user_id', $telegramUserId)
            ->where('telegram_chat_id', $chatId);
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Check if this session has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the session has an active conversation.
     */
    public function hasActiveConversation(): bool
    {
        return $this->current_action !== null && !$this->isExpired();
    }

    /**
     * Get a value from temp_data.
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return data_get($this->temp_data, $key, $default);
    }

    /**
     * Set a value in temp_data and save.
     */
    public function setData(string $key, mixed $value): self
    {
        $data = $this->temp_data ?? [];
        data_set($data, $key, $value);
        $this->temp_data = $data;
        return $this;
    }

    /**
     * Merge multiple values into temp_data.
     */
    public function mergeData(array $values): self
    {
        $data = $this->temp_data ?? [];
        $this->temp_data = array_merge($data, $values);
        return $this;
    }

    /**
     * Advance to the next step.
     */
    public function advanceTo(string $step): self
    {
        $this->current_step = $step;
        $this->expires_at = now()->addMinutes(10); // Reset timeout
        return $this;
    }

    /**
     * Clear the session (end conversation).
     */
    public function clear(): self
    {
        $this->current_action = null;
        $this->current_step = null;
        $this->temp_data = null;
        $this->expires_at = null;
        return $this;
    }
}
