# 💳 Payment Webhook System - README

## 🎯 What Is This?

An **automated payment processing system** that:
- Creates unique payment reference codes
- Receives webhooks from payment gateways
- Automatically approves payments
- Updates loan balances
- Sends notifications

**No manual work needed!** Everything happens automatically.

---

## 🚀 Quick Start (5 Minutes)

### Option 1: Web Demo (Easiest!)

1. **Start your Laravel app**
   ```bash
   php artisan serve
   ```

2. **Start queue worker** (in another terminal)
   ```bash
   php artisan queue:work
   ```

3. **Open the demo page**
   ```
   http://127.0.0.1:8000/payment-demo.html
   ```

4. **Create a payment**
   - Enter Loan ID: `1`
   - Enter Amount: `200.00`
   - Select Gateway: `Simulated (Testing)`
   - Click "Create Payment Request"

5. **Copy the reference code** (e.g., `PAY-LOAN-1-1`)

6. **Send test webhook** (in terminal)
   ```bash
   php test-webhook.php PAY-LOAN-1-1 200.00
   ```

7. **Check status** (click "Check Payment Status" button)
   - Status should change to "approved" ✅

**Done! You just processed a payment automatically!**

---

### Option 2: Command Line

```bash
# 1. Create test payment
php artisan payment:create-test 1 200.00 "PAY-LOAN-1-1"

# 2. Send test webhook
php test-webhook.php PAY-LOAN-1-1 200.00

# 3. Check in database
php artisan tinker
>>> $payment = \App\Models\Payment::where('reference_code', 'PAY-LOAN-1-1')->first();
>>> echo $payment->status;  // "approved"
```

---

## 📚 Documentation

| Document | Purpose | When to Read |
|----------|---------|--------------|
| **QUICK_START.md** | Get testing in 5 minutes | **Start here!** |
| **VISUAL_FLOW_GUIDE.md** | See how it works visually | Understand the flow |
| **REAL_WORLD_EXAMPLE.md** | Complete code examples | Integrate into your app |
| **TESTING_GUIDE.md** | Detailed testing scenarios | Test thoroughly |
| **WEBHOOK_USAGE.md** | Production deployment | Deploy to production |
| **PAYMENT_WEBHOOK_SUMMARY.md** | Complete overview | Reference guide |

---

## 🎬 How It Works (Simple Version)

```
1. Borrower wants to pay
   ↓
2. Your app creates payment request with reference code
   ↓
3. Borrower pays using the reference code
   ↓
4. Payment gateway sends webhook to your server
   ↓
5. System automatically:
   ✅ Approves payment
   ✅ Updates loan balance
   ✅ Sends notification
   ↓
6. Done! (all automatic)
```

**See detailed visual flow:** `VISUAL_FLOW_GUIDE.md`

---

## 💻 Integration Example

### In Your Controller

```php
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}
    
    public function initiatePayment(Request $request, Loan $loan)
    {
        // Create payment request with auto-generated reference
        $payment = $this->paymentService->createPaymentRequest(
            $loan,
            $request->amount,
            $request->gateway  // 'aba', 'khqr', 'stripe', 'paypal'
        );
        
        // Show borrower the reference code
        return response()->json([
            'reference_code' => $payment->reference_code,
            'amount' => $payment->amount,
            'instructions' => "Pay ${payment->amount} using reference: {$payment->reference_code}"
        ]);
    }
}
```

### In Your Frontend

```javascript
// Create payment request
const response = await fetch('/api/tenant/loans/1/payments/initiate', {
    method: 'POST',
    body: JSON.stringify({
        amount: 200.00,
        gateway: 'aba'
    })
});

const data = await response.json();

// Show borrower the reference code
console.log(data.reference_code);  // "PAY-LOAN-1-1"
```

**See complete examples:** `REAL_WORLD_EXAMPLE.md`

---

## 🔧 What Was Built

### Core Components

✅ **PaymentReferenceGenerator** - Generates unique reference codes  
✅ **WebhookSignatureValidator** - Validates webhook signatures  
✅ **GatewayPayloadParser** - Parses webhooks from 5 gateways  
✅ **PaymentMatcher** - Matches webhooks to payments  
✅ **IdempotencyGuard** - Prevents duplicate processing  
✅ **PaymentWebhookController** - Receives webhooks  
✅ **ProcessPaymentWebhook** - Processes webhooks async  

### Database Tables

✅ **payment_transactions** - Stores webhook events  
✅ **payments** - Extended with webhook fields  

### API Endpoints

✅ `POST /api/payment/webhook` - Webhook endpoint  
✅ `POST /api/tenant/loans/{loan}/payments/initiate` - Create payment  
✅ `GET /api/tenant/payments/{payment}/status` - Check status  

### Testing Tools

✅ **payment-demo.html** - Interactive web demo  
✅ **test-webhook.php** - Send test webhooks  
✅ **test-complete-flow.php** - Automated testing  
✅ **php artisan payment:create-test** - Create test payments  

---

## 🎯 Supported Gateways

| Gateway | Status | Use Case |
|---------|--------|----------|
| **Simulated** | ✅ Ready | Testing |
| **ABA Bank** | ✅ Ready | Cambodia |
| **KHQR** | ✅ Ready | Cambodia |
| **Stripe** | ✅ Ready | International |
| **PayPal** | ✅ Ready | International |

---

## 🔐 Security Features

