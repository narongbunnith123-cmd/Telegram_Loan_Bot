# Quick Start Guide - Payment Webhook System

This guide will get you testing the payment webhook system in **5 minutes**.

---

## Prerequisites

✅ Laravel application running  
✅ Database migrated  
✅ Queue worker running: `php artisan queue:work`  
✅ At least one loan in the database

---

## Method 1: Using the Web Demo (Easiest)

### Step 1: Open the Demo Page

Open your browser and go to:
```
http://127.0.0.1:8000/payment-demo.html
```

**Note:** The demo uses test routes (no authentication required). See `AUTHENTICATION_GUIDE.md` for production setup.

### Step 2: Create a Payment Request

1. Enter a **Loan ID** (e.g., `1`)
2. Enter an **Amount** (e.g., `200.00`)
3. Select **Gateway**: Choose "Simulated (Testing)"
4. Click **"Create Payment Request"**

You'll see:
- ✅ A unique reference code (e.g., `PAY-LOAN-1-1`)
- 📋 Payment instructions
- 🔄 A button to check status

### Step 3: Copy the Reference Code

Click the **"📋 Copy Code"** button to copy the reference code.

### Step 4: Send a Test Webhook

Open a new terminal and run:

```bash
php test-webhook.php PAY-LOAN-1-1 200.00
```

Replace `PAY-LOAN-1-1` with your actual reference code.

### Step 5: Check Payment Status

Go back to the demo page and click **"🔄 Check Payment Status"**.

You should see:
- ✅ Status changed to **"approved"**
- ✅ Success message with transaction ID

**Done! The payment was automatically processed!**

---

## Method 2: Using Artisan Commands (Quick Testing)

### Step 1: Create a Test Payment

```bash
php artisan payment:create-test 1 200.00 "PAY-LOAN-1-1"
```

This creates a pending payment with reference code `PAY-LOAN-1-1`.

### Step 2: Send Test Webhook

```bash
php test-webhook.php PAY-LOAN-1-1 200.00
```

### Step 3: Verify in Database

```bash
php artisan tinker
```

```php
$payment = \App\Models\Payment::where('reference_code', 'PAY-LOAN-1-1')->first();
echo "Status: " . $payment->status;  // Should be "approved"
echo "\nTransaction ID: " . $payment->transaction_id;

$loan = $payment->loan;
echo "\nLoan Balance: $" . $loan->remaining_principal;  // Should be reduced
```

---

## Method 3: Using Postman (API Testing)

### Step 1: Create Payment Request

**POST** `http://127.0.0.1:8000/api/tenant/loans/1/payments/initiate`

Headers:
```
Content-Type: application/json
Accept: application/json
```

Body:
```json
{
  "amount": 200.00,
  "gateway": "simulated",
  "notes": "Test payment"
}
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
    "instructions": "..."
  }
}
```

### Step 2: Send Webhook

**POST** `http://127.0.0.1:8000/api/payment/webhook?tenant=1`

Headers:
```
Content-Type: application/json
```

Body:
```json
{
  "transaction_id": "TEST-TXN-123456",
  "reference": "PAY-LOAN-1-1",
  "amount": 200.00,
  "currency": "USD",
  "status": "completed",
  "payment_date": "2026-05-25T14:30:00Z"
}
```

Response:
```json
{
  "success": true,
  "message": "Webhook received and queued for processing"
}
```

### Step 3: Check Status

**GET** `http://127.0.0.1:8000/api/tenant/payments/15/status`

Response:
```json
{
  "payment_id": 15,
  "reference_code": "PAY-LOAN-1-1",
  "status": "approved",
  "amount": 200.00,
  "transaction_id": "TEST-TXN-123456",
  "approved_at": "2026-05-25T14:30:00.000000Z"
}
```

---

## Method 4: Complete Flow Script

Run the automated test script:

```bash
php test-complete-flow.php 1 200.00 simulated
```

This script will:
1. ✅ Create payment request
2. ✅ Simulate webhook
3. ✅ Check status
4. ✅ Show complete results

---

## Troubleshooting

### ❌ "Internal server error"

**Solution**: Make sure queue worker is running:
```bash
php artisan queue:work
```

### ❌ "Payment not found"

**Solution**: Check if the loan exists:
```bash
php artisan tinker
```
```php
\App\Models\Loan::find(1);  // Should return a loan
```

### ❌ "Authentication required"

**Solution**: For testing, you can temporarily disable auth on the API routes, or create an API token:
```bash
php artisan tinker
```
```php
$user = \App\Models\User::first();
$token = $user->createToken('test-token')->plainTextToken;
echo $token;
```

Then use the token in your requests:
```
Authorization: Bearer {your-token}
```

### ❌ Webhook received but payment not approved

**Checklist**:
1. ✅ Queue worker running? `php artisan queue:work`
2. ✅ Reference code matches exactly?
3. ✅ Amount matches exactly?
4. ✅ Check logs: `storage/logs/webhooks.log`

---

## What Happens Behind the Scenes?

```
1. CREATE PAYMENT REQUEST
   ↓
   PaymentService creates pending payment
   ↓
   Auto-generates reference code: PAY-LOAN-1-1
   ↓
   Returns payment details to borrower

2. BORROWER PAYS
   ↓
   Borrower transfers money with reference code
   ↓
   Payment gateway processes the payment

3. WEBHOOK RECEIVED
   ↓
   PaymentWebhookController receives webhook
   ↓
   Validates signature (if configured)
   ↓
   Queues ProcessPaymentWebhook job
   ↓
   Returns 200 OK immediately

4. QUEUE PROCESSES
   ↓
   ProcessPaymentWebhook job runs
   ↓
   Parses gateway payload
   ↓
   Matches payment by reference code
   ↓
   Checks idempotency (prevents duplicates)
   ↓
   Approves payment
   ↓
   Updates loan balance
   ↓
   Sends Telegram notification
   ↓
   Logs transaction

5. BORROWER SEES CONFIRMATION
   ↓
   Payment status: approved
   ↓
   Loan balance updated
   ↓
   Telegram notification received
```

---

## Next Steps

### For Development:
- ✅ Test with different gateways (ABA, KHQR, Stripe, PayPal)
- ✅ Test error scenarios (wrong reference, wrong amount)
- ✅ Test duplicate webhooks (idempotency)
- ✅ Monitor logs: `storage/logs/webhooks.log`

### For Production:
1. Configure gateway secrets in `.env`
2. Set up webhook URLs in payment gateway dashboards
3. Enable signature validation
4. Set up monitoring and alerts
5. Configure queue workers with supervisor

---

## Files Reference

- **Demo Page**: `public/payment-demo.html`
- **Test Scripts**: 
  - `test-webhook.php` - Send test webhook
  - `test-complete-flow.php` - Complete flow test
- **Documentation**:
  - `REAL_WORLD_EXAMPLE.md` - Complete integration guide
  - `TESTING_GUIDE.md` - Detailed testing instructions
  - `WEBHOOK_USAGE.md` - Production usage guide

---

## Support

If you encounter issues:

1. Check logs: `storage/logs/webhooks.log`
2. Check queue: `php artisan queue:failed`
3. Review documentation: `REAL_WORLD_EXAMPLE.md`
4. Test with simulated gateway first

---

**Ready to test? Start with Method 1 (Web Demo) - it's the easiest!**

Open: `http://127.0.0.1:8000/payment-demo.html`
