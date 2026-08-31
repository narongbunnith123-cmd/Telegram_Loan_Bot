# Real World Example: Payment Webhook Integration

This guide shows **exactly** how to integrate the payment webhook system into your loan application with real code examples.

---

## Scenario: Borrower Makes a Payment

**Story**: John borrowed $1000. He wants to pay $200. Your app needs to:
1. Create a payment request with a unique reference code
2. Show John the reference code to use when paying
3. Receive webhook from payment gateway when John pays
4. Automatically approve the payment and update the loan

---

## Step 1: Create Payment Request in Your Controller

When a borrower wants to make a payment, your controller creates a payment request:

### Example: In `PaymentController.php`

```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Borrower initiates a payment
     */
    public function initiatePayment(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'gateway' => 'required|in:aba,khqr,stripe,paypal,simulated'
        ]);

        // Create payment request with auto-generated reference code
        $payment = $this->paymentService->createPaymentRequest(
            $loan,
            $validated['amount'],
            $validated['gateway']
        );

        // Return payment details to show borrower
        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'reference_code' => $payment->reference_code,  // e.g., "PAY-LOAN-42-1"
                'amount' => $payment->amount,
                'gateway' => $payment->gateway,
                'status' => $payment->status,  // "pending"
                'instructions' => $this->getPaymentInstructions($payment)
            ]
        ]);
    }

    /**
     * Get payment instructions for borrower
     */
    protected function getPaymentInstructions($payment)
    {
        $instructions = [
            'aba' => "Transfer ${payment->amount} to ABA account 123456789. Use reference: {$payment->reference_code}",
            'khqr' => "Scan KHQR code and pay ${payment->amount}. Use reference: {$payment->reference_code}",
            'stripe' => "Pay via Stripe using reference: {$payment->reference_code}",
            'paypal' => "Send ${payment->amount} to our PayPal. Use reference: {$payment->reference_code}",
            'simulated' => "Test payment. Reference: {$payment->reference_code}"
        ];

        return $instructions[$payment->gateway] ?? "Pay ${payment->amount} using reference: {$payment->reference_code}";
    }

    /**
     * Show payment status (borrower checks if payment was received)
     */
    public function checkPaymentStatus($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        return response()->json([
            'payment_id' => $payment->id,
            'reference_code' => $payment->reference_code,
            'status' => $payment->status,  // pending, approved, rejected
            'amount' => $payment->amount,
            'paid_at' => $payment->approved_at,
            'transaction_id' => $payment->transaction_id
        ]);
    }
}
```

---

## Step 2: Frontend Shows Payment Instructions

Your frontend (web or mobile app) displays the payment details to the borrower:

### Example: React/Vue Component

```javascript
// PaymentForm.jsx
import { useState } from 'react';

function PaymentForm({ loanId }) {
  const [payment, setPayment] = useState(null);
  const [loading, setLoading] = useState(false);

  const initiatePayment = async (amount, gateway) => {
    setLoading(true);
    
    const response = await fetch(`/api/tenant/loans/${loanId}/payments`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount, gateway })
    });
    
    const data = await response.json();
    setPayment(data.payment);
    setLoading(false);
  };

  const checkStatus = async () => {
    const response = await fetch(`/api/tenant/payments/${payment.id}/status`);
    const data = await response.json();
    setPayment(prev => ({ ...prev, status: data.status }));
  };

  return (
    <div>
      {!payment ? (
        <form onSubmit={(e) => {
          e.preventDefault();
          const formData = new FormData(e.target);
          initiatePayment(formData.get('amount'), formData.get('gateway'));
        }}>
          <input name="amount" type="number" placeholder="Amount" required />
          <select name="gateway" required>
            <option value="aba">ABA Bank</option>
            <option value="khqr">KHQR</option>
            <option value="stripe">Stripe</option>
            <option value="paypal">PayPal</option>
          </select>
          <button type="submit" disabled={loading}>Create Payment</button>
        </form>
      ) : (
        <div className="payment-instructions">
          <h3>Payment Created!</h3>
          <div className="reference-code">
            <strong>Reference Code:</strong>
            <code>{payment.reference_code}</code>
            <button onClick={() => navigator.clipboard.writeText(payment.reference_code)}>
              Copy
            </button>
          </div>
          <p>{payment.instructions}</p>
          <div className="status">
            Status: <span className={payment.status}>{payment.status}</span>
            <button onClick={checkStatus}>Refresh Status</button>
          </div>
          {payment.status === 'approved' && (
            <div className="success">✓ Payment received and approved!</div>
          )}
        </div>
      )}
    </div>
  );
}
```

