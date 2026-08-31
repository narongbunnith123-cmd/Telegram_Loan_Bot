<?php

namespace App\Services\Telegram\Commands;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Jobs\Telegram\SendPaymentRequestJob;
use App\Services\Payment\PaymentRequestService;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;
use Illuminate\Support\Facades\Log;

/**
 * /requestpay — Admin command to create a payment request.
 *
 * Usage:
 *   /requestpay @borrower 120
 *   /requestpay @borrower 120 khqr
 *   /requestpay borrower_name 50.00
 *
 * Flow:
 *   1. Admin issues command in group chat
 *   2. Bot looks up borrower + active loan
 *   3. PaymentRequestService creates session with QR/checkout URL
 *   4. Bot sends payment details message to the group
 */
class RequestPayCommand
{
    public function handle(Tenant $tenant, array $message): void
    {
        $chatId = (string) data_get($message, 'chat.id');
        $fromId = (string) data_get($message, 'from.id');
        $text = data_get($message, 'text', '');
        $chatType = data_get($message, 'chat.type', 'private');

        $sender = app(TelegramSender::class);
        $guard = app(AdminGuard::class);

        // Admin-only command
        if (!$guard->isAdmin($tenant, $fromId)) {
            $sender->sendToGroup($tenant->id, $chatId, "⛔ Only admins can create payment requests.");
            return;
        }

        // Parse command arguments
        $args = $this->parseArguments($text);

        if (!$args) {
            $sender->sendToGroup(
                $tenant->id,
                $chatId,
                "❌ <b>Usage:</b>\n"
                . "<code>/requestpay @borrower amount</code>\n"
                . "<code>/requestpay @borrower amount gateway</code>\n\n"
                . "<b>Examples:</b>\n"
                . "<code>/requestpay @dara 120</code>\n"
                . "<code>/requestpay @dara 50.00 khqr</code>\n\n"
                . "Available gateways: <code>mock</code>, <code>khqr</code>"
            );
            return;
        }

        // Find borrower
        $borrower = $this->findBorrower($tenant->id, $args['identifier']);

        if (!$borrower) {
            $sender->sendToGroup(
                $tenant->id,
                $chatId,
                "❌ Borrower not found: <code>{$args['identifier']}</code>\n\n"
                . "Make sure the borrower is registered. Use /adduser to register first."
            );
            return;
        }

        // Find active loan for this borrower in this group
        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)
            ->first();

        if (!$group) {
            $sender->sendToGroup($tenant->id, $chatId, "❌ This group is not registered.");
            return;
        }

        $loan = Loan::where('tenant_id', $tenant->id)
            ->where('borrower_id', $borrower->id)
            ->where('group_id', $group->id)
            ->whereIn('status', ['active', 'overdue'])
            ->where('balance', '>', 0)
            ->latest()
            ->first();

        if (!$loan) {
            $sender->sendToGroup(
                $tenant->id,
                $chatId,
                "❌ No active loan found for <b>{$borrower->name}</b> in this group."
            );
            return;
        }

        // Validate amount
        $amount = $args['amount'];
        if ($amount <= 0) {
            $sender->sendToGroup($tenant->id, $chatId, "❌ Amount must be greater than 0.");
            return;
        }

        if ($amount > (float) $loan->balance) {
            $sender->sendToGroup(
                $tenant->id,
                $chatId,
                "⚠️ Amount (\${$amount}) exceeds loan balance (\${$loan->balance}).\n"
                . "Maximum payment: \$" . number_format($loan->balance, 2)
            );
            return;
        }

        // Check for existing active session
        $requestService = app(PaymentRequestService::class);
        $existingSession = $requestService->getActiveSessionForLoan($loan);

        if ($existingSession) {
            $remaining = $existingSession->remaining_time ?? 'unknown';
            $sender->sendToGroup(
                $tenant->id,
                $chatId,
                "⚠️ There's already an active payment request for this loan.\n\n"
                . "Reference: <code>{$existingSession->reference_code}</code>\n"
                . "Amount: \$" . number_format($existingSession->amount, 2) . "\n"
                . "Expires in: {$remaining}\n\n"
                . "Wait for it to expire or the borrower to pay before creating a new one."
            );
            return;
        }

        // Create payment session
        $gatewayName = $args['gateway'] ?? 'mock';

        try {
            $session = $requestService->createPaymentRequest(
                loan: $loan,
                amount: $amount,
                gatewayName: $gatewayName,
            );

            if ($session->status === 'failed') {
                $failReason = $session->metadata['failure_reason'] ?? 'Unknown error';
                $sender->sendToGroup(
                    $tenant->id,
                    $chatId,
                    "❌ Failed to create payment request: {$failReason}"
                );
                return;
            }

            // Dispatch async job to send the payment request message
            SendPaymentRequestJob::dispatch($session)->onQueue('telegram');

        } catch (\Exception $e) {
            Log::error("RequestPayCommand error", [
                'tenant_id' => $tenant->id,
                'loan_id' => $loan->id,
                'error' => $e->getMessage(),
            ]);

            $sender->sendToGroup(
                $tenant->id,
                $chatId,
                "❌ Failed to create payment request. Please try again."
            );
        }
    }

    /**
     * Parse the command arguments.
     *
     * @param string $text  e.g. "/requestpay @dara 120 khqr"
     * @return array{identifier: string, amount: float, gateway: string|null}|null
     */
    private function parseArguments(string $text): ?array
    {
        // Remove the command itself
        $text = preg_replace('/^\/requestpay(@\S+)?\s*/', '', $text);
        $text = trim($text);

        if (empty($text))
            return null;

        // Split by whitespace
        $parts = preg_split('/\s+/', $text);

        if (count($parts) < 2)
            return null;

        $identifier = $parts[0];
        $amount = (float) $parts[1];

        if ($amount <= 0)
            return null;

        $gateway = $parts[2] ?? null;

        return [
            'identifier' => $identifier,
            'amount' => $amount,
            'gateway' => $gateway,
        ];
    }

    /**
     * Find borrower by username or name.
     */
    private function findBorrower(int $tenantId, string $identifier): ?Borrower
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
