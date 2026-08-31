<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

/**
 * ABA PayWay Signature Service.
 *
 * Handles all HMAC-SHA512 cryptographic operations for ABA PayWay API:
 * - Generate request hash (for purchase, check-transaction)
 * - Verify webhook signatures (pushback callbacks)
 *
 * ABA uses the same api_key for both request signing and webhook verification.
 * Hash algorithm: HMAC-SHA512 → base64 encoded
 */
class AbaSignatureService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('payment.gateways.aba.api_key', '');
    }

    /**
     * Generate HMAC-SHA512 hash for ABA API request.
     *
     * ABA requires: concatenate param values in a specific order,
     * hash with HMAC-SHA512 using api_key, base64 encode.
     *
     * @param string $dataString The concatenated data string to hash
     * @return string Base64-encoded HMAC-SHA512 hash
     */
    public function generateHash(string $dataString): string
    {
        return base64_encode(
            hash_hmac('sha512', $dataString, $this->apiKey, true)
        );
    }

    /**
     * Build the hash for the Purchase API request.
     *
     * ABA PayWay purchase hash = HMAC-SHA512 of:
     * req_time + merchant_id + tran_id + amount
     *
     * IMPORTANT: Only these 4 fields are concatenated. No separators.
     *
     * @param string $reqTime    Request timestamp (YmdHis)
     * @param string $merchantId Merchant ID
     * @param string $tranId     Transaction ID
     * @param string $amount     Amount (e.g. "50.00")
     * @return string Base64-encoded hash
     */
    public function buildPurchaseHash(string $reqTime, string $merchantId, string $tranId, string $amount): string
    {
        $dataString = $reqTime . $merchantId . $tranId . $amount;
        return $this->generateHash($dataString);
    }

    /**
     * Build the hash for the Check Transaction API request.
     *
     * Hash = HMAC-SHA512 of: req_time + merchant_id + tran_id
     *
     * @param string $reqTime  Request timestamp (YmdHis)
     * @param string $merchantId
     * @param string $tranId
     * @return string Base64-encoded hash
     */
    public function buildCheckTransactionHash(string $reqTime, string $merchantId, string $tranId): string
    {
        $dataString = $reqTime . $merchantId . $tranId;
        return $this->generateHash($dataString);
    }

    /**
     * Verify ABA PayWay webhook (pushback) signature.
     *
     * ABA sends the signature in the X-PayWay-Hmac-Sha512 header.
     * We HMAC-SHA512 the raw request body with our api_key and compare.
     *
     * @param string $rawBody Raw HTTP request body (before JSON parsing)
     * @param string $headerSignature Value of X-PayWay-Hmac-Sha512 header
     * @return bool
     */
    public function verifyWebhookSignature(string $rawBody, string $headerSignature): bool
    {
        if (empty($headerSignature) || empty($this->apiKey)) {
            Log::warning('ABA webhook verification failed: missing signature or api_key');
            return false;
        }

        $computed = base64_encode(
            hash_hmac('sha512', $rawBody, $this->apiKey, true)
        );

        $isValid = hash_equals($computed, $headerSignature);

        if (!$isValid) {
            Log::warning('ABA webhook signature mismatch', [
                'expected_length' => strlen($computed),
                'received_length' => strlen($headerSignature),
            ]);
        }

        return $isValid;
    }

    /**
     * Encode items array for ABA API.
     *
     * ABA expects items as a base64-encoded JSON string.
     *
     * @param array $items Array of item objects with name, quantity, price
     * @return string Base64-encoded JSON
     */
    public function encodeItems(array $items): string
    {
        return base64_encode(json_encode($items));
    }
}
