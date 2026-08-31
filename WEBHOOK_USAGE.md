# Payment Webhook Automation - Usage Guide

## 🎯 Overview

The payment webhook system allows borrowers to pay through their bank/payment gateway, and the system automatically processes the payment without admin intervention.

---

## 📋 Production Workflow

### Step 1: Create Payment Request (In Your App)

When a borrower wants to make a payment, create a payment request with a reference code:

```php
use App\Services\PaymentService;
use App\Models\Loan;

// In your controller
public function createPaymentRequest(Request $request, PaymentService $paymentService)
{
    $loan = Loan::findOrFail($request->loan_id);
    $amount = $request->amount;

    // Create payment request with auto-generated reference code
    $payment = $paymentService->createPaymentRequest($loan, $amount, [
        'type' => 'partial',
        'method' => 'bank_transfer',
        'notes' => 'Payment request created by borrower',
    ]);

    // Show payment instructions to user
    return view('payment.instructions', [
        'payment' => $payment,
        'reference_code' => $payment->reference_code, // e.g., "PAY-LOAN-1-1"
        'amount' => $payment->amount,
        'loan' => $loan,
    ]);
}
```

### Step 2: Display Payment Instructions to User

Show the borrower how to pay:

```blade
{{-- resources/views/payment/instructions.blade.php --}}
<div class="payment-instructions">
    <h2>Payment Instructions</h2>
    
    <div class="alert alert-info">
        <p><strong>Amount to Pay:</strong> ${{ number_format($payment->amount, 2) }}</p>
        <p><strong>Payment Reference:</strong> <code>{{ $payment->reference_code }}</code></p>
    </div>

    <h3>How to Pay:</h3>
    <ol>
        <li>Open your bank app or payment gateway</li>
        <li>Transfer <strong>${{ number_format($payment->amount, 2) }}</strong> to our account</li>
        <li><strong>IMPORTANT:</strong> Enter this reference code in the payment notes/description:
            <div class="reference-code">{{ $payment->reference_code }}</div>
        </li>
        <li>Complete the payment</li>
        <li>Your payment will be automatically processed within minutes!</li>
    </ol>

    <div class="alert alert-warning">
        ⚠️ Make sure to include the reference code <code>{{ $payment->reference_code }}</code> 
        in your payment to ensure automatic processing.
    </div>
</div>
```

### Step 3: User Makes Payment

The borrower:
1. Opens their bank app (ABA, Wing, etc.) or payment gateway (Stripe, PayPal)
2. Transfers the amount
3. **Enters the reference code** `PAY-LOAN-1-1` in the payment notes/description
4. Completes the payment

### Step 4: Payment Gateway Sends Webhook (Automatic)

The payment gateway automatically sends a webhook to your server:

```
POST https://yourdomain.com/api/payment/webhook?tenant=1
Content-Type: application/json

{
  "transaction_id": "TXN-ABC123",
  "reference": "PAY-LOAN-1-1",
  "amount": 120.00,
  "currency": "USD",
  "status": "completed",
  "payment_date": "2026-05-26T10:30:00Z"
}
```

### Step 5: System Processes Automatically

Your system:
1. ✅ Receives webhook
2. ✅ Finds payment by reference code `PAY-LOAN-1-1`
3. ✅ Validates tenant ownership
4. ✅ Approves payment
5. ✅ Updates loan balance
6. ✅ Sends Telegram notification to borrower
7. ✅ Marks payment as processed

**No admin action required!** 🎉

---

## 🧪 Testing Workflow

For testing, you have two options:

### Option A: Use the Test Command (Quick)

```bash
# Create a test payment request
php artisan payment:create-test 1 120.00 "PAY-LOAN-1-1"

# Then send webhook via Postman
POST http://127.0.0.1:8000/api/payment/webhook?tenant=1
Body: {"transaction_id": "TXN-123", "reference": "PAY-LOAN-1-1", "amount": 120.00, ...}
```

### Option B: Use the Production Flow (Recommended)

