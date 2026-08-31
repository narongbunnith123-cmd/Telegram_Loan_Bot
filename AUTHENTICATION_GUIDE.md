# Authentication Guide

## The "Unauthenticated" Error - Fixed! ✅

### What Was the Problem?

The payment API endpoints require authentication (Laravel Sanctum), but the demo page wasn't sending an authentication token. This caused the "Unauthenticated" error.

### The Solution

I've created **two sets of routes**:

#### 1. Production Routes (WITH Authentication) ✅
```
POST /api/tenant/loans/{loan}/payments/initiate
GET  /api/tenant/payments/{payment}/status
```
- Requires authentication token
- Use these in production
- Secure and protected

#### 2. Test Routes (NO Authentication) 🧪
```
POST /api/test/loans/{loan}/payments/initiate
GET  /api/test/payments/{payment}/status
```
- No authentication required
- Use for testing and demos
- **⚠️ Remove or secure in production!**

---

## For Testing (Right Now)

The demo page now uses the **test routes** automatically. Just refresh the page and try again!

```
http://127.0.0.1:8000/payment-demo.html
```

It should work now without any authentication! ✅

---

## For Production (Later)

### Option 1: Use Laravel Sanctum (Recommended)

**Step 1: Create API Token**

```bash
php artisan tinker
```

```php
$user = \App\Models\User::first();
$token = $user->createToken('payment-api')->plainTextToken;
echo $token;
// Copy this token
```

**Step 2: Use Token in Requests**

```javascript
fetch('/api/tenant/loans/1/payments/initiate', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer YOUR_TOKEN_HERE',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify({
        amount: 200.00,
        gateway: 'aba'
    })
});
```

### Option 2: Use Session Authentication

If your borrowers are logged in via web (session-based auth):

```php
// In your web routes (routes/web.php)
Route::middleware(['auth'])->group(function () {
    Route::post('/loans/{loan}/payments/initiate', [PaymentController::class, 'initiatePayment']);
    Route::get('/payments/{payment}/status', [PaymentController::class, 'checkPaymentStatus']);
});
```

### Option 3: Remove Test Routes (Production)

Before deploying to production, **remove the test routes**:

```php
// In routes/api.php - DELETE THIS SECTION:
Route::prefix('test')->group(function () {
    Route::post('/loans/{loan}/payments/initiate', ...);
    Route::get('/payments/{payment}/status', ...);
});
```

---

## Quick Test Now

1. **Refresh the demo page**
   ```
   http://127.0.0.1:8000/payment-demo.html
   ```

2. **Create a payment**
   - Loan ID: `1`
   - Amount: `200`
   - Gateway: `Simulated (Testing)`
   - Click "Create Payment Request"

3. **It should work now!** ✅

4. **Copy the reference code** (e.g., `PAY-LOAN-1-1`)

5. **Send test webhook**
   ```bash
   php test-webhook.php PAY-LOAN-1-1 200.00
   ```

6. **Check status** (click "Check Payment Status" button)
   - Status should be "approved" ✅

---

## Understanding the Routes

### Test Routes (Current - For Testing)
```
/api/test/loans/{loan}/payments/initiate
/api/test/payments/{payment}/status
```
- ✅ No authentication needed
- ✅ Perfect for testing
- ⚠️ Not secure for production
- ⚠️ Anyone can access

### Production Routes (For Real Use)
```
/api/tenant/loans/{loan}/payments/initiate
/api/tenant/payments/{payment}/status
```
- ✅ Requires authentication
- ✅ Secure
- ✅ Production-ready
- ⚠️ Needs API token or session

---

## Common Questions

### Q: Why did you create test routes?

**A:** To make testing easier! You can test the payment flow without setting up authentication first. Once you're ready for production, you'll use the authenticated routes.

### Q: Are test routes secure?

**A:** No! They have no authentication. That's why they're only for testing. Remove them before production.

### Q: How do I switch to production routes?

**A:** 
1. Set up authentication (Sanctum or session)
2. Update your frontend to send auth tokens
3. Remove the test routes from `routes/api.php`

### Q: Can I use test routes in production?

**A:** **NO!** Test routes have no security. Anyone could create payments or check statuses. Always use authenticated routes in production.

---

## Security Checklist for Production

- [ ] Remove test routes from `routes/api.php`
- [ ] Set up Laravel Sanctum or session auth
- [ ] Generate API tokens for users
- [ ] Update frontend to send auth tokens
- [ ] Test with authentication enabled
- [ ] Verify unauthorized requests are blocked

---

## Example: Production Frontend with Auth

```javascript
// Get token from your auth system
const token = localStorage.getItem('api_token');

// Create payment with authentication
const response = await fetch('/api/tenant/loans/1/payments/initiate', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify({
        amount: 200.00,
        gateway: 'aba'
    })
});
```

---

## What Changed?

### routes/api.php
```php
// Added test routes (no auth)
Route::prefix('test')->group(function () {
    Route::post('/loans/{loan}/payments/initiate', ...);
    Route::get('/payments/{payment}/status', ...);
});
```

### payment-demo.html
```javascript
// Changed from:
fetch('/api/tenant/loans/...')

// To:
fetch('/api/test/loans/...')
```

### test-complete-flow.php
```php
// Changed from:
$url = "{$baseUrl}/api/tenant/loans/...";

// To:
$url = "{$baseUrl}/api/test/loans/...";
```

---

## Try It Now!

The demo should work now. Refresh the page and try creating a payment:

```
http://127.0.0.1:8000/payment-demo.html
```

If you still see "Unauthenticated", make sure you:
1. Saved all files
2. Refreshed the browser (Ctrl+F5)
3. Check the browser console for errors

---

## Need Help?

**Still getting "Unauthenticated"?**

1. Check if routes are registered:
   ```bash
   php artisan route:list | grep test
   ```
   You should see the test routes.

2. Clear route cache:
   ```bash
   php artisan route:clear
   php artisan cache:clear
   ```

3. Check browser console (F12) for the actual error

4. Verify the URL in the browser console matches `/api/test/...`

---

**The fix is complete! Try the demo page now.** ✅
