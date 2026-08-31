<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BorrowerService
{
    /**
     * Create a new borrower.
     * Single source of truth — used by Web dashboard, Telegram /adduser, Telegram /import,
     * and future conversational flows.
     *
     * @param int        $tenantId        Tenant ID
     * @param array      $data            Borrower data (name, telegram_username, etc.)
     * @param User|null  $createdBy       The admin creating this borrower
     * @param bool       $skipDuplicates  If true, skip duplicate detection
     * @return array{borrower: Borrower|null, duplicates: array}
     */
    public function createBorrower(
        int $tenantId,
        array $data,
        ?User $createdBy = null,
        bool $skipDuplicates = false,
    ): array {
        // Clean up username
        $username = isset($data['telegram_username'])
            ? ltrim($data['telegram_username'], '@')
            : null;

        // Duplicate detection
        if (!$skipDuplicates) {
            $duplicates = Borrower::findDuplicates(
                $tenantId,
                $data['telegram_user_id'] ?? null,
                $username,
                $data['phone_number'] ?? null,
            );

            if (!empty($duplicates)) {
                return ['borrower' => null, 'duplicates' => $duplicates];
            }
        }

        // Determine verification status
        $hasUserId = !empty($data['telegram_user_id']);
        $verificationStatus = $hasUserId ? 'linked' : 'pending';

        // Determine onboarding source
        $source = $data['onboarding_source'] ?? ($hasUserId ? 'auto_detected' : 'manual');

        $borrower = DB::transaction(function () use ($tenantId, $data, $username, $verificationStatus, $source, $createdBy) {
            $borrower = Borrower::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'telegram_username' => $username,
                'telegram_user_id' => $data['telegram_user_id'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'borrower_code' => Borrower::generateCode(),
                'verification_status' => $verificationStatus,
                'onboarding_source' => $source,
                'linked_at' => $verificationStatus === 'linked' ? now() : null,
                'created_by' => $createdBy?->id,
                'status' => 'active',
            ]);

            activity()
                ->performedOn($borrower)
                ->causedBy($createdBy)
                ->withProperties(['source' => $source])
                ->log('Borrower created');

            return $borrower;
        });

        return ['borrower' => $borrower, 'duplicates' => []];
    }

    /**
     * Link a borrower's Telegram account.
     *
     * @param Borrower    $borrower
     * @param string      $telegramUserId
     * @param string|null $username
     * @return void
     */
    public function linkTelegram(Borrower $borrower, string $telegramUserId, ?string $username = null): void
    {
        $borrower->update([
            'telegram_user_id' => $telegramUserId,
            'telegram_username' => $username ? ltrim($username, '@') : $borrower->telegram_username,
            'verification_status' => 'linked',
            'linked_at' => now(),
        ]);

        activity()
            ->performedOn($borrower)
            ->log('Telegram account linked');
    }

    /**
     * Unlink a borrower's Telegram account.
     *
     * @param Borrower $borrower
     * @return void
     */
    public function unlinkTelegram(Borrower $borrower): void
    {
        $borrower->update([
            'verification_status' => 'unlinked',
            'telegram_user_id' => null,
        ]);

        activity()
            ->performedOn($borrower)
            ->log('Telegram account unlinked');
    }

    /**
     * Blacklist a borrower.
     *
     * @param Borrower $borrower
     * @return void
     */
    public function blacklist(Borrower $borrower): void
    {
        $borrower->update(['status' => 'blacklisted']);

        activity()
            ->performedOn($borrower)
            ->log('Borrower blacklisted');
    }

    /**
     * Restore a blacklisted borrower.
     *
     * @param Borrower $borrower
     * @return void
     */
    public function unblacklist(Borrower $borrower): void
    {
        $borrower->update(['status' => 'active']);

        activity()
            ->performedOn($borrower)
            ->log('Borrower restored from blacklist');
    }

    /**
     * Find a borrower by telegram username or name match.
     *
     * @param int    $tenantId
     * @param string $identifier  Username (with or without @) or partial name
     * @return Borrower|null
     */
    public function findByIdentifier(int $tenantId, string $identifier): ?Borrower
    {
        $username = ltrim($identifier, '@');

        return Borrower::where('tenant_id', $tenantId)
            ->where(function ($q) use ($username) {
                $q->where('telegram_username', $username)
                    ->orWhere('name', 'like', "%{$username}%");
            })
            ->first();
    }
}
