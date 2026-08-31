<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Models\Tenant;

class AdminGuard
{
    /**
     * Check if a Telegram user is a linked admin for this tenant.
     */
    public function isAdmin(Tenant $tenant, string $telegramUserId): bool
    {
        return User::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $telegramUserId)
            ->exists();
    }

    /**
     * Get the admin User model for a Telegram user.
     */
    public function getAdmin(Tenant $tenant, string $telegramUserId): ?User
    {
        return User::where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $telegramUserId)
            ->first();
    }

    /**
     * Attempt to link a Telegram account using a one-time code.
     * Returns the User if successful, null otherwise.
     */
    public function linkWithCode(string $code, string $telegramUserId): ?User
    {
        $user = User::where('telegram_link_code', strtoupper($code))
            ->where('telegram_link_expires_at', '>', now())
            ->whereNull('telegram_user_id')
            ->first();

        if (!$user) return null;

        $user->update([
            'telegram_user_id'         => $telegramUserId,
            'telegram_link_code'       => null,
            'telegram_link_expires_at' => null,
        ]);

        return $user;
    }

    /**
     * Generate a one-time link code for a user (called from web dashboard).
     */
    public static function generateLinkCode(User $user): string
    {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $user->update([
            'telegram_link_code'       => $code,
            'telegram_link_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }
}