```php
// In tinker or a test controller
use App\Services\PaymentService;
use App\Models\Loan;

$loan = Loan::find(1);
$paymentService = app(PaymentService::class);

// Create payment request (same as production)
$payment = $paymentService->createPaymentRequest($loan, 120.00);

echo "Reference Code: " . $payment->reference_code;
// Output: Reference Code: PAY-LOAN-1-2

// Then send webhook with this reference code
```

---

## 🔧 Integration with Payment Gateways

### Supported Gateways

1. **Simulated** (for testing)
2. **ABA Bank** (Cambodia)
3. **KHQR** (Cambodia)
4. **Stripe** (International)
5. **PayPal** (International)

### Gateway Configuration

Add webhook secrets to `.env`:

```env
# ABA Bank
ABA_WEBHOOK_SECRET=your_aba_secret
ABA_MERCHANT_ID=your_merchant_id
ABA_API_KEY=your_api_key

# KHQR
KHQR_WEBHOOK_SECRET=your_khqr_secret
KHQR_MERCHANT_ID=your_merchant_id

# Stripe
STRIPE_WEBHOOK_SECRET=whsec_your_stripe_secret
STRIPE_SECRET_KEY=sk_your_stripe_key

# PayPal
PAYPAL_WEBHOOK_SECRET=your_paypal_secret
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
PAYPAL_MODE=sandbox  # or 'live' for production
```

### Register Webhook URL with Gateway

For each gateway, register this webhook URL:

```
https://yourdomain.com/api/payment/webhook?tenant={tenant_id}
```

Example for tenant 1:
```
https://yourdomain.com/api/payment/webhook?tenant=1
```

---

## 📊 Monitoring

### Check Payment Status

```bash
# Check payment by reference code
php artisan tinker
>>> $payment = App\Models\Payment::where('reference_code', 'PAY-LOAN-1-1')->first();
>>> echo $payment->status; // 'pending' or 'approved'
```

### Check Webhook Transactions

```bash
# View recent webhook transactions
php artisan tinker
>>> App\Models\PaymentTransaction::latest()->take(5)->get(['id', 'status', 'source', 'created_at']);
```

### View Webhook Logs

```bash
# View webhook-specific logs
tail -f storage/logs/webhooks.log

# View general application logs
tail -f storage/logs/laravel.log
```

---

## 🚀 Queue Workers

For production, run queue workers to process webhooks asynchronously:

```bash
# Start payments queue worker
php artisan queue:work --queue=payments --tries=3

# Or use Supervisor for automatic restart
# See config example in design.md
```

---

## ❓ FAQ

### Q: Do I need to create payment requests manually every time?

**A:** No! In production, your application should automatically create payment requests when:
- A borrower clicks "Make Payment" button
- A scheduled payment is due
- An admin creates a payment request for a borrower

### Q: What if the borrower forgets to include the reference code?

**A:** The payment will not be automatically processed. You'll need to:
1. Check failed webhook transactions in the admin panel
2. Manually match the payment to the loan
3. Approve it manually

**Best practice:** Make the reference code very prominent in payment instructions!

### Q: Can I test without a real payment gateway?

**A:** Yes! Use the simulated gateway:
1. Create payment request
2. Send webhook via Postman with `gateway=simulated`
3. No signature validation required for testing

### Q: How do I know if a webhook failed?

**A:** Check the `payment_transactions` table:
- `status = 'failed'` → Check `error_message` column
- `status = 'processed'` → Success!
- `status = 'duplicate'` → Already processed (idempotency protection)

---

## 🎓 Example: Complete Integration

Here's a complete example of integrating webhook payments into your loan dashboard:

```php
// routes/web.php
Route::post('/loans/{loan}/payment-request', [PaymentController::class, 'createRequest'])
    ->name('loans.payment-request');

// app/Http/Controllers/Tenant/PaymentController.php
public function createRequest(Request $request, Loan $loan, PaymentService $paymentService)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01|max:' . $loan->balance,
    ]);

    // Create payment request with auto-generated reference
    $payment = $paymentService->createPaymentRequest($loan, $request->amount);

    // Return payment instructions
    return view('tenant.payments.instructions', [
        'payment' => $payment,
        'loan' => $loan,
    ]);
}
```

That's it! The webhook system handles everything else automatically. 🚀
