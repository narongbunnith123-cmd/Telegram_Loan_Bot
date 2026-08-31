# 🧪 Simple Testing Guide - Payment Webhook

## Step-by-Step Testing Instructions

Follow these steps **exactly** to test the webhook system:

---

## ✅ Step 1: Check Your Loans

First, let's see what loans you have:

```bash
php artisan tinker --execute="echo 'Available Loans: ' . App\Models\Loan::pluck('id')->implode(', ');"
```

**Example Output:**
```
Available Loans: 1, 2, 3, 4
```

Pick any loan ID from this list. Let's say you pick **loan ID 1**.

---

## ✅ Step 2: Create a Test Payment Request

Create a pending payment with a reference code:

```bash
php artisan payment:create-test 1 100.00 "PAY-LOAN-1-TEST"
```

**What this does:**
- Creates a payment for loan ID 1
- Amount: $100.00
- Reference code: `PAY-LOAN-1-TEST`
- Status: `pending` (waiting for webhook)

**Expected Output:**
```
Payment created successfully!
ID: 6
Reference: PAY-LOAN-1-TEST
Amount: 100.00
Status: pending
```

✏️ **Write down the reference code:** `PAY-LOAN-1-TEST`

---

## ✅ Step 3: Send Webhook via Postman

Now open Postman and configure:

### 📍 Request Settings:

**Method:** `POST`

**URL:** 
```
http://127.0.0.1:8000/api/payment/webhook?tenant=1
```

**Headers:**
```
Content-Type: application/json
```

**Body** (select "raw" and "JSON"):
```json
{
  "transaction_id": "TXN-TEST-123",
  "reference": "PAY-LOAN-1-TEST",
  "amount": 100.00,
  "currency": "USD",
  "status": "completed",
  "payment_date": "2026-05-26T10:30:00Z"
}
```

⚠️ **IMPORTANT:** Make sure the `reference` matches what you created in Step 2!

### 📤 Click "Send"

**Expected Response (200 OK):**
```json
{
  "success": true,
  "message": "Webhook received",
  "transaction_id": 8
}
```

---

## ✅ Step 4: Verify Payment Was Processed

Check if the payment was approved:

```bash
php artisan tinker --execute="echo App\Models\Payment::where('reference_code', 'PAY-LOAN-1-TEST')->first()->status;"
```

**Expected Output:**
```
approved
```

✅ **Success!** The payment was automatically approved by the webhook!

---

## ✅ Step 5: Check the Transaction Log

See the webhook transaction details:

```bash
php artisan tinker --execute="
\$tx = App\Models\PaymentTransaction::latest()->first();
echo 'Status: ' . \$tx->status . PHP_EOL;
echo 'Source: ' . \$tx->source . PHP_EOL;
"
```

**Expected Output:**
```
Status: processed
Source: simulated
```

---

## 🎯 Quick Test (All in One)

If you want to test quickly, run these commands one after another:

```bash
# 1. Create payment
php artisan payment:create-test 1 50.00 "PAY-LOAN-1-QUICK"

# 2. Send webhook (using the test script)
php test-webhook.php

# 3. Check result
php artisan tinker --execute="echo App\Models\Payment::where('reference_code', 'PAY-LOAN-1-QUICK')->first()->status;"
```

---

## 🔄 Testing Multiple Payments

You can test multiple times by changing the reference code:

### Test 1:
```bash
php artisan payment:create-test 1 100.00 "PAY-LOAN-1-1"
# Send webhook with reference: "PAY-LOAN-1-1"
```

### Test 2:
```bash
php artisan payment:create-test 1 200.00 "PAY-LOAN-1-2"
# Send webhook with reference: "PAY-LOAN-1-2"
```

### Test 3:
```bash
php artisan payment:create-test 2 150.00 "PAY-LOAN-2-1"
# Send webhook with reference: "PAY-LOAN-2-1"
```

---

## ❌ Common Mistakes

### Mistake 1: Reference Code Doesn't Match