✅ **Signature Validation** - Verifies webhooks are legitimate  
✅ **Idempotency Protection** - Prevents duplicate processing  
✅ **Multi-Tenant Isolation** - Each tenant's data is isolated  
✅ **Rate Limiting** - Prevents webhook flooding  
✅ **Reference Code Matching** - No guessing who paid  
✅ **Audit Logging** - All webhooks logged  

---

## 📋 Testing Checklist

- [ ] Create payment request
- [ ] Verify reference code generated
- [ ] Send test webhook
- [ ] Verify payment approved
- [ ] Verify loan balance updated
- [ ] Verify notification sent
- [ ] Test with wrong reference (should fail)
- [ ] Test duplicate webhook (should be idempotent)

**Detailed testing guide:** `TESTING_GUIDE.md`

---

## 🚨 Troubleshooting

### Payment not approved?

**Check:**
1. Is queue worker running? `php artisan queue:work`
2. Does reference code match exactly?
3. Does amount match exactly?
4. Check logs: `storage/logs/webhooks.log`

### Webhook not received?

**Check:**
1. Is route registered? `php artisan route:list | grep webhook`
2. Is server accessible?
3. Is webhook URL configured in gateway?

### Internal server error?

**Check:**
1. Check logs: `storage/logs/laravel.log`
2. Run migrations: `php artisan migrate`
3. Clear cache: `php artisan cache:clear`

---

## 📁 File Structure

```
telegram-loan-bot/
├── app/
│   ├── Services/Payment/
│   │   ├── PaymentReferenceGenerator.php
│   │   ├── WebhookSignatureValidator.php
│   │   ├── GatewayPayloadParser.php
│   │   ├── PaymentMatcher.php
│   │   ├── IdempotencyGuard.php
│   │   └── ParsedWebhook.php
│   ├── Http/Controllers/Payment/
│   │   └── PaymentWebhookController.php
│   ├── Jobs/Payment/
│   │   └── ProcessPaymentWebhook.php
│   └── Models/
│       ├── Payment.php
│       └── PaymentTransaction.php
├── config/
│   └── payment.php
├── database/migrations/
│   ├── 2026_05_25_040000_create_payment_transactions_table.php
│   └── 2026_05_25_203455_add_webhook_fields_to_payments_table.php
├── public/
│   └── payment-demo.html
├── routes/
│   └── api.php
├── test-webhook.php
├── test-complete-flow.php
└── Documentation/
    ├── QUICK_START.md ⭐ Start here!
    ├── VISUAL_FLOW_GUIDE.md
    ├── REAL_WORLD_EXAMPLE.md
    ├── TESTING_GUIDE.md
    ├── WEBHOOK_USAGE.md
    └── PAYMENT_WEBHOOK_SUMMARY.md
```

---

## 🎓 Learning Path

**For Beginners:**
1. Read `VISUAL_FLOW_GUIDE.md` - Understand how it works
2. Follow `QUICK_START.md` - Get it running
3. Try the web demo - See it in action

**For Developers:**
1. Read `REAL_WORLD_EXAMPLE.md` - See code examples
2. Read `TESTING_GUIDE.md` - Test thoroughly
3. Integrate into your app

**For DevOps:**
1. Read `WEBHOOK_USAGE.md` - Production setup
2. Configure gateways
3. Set up monitoring

---

## 🎯 Next Steps

### Right Now (Testing):
1. ✅ Open `QUICK_START.md`
2. ✅ Try the web demo
3. ✅ Send test webhooks

### This Week (Integration):
1. ✅ Read `REAL_WORLD_EXAMPLE.md`
2. ✅ Integrate into your app
3. ✅ Test with real scenarios

### Before Production:
1. ✅ Read `WEBHOOK_USAGE.md`
2. ✅ Configure gateway secrets
3. ✅ Set up monitoring
4. ✅ Test with gateway sandbox

---

## 📞 Support

**Documentation:**
- Quick start: `QUICK_START.md`
- Visual guide: `VISUAL_FLOW_GUIDE.md`
- Code examples: `REAL_WORLD_EXAMPLE.md`
- Testing: `TESTING_GUIDE.md`
- Production: `WEBHOOK_USAGE.md`

**Logs:**
- Webhooks: `storage/logs/webhooks.log`
- Application: `storage/logs/laravel.log`
- Failed jobs: `php artisan queue:failed`

**Tools:**
- Web demo: `http://127.0.0.1:8000/payment-demo.html`
- Test webhook: `php test-webhook.php`
- Complete flow: `php test-complete-flow.php`

---

## ✅ Status

**Implementation:** ✅ 100% Complete (73/73 tasks)  
**Documentation:** ✅ Complete  
**Testing Tools:** ✅ Ready  
**Production Ready:** ✅ Yes (after configuration)

---

## 🎉 Get Started Now!

**Easiest way to start:**

1. Open terminal:
   ```bash
   php artisan serve
   ```

2. Open another terminal:
   ```bash
   php artisan queue:work
   ```

3. Open browser:
   ```
   http://127.0.0.1:8000/payment-demo.html
   ```

4. Create a payment and test it!

**Or read the quick start guide:**
```
Open: QUICK_START.md
```

---

**Questions? Check the documentation files above!**

**Ready to integrate? See `REAL_WORLD_EXAMPLE.md`**

**Need help? Check `TROUBLESHOOTING` section above**
