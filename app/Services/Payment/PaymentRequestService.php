<?php

namespace App\Services\Payment;

use App\Models\Loan;
use App\Models\PaymentSession;
use App\Services\Payment\Gateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Request Service — the orchestrator for payment session lifecycle.
 *
 * Responsibilities:
 * 1. Create payment session with unique reference
 * 2. Call gateway to get QR/checkout URL
 * 3. Track session status throughout lifecycle
 * 4. Coordinate with PaymentService for financial processing
 *
 * This is the ENTRY POINT for creating any payment request.
 * Telegram /requestpay command → calls this service.
 * Web dashboard payment request → calls this service.
 */
class PaymentRequestService
{
    /**
     * Default session expiry in minutes.
     * Can be overridden via config or tenant settings.
     */
    private const DEFAULT_EXPIRY_MINUTES = 60;

    public function __construct(
        private PaymentReferenceGenerator $referenceGenerator,
        private GatewayFactory $gatewayFactory,
    ) {
    }

    /**
     * Create a payment request — the main entry point.
     *
     * Flow:
     * 1. Generate unique reference code (new session format)
     * 2. Resolve the gateway adapter
     * 3. Create PaymentSession record
     * 4. Call gateway to get QR/checkout URL
     * 5. Update session with gateway response
     * 6. Return the complete session
     *
     * @param Loan   $loan         The loan to create payment for
     * @param float  $amount       Amount to request
     * @param string $gatewayName  Gateway to use ('mock', 'khqr', etc.)
     * @param array  $options      Optional: currency, expiry_minutes, metadata
     * @return PaymentSession
     */
    public function createPaymentRequest(
        Loan $loan,
        float $amount,
        string $gatewayName = 'mock',
        array $options = [],
    ): PaymentSession {
        return DB::transaction(function () use ($loan, $amount, $gatewayName, $options) {
            // 1. Resolve the gateway
            $gateway = $this->gatewayFactory->resolve($gatewayName);

            // 2. Determine currency (group default → admin override → gateway final truth)
            $currency = $options['currency']
                ?? $this->getGroupCurrency($loan)
                ?? 'USD';

            // Validate currency support
            if (!$gateway->supportsCurrency($currency)) {
                throw new \InvalidArgumentException(
                    "Gateway '{$gatewayName}' does not support currency '{$currency}'."
                );
            }

            // 3. Generate unique reference code
            $referenceCode = $this->referenceGenerator->generateForSession($loan);

            // 4. Calculate expiry
            $expiryMinutes = $options['expiry_minutes']
                ?? $this->getTenantExpiryMinutes($loan->tenant_id)
                ?? self::DEFAULT_EXPIRY_MINUTES;

            // 5. Create the payment session
            $session = PaymentSession::create([
                'tenant_id' => $loan->tenant_id,
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
                'reference_code' => $referenceCode,
                'amount' => $amount,
                'currency' => $currency,
                'gateway_name' => $gatewayName,
                'status' => 'pending',
                'expires_at' => now()->addMinutes($expiryMinutes),
                'metadata' => $options['metadata'] ?? null,
            ]);

            // 6. Call gateway to get QR/checkout URL
            try {
                $gatewayResponse = $gateway->createPayment($session);

                if ($gatewayResponse->success) {
                    $session->update([
                        'qr_payload' => $gatewayResponse->qrPayload,
                        'checkout_url' => $gatewayResponse->checkoutUrl,
                        'metadata' => array_merge($session->metadata ?? [], [
                            'gateway_reference' => $gatewayResponse->gatewayReference,
                            'gateway_metadata' => $gatewayResponse->metadata,
                        ]),
                    ]);
                } else {
                    // Gateway failed — mark session as failed
                    $session->markFailed($gatewayResponse->errorMessage);
                    Log::warning("Gateway payment creation failed", [
                        'session_id' => $session->id,
                        'gateway' => $gatewayName,
                        'error' => $gatewayResponse->errorMessage,
                    ]);
                }
            } catch (\Exception $e) {
                $session->markFailed($e->getMessage());
                Log::error("Gateway payment creation exception", [
                    'session_id' => $session->id,
                    'gateway' => $gatewayName,
                    'error' => $e->getMessage(),
                ]);
            }

            // Log activity
            activity()
                ->performedOn($session)
                ->withProperties([
                    'loan_id' => $loan->id,
                    'amount' => $amount,
                    'gateway' => $gatewayName,
                    'reference_code' => $referenceCode,
                    'expires_at' => $session->expires_at?->toIso8601String(),
                ])
                ->log('Payment session created');

            return $session->fresh();
        });
    }

    /**
     * Cancel a pending payment session.
     *
     * @param PaymentSession $session
     * @return void
     * @throws \InvalidArgumentException If session is not pending
     */
    public function cancelSession(PaymentSession $session): void
    {
        if ($session->status !== 'pending') {
            throw new \InvalidArgumentException(
                "Cannot cancel session #{$session->id} — status is '{$session->status}'."
            );
        }

        $session->markCancelled();

        activity()
            ->performedOn($session)
            ->log('Payment session cancelled');
    }

    /**
     * Check the current status of a payment session.
     * If expired but still marked as pending, auto-expire it.
     *
     * @param PaymentSession $session
     * @return string  Current status
     */
    public function checkSessionStatus(PaymentSession $session): string
    {
        // Auto-expire if time has passed
        if ($session->status === 'pending' && $session->isExpired()) {
            $session->markExpired();
            return 'expired';
        }

        return $session->status;
    }

    /**
     * Find a session by reference code for webhook processing.
     * Returns the session only if it's still active (pending + not expired).
     *
     * @param string $referenceCode
     * @param int    $tenantId
     * @return PaymentSession|null
     */
    public function findActiveSession(string $referenceCode, int $tenantId): ?PaymentSession
    {
        $session = PaymentSession::where('reference_code', $referenceCode)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->first();

        if (!$session)
            return null;

        // Auto-expire if needed
        if ($session->isExpired()) {
            $session->markExpired();
            return null;
        }

        return $session;
    }

    /**
     * Check if there's already an active session for this loan.
     * Prevents creating duplicate payment requests.
     *
     * @param Loan $loan
     * @return PaymentSession|null  The existing active session, or null
     */
    public function getActiveSessionForLoan(Loan $loan): ?PaymentSession
    {
        return PaymentSession::where('loan_id', $loan->id)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    // ── Private Helpers ────────────────────────────

    /**
     * Get the group's default currency from settings.
     */
    private function getGroupCurrency(Loan $loan): ?string
    {
        $settings = is_array($loan->group?->settings) ? $loan->group->settings : [];
        $symbol = $settings['currency'] ?? null;

        // Convert currency symbol to ISO code if needed
        return match ($symbol) {
            '$', 'USD' => 'USD',
            '៛', 'KHR' => 'KHR',
            default => $symbol,
        };
    }

    /**
     * Get tenant-specific session expiry setting.
     * Falls back to null (caller uses default).
     */
    private function getTenantExpiryMinutes(int $tenantId): ?int
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        return $tenant?->getSetting('payment_session_expiry_minutes');
    }
}