**Problem:**
```bash
# Created payment with: PAY-LOAN-1-1
# But sent webhook with: PAY-LOAN-1-2  ❌ WRONG!
```

**Solution:** Make sure the reference codes match exactly!

### Mistake 2: Payment Already Approved

**Problem:**
```
Payment not found for reference: PAY-LOAN-1-1
```

**Reason:** You already tested with this reference code and it was approved.

**Solution:** Create a new payment with a different reference code:
```bash
php artisan payment:create-test 1 100.00 "PAY-LOAN-1-NEW"
```

### Mistake 3: Wrong Tenant ID

**Problem:**
```
Tenant mismatch
```

**Solution:** Make sure `?tenant=1` in the URL matches your loan's tenant_id.

---

## 🎓 Understanding the Flow

Here's what happens when you test:

```
1. You create payment → Status: pending
                         Reference: PAY-LOAN-1-TEST
                         
2. You send webhook → System receives it
                      Logs to payment_transactions
                      
3. System processes → Finds payment by reference
                      Approves payment
                      Updates loan balance
                      
4. Result → Payment status: approved ✅
            Loan balance: reduced ✅
            Transaction: processed ✅
```

---

## 📊 Checking Results

### Check Payment Status:
```bash
php artisan tinker --execute="
\$p = App\Models\Payment::where('reference_code', 'PAY-LOAN-1-TEST')->first();
echo 'Status: ' . \$p->status . PHP_EOL;
echo 'Amount: ' . \$p->amount . PHP_EOL;
echo 'Gateway: ' . \$p->gateway_name . PHP_EOL;
"
```

### Check Loan Balance:
```bash
php artisan tinker --execute="
\$loan = App\Models\Loan::find(1);
echo 'Remaining Principal: ' . \$loan->remaining_principal . PHP_EOL;
echo 'Balance: ' . \$loan->balance . PHP_EOL;
"
```

### Check All Transactions:
```bash
php artisan tinker --execute="
App\Models\PaymentTransaction::latest()->take(5)->get(['id', 'status', 'source', 'created_at'])->each(function(\$tx) {
    echo 'ID: ' . \$tx->id . ' | Status: ' . \$tx->status . ' | Source: ' . \$tx->source . PHP_EOL;
});
"
```

---

## 🆘 Troubleshooting

### If webhook returns error:

1. **Check Laravel logs:**
```bash
tail -f storage/logs/laravel.log
```

2. **Check webhook logs:**
```bash
tail -f storage/logs/webhooks.log
```

3. **Check if payment exists:**
```bash
php artisan tinker --execute="echo App\Models\Payment::where('reference_code', 'YOUR-REFERENCE')->exists() ? 'Found' : 'Not Found';"
```

---

## ✨ Success Checklist

After testing, you should see:

- ✅ Payment status changed from `pending` to `approved`
- ✅ Loan balance decreased by payment amount
- ✅ Transaction status is `processed`
- ✅ Gateway name is `simulated`
- ✅ Transaction ID is stored in payment

If all these are true, **your webhook system is working perfectly!** 🎉

---

## 🚀 Next: Test with Different Scenarios

### Scenario 1: Partial Payment
```bash
php artisan payment:create-test 1 50.00 "PAY-LOAN-1-PARTIAL"
# Send webhook with amount: 50.00
```

### Scenario 2: Full Payment
```bash
# Check loan balance first
php artisan tinker --execute="echo App\Models\Loan::find(1)->balance;"
# Create payment for full amount
php artisan payment:create-test 1 {full_amount} "PAY-LOAN-1-FULL"
```

### Scenario 3: Different Loan
```bash
php artisan payment:create-test 2 100.00 "PAY-LOAN-2-1"
# Send webhook with reference: PAY-LOAN-2-1
```

---

## 📞 Need Help?

If something doesn't work:

1. Check the reference code matches exactly
2. Make sure the payment status is `pending` before sending webhook
3. Check the logs for error messages
4. Make sure your Laravel server is running (`php artisan serve`)

That's it! Happy testing! 🎉
