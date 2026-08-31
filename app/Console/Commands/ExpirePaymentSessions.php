<?php

namespace App\Console\Commands;

use App\Models\PaymentSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Expire payment sessions that have passed their expiry time.
 *
 * Runs every minute via scheduler.
 * Finds all pending sessions where expires_at < now() and marks them expired.
 */
class ExpirePaymentSessions extends Command
{
    protected $signature = 'payment:expire-sessions';

    protected $description = 'Expire pending payment sessions that have passed their expiry time';

    public function handle(): int
    {
        $expiredSessions = PaymentSession::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredSessions->isEmpty()) {
            $this->info('No expired sessions found.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expiredSessions as $session) {
            $session->markExpired();
            $count++;

            Log::info('Payment session expired', [
                'session_id' => $session->id,
                'reference' => $session->reference_code,
                'loan_id' => $session->loan_id,
                'amount' => $session->amount,
                'expired_at' => $session->expires_at,
            ]);
        }

        $this->info("Expired {$count} payment session(s).");

        return self::SUCCESS;
    }
}