---

## Step 3: Borrower Pays Using Reference Code

John sees:
```
Reference Code: PAY-LOAN-42-1
Amount: $200.00
Instructions: Transfer $200 to ABA account 123456789. Use reference: PAY-LOAN-42-1
```

John opens his banking app and transfers $200 with the reference code `PAY-LOAN-42-1`.

---

## Step 4: Payment Gateway Sends Webhook

When John's payment is processed, the payment gateway (ABA Bank) sends a webhook to your server:

```http
POST https://yourdomain.com/api/payment/webhook?tenant=1
Content-Type: application/json
X-ABA-Signature: sha256=abc123...

{
  "transaction_id": "ABA-TXN-789456123",
  "reference": "PAY-LOAN-42-1",
  "amount": 200.00,
  "currency": "USD",
  "status": "completed",
  "payment_date": "2026-05-25T14:30:00Z",
  "payer_account": "1234567890"
}
```

---

## Step 5: System Automatically Processes Payment

The webhook system automatically:

1. **Validates** the webhook signature
2. **Parses** the gateway-specific payload
3. **Matches** the payment using reference code `PAY-LOAN-42-1`
4. **Updates** the payment status to "approved"
5. **Updates** the loan balance (reduces remaining_principal by $200)
6. **Sends** Telegram notification to John
7. **Logs** the transaction

**All of this happens automatically in the background!**

---

## Step 6: Borrower Sees Confirmation

John refreshes the payment status page and sees:

```
✓ Payment received and approved!
Transaction ID: ABA-TXN-789456123
Paid at: 2026-05-25 14:30:00
```

John also receives a Telegram message:
```
✅ Payment Confirmed!

Loan ID: 42
Amount Paid: $200.00
Transaction: ABA-TXN-789456123
New Balance: $800.00

Thank you for your payment!
```

---

## Complete Flow Diagram

```
┌─────────────┐
│  Borrower   │
│   (John)    │
└──────┬──────┘
       │
       │ 1. "I want to pay $200"
       ▼
┌─────────────────────────────┐
│  Your Laravel App           │
│  PaymentController          │
│  ├─ createPaymentRequest()  │
│  └─ Returns: PAY-LOAN-42-1  │
└──────┬──────────────────────┘
       │
       │ 2. Shows reference code
       ▼
┌─────────────┐
│  Borrower   │
│  Opens Bank │
│  App        │
└──────┬──────┘
       │
       │ 3. Transfers $200 with reference "PAY-LOAN-42-1"
       ▼
┌─────────────────────┐
│  Payment Gateway    │
│  (ABA Bank)         │
└──────┬──────────────┘
       │
       │ 4. Sends webhook
       ▼
┌──────────────────────────────────┐
│  Your Laravel App                │
│  PaymentWebhookController        │
│  ├─ Validates signature          │
│  ├─ Queues ProcessPaymentWebhook │
│  └─ Returns 200 OK               │
└──────┬───────────────────────────┘
       │
       │ 5. Queue processes webhook
       ▼
┌──────────────────────────────────┐
│  ProcessPaymentWebhook Job       │
│  ├─ Parses webhook               │
│  ├─ Matches payment by reference │
│  ├─ Approves payment             │
│  ├─ Updates loan balance         │
│  └─ Sends Telegram notification  │
└──────┬───────────────────────────┘
       │
       │ 6. Notification sent
       ▼
┌─────────────┐
│  Borrower   │
│  Receives   │
│  Telegram   │
│  Message    │
└─────────────┘
```

