<?php

namespace App\Services\Payment\Gateways;

use App\Models\PaymentSession;
use App\Services\Payment\AbaSignatureService;
use App\Services\Payment\ParsedWebhook;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ABA PayWay Gateway Service.
 *
 * Real payment gateway adapter for ABA Bank's PayWay system.
 * Supports KHQR + ABA PAY checkout in Cambodia.
 *
 * API Flow:
 *   1. Build payload with merchant_id, tran_id, amount, items, hash
 *   2. POST to PayWay Purchase API
 *   3. Response contains checkout URL (redirect borrower there)
 *   4. After payment, ABA sends pushback webhook to our endpoint
 *   5. We verify signature and process payment
 *
 * Sandbox: https://checkout-sandbox.payway.com.kh
 * Production: https://checkout.payway.com.kh
 */
class AbaPaywayGatewayService implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $apiUrl;
    private string $webhookUrl;

    public function __construct(
        private AbaSignatureService $signatureService,
    ) {
        $this->merchantId = config('payment.gateways.aba.merchant_id', '');
        $this->apiUrl = rtrim(config('payment.gateways.aba.api_url', 'https://checkout-sandbox.payway.com.kh'), '/');
        $this->webhookUrl = config('payment.gateways.aba.webhook_url', '');
    }

    public function getName(): string
    {
        return 'aba';
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), ['USD', 'KHR']);
    }

    /**
     * Create a payment via ABA PayWay Purchase API.
     *
     * POST /api/payment-gateway/v1/payments/purchase
     *
     * Required params:
     * - merchant_id, tran_id, amount, items, req_time, hash
     * - payment_option: abapay_khqr, cards, abapay, etc.
     * - return_url: where to redirect after payment
     * - continue_success_url: redirect on success
     */
    public function createPayment(PaymentSession $session): GatewayResponse
    {
        if (empty($this->merchantId)) {
            return GatewayResponse::failed(
                'ABA gateway not configured. Set ABA_MERCHANT_ID and ABA_API_KEY in .env'
            );
        }

        try {
            $reqTime = now()->format('YmdHis');
            $tranId = $session->reference_code;
            $amount = number_format((float) $session->amount, 2, '.', '');
            $currency = strtoupper($session->currency);

            // Build items array for ABA (base64-encoded JSON)
            $items = $this->signatureService->encodeItems([
                [
                    'name' => "Loan Payment #{$session->loan_id}",
                    'quantity' => '1',
                    'price' => $amount,
                ],
            ]);

            // Build return URL (where borrower goes after payment)
            $returnUrl = url('/payments/sessions/' . $session->id);

            // Generate hash: only req_time + merchant_id + tran_id + amount
            $hash = $this->signatureService->buildPurchaseHash(
                $reqTime,
                $this->merchantId,
                $tranId,
                $amount
            );

            // Build the purchase parameters
            $params = [
                'req_time' => $reqTime,
                'merchant_id' => $this->merchantId,
                'tran_id' => $tranId,
                'amount' => $amount,
                'items' => $items,
                'hash' => $hash,
                'payment_option' => 'abapay_khqr',
                'return_url' => $returnUrl,
                'continue_success_url' => $returnUrl,
                'push_back_url' => $this->webhookUrl,
                'return_params' => "session_id={$session->id}",
            ];

            // Add currency if not USD (ABA defaults to USD)
            if ($currency !== 'USD') {
                $params['currency'] = $currency;
            }

            Log::info('ABA PayWay: Creating payment', [
                'session_id' => $session->id,
                'tran_id' => $tranId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            // POST to ABA Purchase API
            $http = Http::asForm()->timeout(30);

            // Disable SSL verification in local/sandbox (Windows CA bundle issue)
            if (app()->environment('local', 'testing')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post("{$this->apiUrl}/api/payment-gateway/v1/payments/purchase", $params);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('ABA PayWay: API request failed', [
                    'status' => $response->status(),
                    'body' => substr($errorBody, 0, 500),
                ]);
                return GatewayResponse::failed(
                    "ABA API error (HTTP {$response->status()}): " . substr($errorBody, 0, 200)
                );
            }

            $responseData = $response->json();

            // ABA returns status "00" for success
            $status = $responseData['status'] ?? null;

            if ($status === '00' || $status === 0 || $status === '0') {
                // Build checkout URL
                $checkoutUrl = $responseData['checkout_url']
                    ?? $responseData['payment_url']
                    ?? "{$this->apiUrl}/checkout/{$tranId}";

                // Extract QR data if available
                $qrData = $responseData['qr_data']
                    ?? $responseData['abapay_deeplink']
                    ?? null;

                Log::info('ABA PayWay: Payment created successfully', [
                    'session_id' => $session->id,
                    'checkout_url' => $checkoutUrl,
                    'has_qr' => !empty($qrData),
                ]);

                return GatewayResponse::success(
                    qrPayload: $qrData,
                    checkoutUrl: $checkoutUrl,
                    gatewayReference: $tranId,
                    metadata: [
                        'aba_response' => $responseData,
                        'req_time' => $reqTime,
                    ],
                );
            }

            // ABA returned an error
            $errorMsg = $responseData['description']
                ?? $responseData['message']
                ?? $responseData['error']
                ?? 'Unknown ABA error';

            Log::warning('ABA PayWay: Payment creation failed', [
                'session_id' => $session->id,
                'status' => $status,
                'error' => $errorMsg,
                'response' => $responseData,
            ]);

            return GatewayResponse::failed("ABA error: {$errorMsg}");

        } catch (\Exception $e) {
            Log::error('ABA PayWay: Exception during payment creation', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return GatewayResponse::failed("ABA connection error: {$e->getMessage()}");
        }
    }

    /**
     * Verify ABA PayWay webhook signature.
     *
     * ABA sends X-PayWay-Hmac-Sha512 header with HMAC of the raw body.
     */
    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        return $this->signatureService->verifyWebhookSignature($payload, $signature);
    }

    /**
     * Parse ABA PayWay webhook (pushback) payload.
     *
     * ABA callback sends:
     * - tran_id: our reference code (PAY-T1-L15-...)
     * - status: "00" = success
     * - apv: approval code
     * - amount: paid amount
     */
    public function parseWebhookPayload(array $payload): ParsedWebhook
    {
        return new ParsedWebhook(
            transactionId: $payload['tran_id'] ?? '',
            referenceCode: $payload['tran_id'] ?? '', // ABA uses tran_id as our reference
            amount: (float) ($payload['amount'] ?? 0),
            currency: $payload['currency'] ?? 'USD',
            paidAt: isset($payload['payment_date'])
            ? Carbon::parse($payload['payment_date'])
            : now(),
            receiptUrl: null,
            gateway: 'aba',
        );
    }

    /**
     * Query transaction status from ABA PayWay.
     *
     * POST /api/payment-gateway/v1/payments/check-transaction
     */
    public function queryTransaction(string $transactionId): ?TransactionStatus
    {
        if (empty($this->merchantId)) {
            return null;
        }

        try {
            $reqTime = now()->format('YmdHis');
            $hash = $this->signatureService->buildCheckTransactionHash(
                $reqTime,
                $this->merchantId,
                $transactionId
            );

            $http = Http::asForm()->timeout(15);

            if (app()->environment('local', 'testing')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post("{$this->apiUrl}/api/payment-gateway/v1/payments/check-transaction", [
                'merchant_id' => $this->merchantId,
                'tran_id' => $transactionId,
                'req_time' => $reqTime,
                'hash' => $hash,
            ]);

            if (!$response->successful()) {
                Log::warning('ABA check-transaction failed', [
                    'tran_id' => $transactionId,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            $status = $data['status'] ?? null;

            $mappedStatus = match ($status) {
                '00', 0 => 'completed',
                '01' => 'pending',
                '02' => 'failed',
                '03' => 'refunded',
                default => 'unknown',
            };

            return new TransactionStatus(
                transactionId: $transactionId,
                status: $mappedStatus,
                amount: (float) ($data['amount'] ?? 0),
                currency: $data['currency'] ?? 'USD',
                paidAt: $data['payment_date'] ?? null,
                metadata: $data,
            );

        } catch (\Exception $e) {
            Log::error('ABA check-transaction exception', [
                'tran_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Refund — ABA PayWay refund is typically handled manually.
     */
    public function refund(string $transactionId, float $amount): RefundResult
    {
        return RefundResult::failed('ABA PayWay refunds must be processed manually through the merchant portal.');
    }
}
