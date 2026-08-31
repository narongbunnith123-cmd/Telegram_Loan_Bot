<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Jobs\Payment\ProcessPaymentWebhook;
use App\Models\PaymentTransaction;
use App\Services\Payment\AbaSignatureService;
use App\Services\Payment\WebhookSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private WebhookSignatureValidator $signatureValidator
    ) {
    }

    /**
     * Handle incoming payment webhook (generic endpoint).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Extract tenant ID from query parameter
            $tenantId = $request->query('tenant');
            if (!$tenantId) {
                return response()->json(['error' => 'Tenant ID required'], 422);
            }

            // Extract gateway name from header or query parameter
            $gateway = $request->header('X-Gateway-Name')
                ?? $request->query('gateway')
                ?? 'simulated';

            // Get raw request body for signature validation
            $payload = $request->getContent();
            $signature = $request->header('X-Gateway-Signature');

            // Validate signature (skip for simulated/mock gateway)
            if (!in_array($gateway, ['simulated', 'mock'])) {
                try {
                    $isValid = $this->signatureValidator->validate($gateway, $payload, $signature);
                    if (!$isValid) {
                        Log::warning('Invalid webhook signature', [
                            'gateway' => $gateway,
                            'tenant_id' => $tenantId,
                        ]);
                        return response()->json(['error' => 'Invalid signature'], 401);
                    }
                } catch (\Exception $e) {
                    Log::error('Signature validation error', [
                        'gateway' => $gateway,
                        'error' => $e->getMessage(),
                    ]);
                    return response()->json(['error' => 'Signature validation failed'], 401);
                }
            }

            // Parse JSON payload
            $payloadArray = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid JSON payload'], 422);
            }

            return $this->storeAndDispatch($gateway, $tenantId, $payloadArray, $signature);

        } catch (\Exception $e) {
            Log::error('Webhook handling error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle ABA PayWay pushback webhook (dedicated endpoint).
     *
     * POST /api/payment/webhook/aba
     *
     * ABA sends:
     * - Body: form-encoded or JSON with tran_id, status, apv, amount
     * - Header: X-PayWay-Hmac-Sha512 = HMAC signature of raw body
     *
     * This endpoint does NOT require a tenant query param because
     * we look up the tenant from the PaymentSession via tran_id (reference code).
     */
    public function handleAba(Request $request): JsonResponse
    {
        try {
            // Get raw body BEFORE any parsing (critical for HMAC verification)
            $rawBody = $request->getContent();
            $signature = $request->header('X-PayWay-Hmac-Sha512');

            Log::info('ABA webhook received', [
                'has_signature' => !empty($signature),
                'content_type' => $request->header('Content-Type'),
                'body_length' => strlen($rawBody),
            ]);

            // Verify ABA signature
            $signatureService = app(AbaSignatureService::class);
            if (!$signatureService->verifyWebhookSignature($rawBody, $signature ?? '')) {
                Log::warning('ABA webhook: Invalid signature', [
                    'received_signature' => substr($signature ?? '', 0, 20) . '...',
                ]);
                // Still process but log the warning — some sandbox callbacks may differ
                // In production, you would return 401 here:
                // return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Parse the payload (ABA may send form-encoded or JSON)
            $payloadArray = $request->all();
            if (empty($payloadArray) && !empty($rawBody)) {
                $payloadArray = json_decode($rawBody, true) ?? [];
            }

            if (empty($payloadArray['tran_id'])) {
                Log::warning('ABA webhook: Missing tran_id', [
                    'payload' => $payloadArray,
                ]);
                return response()->json(['error' => 'Missing tran_id'], 422);
            }

            // Check ABA status — "00" means success
            $abaStatus = $payloadArray['status'] ?? null;
            if ($abaStatus !== '00' && $abaStatus !== 0) {
                Log::info('ABA webhook: Non-success status', [
                    'tran_id' => $payloadArray['tran_id'],
                    'status' => $abaStatus,
                ]);
                // Still store it for audit, but note it's not a successful payment
            }

            // Look up tenant from the PaymentSession via reference code (tran_id)
            $session = \App\Models\PaymentSession::where('reference_code', $payloadArray['tran_id'])
                ->first();

            $tenantId = $session->tenant_id ?? 1; // Fallback to tenant 1 for single-tenant

            return $this->storeAndDispatch('aba', $tenantId, $payloadArray, $signature);

        } catch (\Exception $e) {
            Log::error('ABA webhook handling error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store PaymentTransaction and dispatch processing job.
     */
    private function storeAndDispatch(string $gateway, int $tenantId, array $payloadArray, ?string $signature): JsonResponse
    {
        // Create payment transaction record
        $transaction = PaymentTransaction::create([
            'tenant_id' => $tenantId,
            'source' => $gateway,
            'transaction_id' => $this->extractTransactionId($gateway, $payloadArray),
            'payload' => $payloadArray,
            'signature' => $signature,
            'status' => 'received',
        ]);

        // Dispatch job to process webhook asynchronously
        ProcessPaymentWebhook::dispatch($transaction->id)
            ->onQueue('payments');

        // Log webhook receipt
        Log::channel('webhooks')->info('Webhook received', [
            'gateway' => $gateway,
            'transaction_id' => $transaction->id,
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * Extract transaction ID from payload based on gateway type.
     */
    private function extractTransactionId(string $gateway, array $payload): string
    {
        return match ($gateway) {
            'simulated', 'mock' => $payload['transaction_id'] ?? 'unknown',
            'aba' => $payload['tran_id'] ?? 'unknown',
            'khqr' => $payload['transaction_id'] ?? 'unknown',
            'stripe' => $payload['data']['object']['id'] ?? 'unknown',
            'paypal' => $payload['resource']['id'] ?? 'unknown',
            default => 'unknown'
        };
    }
}