---

## Testing with Real Data

### 1. Create a Test Loan

```bash
php artisan tinker
```

```php
$tenant = Tenant::first();
$borrower = $tenant->borrowers()->first();

$loan = Loan::create([
    'tenant_id' => $tenant->id,
    'borrower_id' => $borrower->id,
    'principal_amount' => 1000.00,
    'remaining_principal' => 1000.00,
    'interest_rate' => 5.0,
    'loan_date' => now(),
    'due_date' => now()->addMonths(6),
    'status' => 'active'
]);

echo "Loan ID: " . $loan->id;
```

### 2. Create Payment Request via API

```bash
curl -X POST http://127.0.0.1:8000/api/tenant/loans/1/payments \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 200.00,
    "gateway": "simulated"
  }'
```

Response:
```json
{
  "success": true,
  "payment": {
    "id": 15,
    "reference_code": "PAY-LOAN-1-1",
    "amount": 200.00,
    "gateway": "simulated",
    "status": "pending",
    "instructions": "Test payment. Reference: PAY-LOAN-1-1"
  }
}
```

### 3. Simulate Webhook from Gateway

```bash
curl -X POST "http://127.0.0.1:8000/api/payment/webhook?tenant=1" \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_id": "TEST-TXN-123456",
    "reference": "PAY-LOAN-1-1",
    "amount": 200.00,
    "currency": "USD",
    "status": "completed",
    "payment_date": "2026-05-25T14:30:00Z"
  }'
```

Response:
```json
{
  "success": true,
  "message": "Webhook received and queued for processing"
}
```

### 4. Check Payment Status

```bash
curl http://127.0.0.1:8000/api/tenant/payments/15/status
```

Response:
```json
{
  "payment_id": 15,
  "reference_code": "PAY-LOAN-1-1",
  "status": "approved",
  "amount": 200.00,
  "paid_at": "2026-05-25 14:30:00",
  "transaction_id": "TEST-TXN-123456"
}
```

### 5. Verify Loan Balance Updated

```bash
php artisan tinker
```

```php
$loan = Loan::find(1);
echo "Remaining Principal: $" . $loan->remaining_principal;
// Output: Remaining Principal: $800
```

---

## Integration Checklist

- [ ] Add `createPaymentRequest()` calls in your payment controller
- [ ] Update frontend to display reference codes to borrowers
- [ ] Configure payment gateway webhook URLs to point to `/api/payment/webhook?tenant={id}`
- [ ] Add gateway secrets to `.env` file
- [ ] Test with each gateway you plan to use
- [ ] Set up queue worker: `php artisan queue:work`
- [ ] Monitor webhook logs: `storage/logs/webhooks.log`
- [ ] Set up Telegram bot for payment notifications

---

## Common Integration Points

### 1. When Borrower Clicks "Make Payment" Button
```php
$payment = $paymentService->createPaymentRequest($loan, $amount, $gateway);
// Show $payment->reference_code to borrower
```

### 2. When Displaying Payment QR Code
```php
$qrCode = QrCode::generate($payment->reference_code);
// Show QR code with reference embedded
```

### 3. When Sending Payment Link via Email
```php
Mail::to($borrower->email)->send(new PaymentInstructionMail($payment));
// Email includes reference code and payment instructions
```

### 4. When Borrower Checks Payment History
```php
$payments = $loan->payments()->with('transaction')->latest()->get();
// Show list with status, reference codes, and transaction IDs
```

---

## Need Help?

- **Testing Guide**: See `TESTING_GUIDE.md` for step-by-step testing
- **Production Setup**: See `WEBHOOK_USAGE.md` for deployment guide
- **Logs**: Check `storage/logs/webhooks.log` for webhook activity
- **Queue Jobs**: Monitor with `php artisan queue:work --verbose`
