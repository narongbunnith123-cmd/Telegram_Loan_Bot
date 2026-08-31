# Visual Flow Guide - Payment Webhook System

## 🎬 The Complete Story (Step by Step)

---

## Scene 1: Borrower Wants to Pay

```
👤 John (Borrower)
   "I want to pay $200 on my loan"
   
   Opens your app/website
   Clicks "Make Payment"
```

---

## Scene 2: Your App Creates Payment Request

```
🖥️ Your Laravel App
   
   PaymentController receives request:
   ┌─────────────────────────────────┐
   │ Loan ID: 42                     │
   │ Amount: $200.00                 │
   │ Gateway: ABA Bank               │
   └─────────────────────────────────┘
   
   Calls PaymentService:
   ┌─────────────────────────────────┐
   │ createPaymentRequest()          │
   │   ├─ Generate reference code    │
   │   ├─ Create pending payment     │
   │   └─ Return payment details     │
   └─────────────────────────────────┘
   
   Database:
   ┌─────────────────────────────────┐
   │ payments table                  │
   ├─────────────────────────────────┤
   │ id: 15                          │
   │ loan_id: 42                     │
   │ amount: 200.00                  │
   │ status: pending                 │
   │ reference_code: PAY-LOAN-42-1   │
   │ gateway: aba                    │
   └─────────────────────────────────┘
```

---

## Scene 3: Borrower Sees Payment Instructions

```
👤 John's Screen
   
   ┌─────────────────────────────────────────┐
   │  💳 Payment Request Created!            │
   ├─────────────────────────────────────────┤
   │                                         │
   │  Your Reference Code:                   │
   │  ┌───────────────────────────────────┐  │
   │  │  PAY-LOAN-42-1                    │  │
   │  │  [📋 Copy]                        │  │
   │  └───────────────────────────────────┘  │
   │                                         │
   │  Instructions:                          │
   │  Transfer $200.00 to ABA Bank           │
   │  Account: 123456789                     │
   │                                         │
   │  ⚠️ IMPORTANT: Include reference code   │
   │     "PAY-LOAN-42-1" in your transfer    │
   │                                         │
   │  Status: ⏳ Pending                     │
   │                                         │
   │  [🔄 Check Status]                      │
   └─────────────────────────────────────────┘
```

---

## Scene 4: Borrower Makes Payment

```
👤 John opens his banking app
   
   📱 ABA Mobile Banking
   ┌─────────────────────────────────────────┐
   │  Transfer Money                         │
   ├─────────────────────────────────────────┤
   │  To Account: 123456789                  │
   │  Amount: $200.00                        │
   │  Description: PAY-LOAN-42-1             │
   │                                         │
   │  [Confirm Transfer]                     │
   └─────────────────────────────────────────┘
   
   John clicks "Confirm Transfer"
   
   ✅ Transfer successful!
```

---

## Scene 5: ABA Bank Processes Payment

```
🏦 ABA Bank System
   
   ┌─────────────────────────────────┐
   │ Payment Received                │
   ├─────────────────────────────────┤
   │ From: John's Account            │
   │ To: Your Account (123456789)    │
   │ Amount: $200.00                 │
   │ Description: PAY-LOAN-42-1      │
   │ Transaction ID: ABA-TXN-789456  │
   └─────────────────────────────────┘
   
   ABA Bank prepares webhook:
   ┌─────────────────────────────────┐
   │ {                               │
   │   "transaction_id": "ABA-...",  │
   │   "reference": "PAY-LOAN-42-1", │
   │   "amount": 200.00,             │
   │   "status": "completed"         │
   │ }                               │
   └─────────────────────────────────┘
```

---

## Scene 6: Webhook Arrives at Your Server

```
🌐 Internet
   
   ABA Bank → Your Server
   
   POST https://yourdomain.com/api/payment/webhook?tenant=1
   Headers:
     Content-Type: application/json
     X-ABA-Signature: sha256=abc123...
   Body:
     {
       "transaction_id": "ABA-TXN-789456",
       "reference": "PAY-LOAN-42-1",
       "amount": 200.00,
       "currency": "USD",
       "status": "completed",
       "payment_date": "2026-05-25T14:30:00Z"
     }
```

---

## Scene 7: Your Server Receives Webhook

```
🖥️ PaymentWebhookController
   
   Step 1: Validate Signature
   ┌─────────────────────────────────┐
   │ WebhookSignatureValidator       │
   │ ✅ Signature valid              │
   └─────────────────────────────────┘
   
   Step 2: Log Webhook
   ┌─────────────────────────────────┐
   │ webhook_logs table              │
   │ ├─ gateway: aba                 │
   │ ├─ payload: {...}               │
   │ └─ received_at: now()           │
   └─────────────────────────────────┘
   
   Step 3: Queue for Processing
   ┌─────────────────────────────────┐
   │ Queue: ProcessPaymentWebhook    │
   │ Status: Queued                  │
   └─────────────────────────────────┘
   
   Step 4: Respond Immediately
   ┌─────────────────────────────────┐
   │ HTTP 200 OK                     │
   │ {                               │
   │   "success": true,              │
   │   "message": "Webhook received" │
   │ }                               │
   └─────────────────────────────────┘
   
   ⏱️ Total time: < 3 seconds
```

---

## Scene 8: Queue Worker Processes Webhook

```
⚙️ Queue Worker (Background)
   
   ProcessPaymentWebhook Job starts
   
   Step 1: Parse Webhook
   ┌─────────────────────────────────┐
   │ GatewayPayloadParser            │
   │ ├─ Gateway: aba                 │
   │ ├─ Extract transaction_id       │
   │ ├─ Extract reference            │
   │ ├─ Extract amount               │
   │ └─ Extract status               │
   └─────────────────────────────────┘
   
   Parsed Data:
   ┌─────────────────────────────────┐
   │ transaction_id: ABA-TXN-789456  │
   │ reference: PAY-LOAN-42-1        │
   │ amount: 200.00                  │
   │ status: completed               │
   └─────────────────────────────────┘
   
   Step 2: Match Payment
   ┌─────────────────────────────────┐
   │ PaymentMatcher                  │
   │ Search for reference:           │
   │   "PAY-LOAN-42-1"               │
   │                                 │
   │ ✅ Found payment ID: 15         │
   └─────────────────────────────────┘
   
   Step 3: Check Idempotency
   ┌─────────────────────────────────┐
   │ IdempotencyGuard                │
   │ Check if already processed:     │
   │   transaction_id: ABA-TXN-...   │
   │                                 │
   │ ✅ Not processed yet            │
   └─────────────────────────────────┘
   
   Step 4: Approve Payment
   ┌─────────────────────────────────┐
   │ PaymentService                  │
   │ ├─ Update payment status        │
   │ ├─ Set transaction_id           │
   │ └─ Set approved_at              │
   └─────────────────────────────────┘
   
   Database Update:
   ┌─────────────────────────────────┐
   │ payments table                  │
   ├─────────────────────────────────┤
   │ id: 15                          │
   │ status: approved ✅             │
   │ transaction_id: ABA-TXN-789456  │
   │ approved_at: 2026-05-25 14:30   │
   └─────────────────────────────────┘
   
   Step 5: Update Loan Balance
   ┌─────────────────────────────────┐
   │ loans table                     │
   ├─────────────────────────────────┤
   │ id: 42                          │
   │ remaining_principal:            │
   │   Before: $1000.00              │
   │   After:  $800.00 ✅            │
   └─────────────────────────────────┘
   
   Step 6: Create Transaction Record
   ┌─────────────────────────────────┐
   │ payment_transactions table      │
   ├─────────────────────────────────┤
   │ payment_id: 15                  │
   │ gateway: aba                    │
   │ gateway_transaction_id: ABA-... │
   │ amount: 200.00                  │
   │ status: completed               │
   │ processed_at: now()             │
   └─────────────────────────────────┘
   
   Step 7: Send Telegram Notification
   ┌─────────────────────────────────┐
   │ SendPaymentConfirmationJob      │
   │ Queue notification to John      │
   └─────────────────────────────────┘
   
   ✅ Job completed successfully!
```

---

## Scene 9: John Receives Confirmation

```
📱 John's Phone
   
   Telegram Notification:
   ┌─────────────────────────────────────────┐
   │  🤖 Loan Bot                            │
   ├─────────────────────────────────────────┤
   │  ✅ Payment Confirmed!                  │
   │                                         │
   │  Loan ID: 42                            │
   │  Amount Paid: $200.00                   │
   │  Transaction: ABA-TXN-789456            │
   │  Date: May 25, 2026 2:30 PM             │
   │                                         │
   │  New Balance: $800.00                   │
   │                                         │
   │  Thank you for your payment!            │
   └─────────────────────────────────────────┘
```

---

## Scene 10: John Checks Status on Website

```
👤 John refreshes payment page
   
   ┌─────────────────────────────────────────┐
   │  💳 Payment Status                      │
   ├─────────────────────────────────────────┤
   │                                         │
   │  Reference Code: PAY-LOAN-42-1          │
   │                                         │
   │  Status: ✅ Approved                    │
   │                                         │
   │  Amount: $200.00                        │
   │  Transaction ID: ABA-TXN-789456         │
   │  Approved: May 25, 2026 2:30 PM         │
   │                                         │
   │  ✅ Your payment has been received      │
   │     and your loan balance has been      │
   │     updated!                            │
   │                                         │
   │  New Loan Balance: $800.00              │
   └─────────────────────────────────────────┘
```

---

## 🎯 The Magic: What Happened Automatically

```
✅ Payment matched using reference code (no guessing!)
✅ Payment approved automatically
✅ Loan balance updated automatically
✅ Transaction recorded automatically
✅ Telegram notification sent automatically
✅ Audit trail created automatically
✅ Idempotency protected (no duplicates)
✅ Multi-tenant isolation enforced
✅ All in < 5 seconds total
```

---

## 🔄 Data Flow Diagram

```
┌──────────┐
│ Borrower │
└────┬─────┘
     │
     │ 1. Request payment
     ▼
┌─────────────────┐
│ PaymentService  │──────┐
│ createPayment   │      │ 2. Create pending payment
└─────────────────┘      │    with reference code
                         ▼
                    ┌──────────┐
                    │ Database │
                    │ payments │
                    └──────────┘
                         │
     ┌───────────────────┘
     │ 3. Return reference code
     ▼
┌──────────┐
│ Borrower │
│ Pays     │
└────┬─────┘
     │
     │ 4. Transfer with reference
     ▼
┌──────────────┐
│ Bank/Gateway │
└────┬─────────┘
     │
     │ 5. Send webhook
     ▼
┌────────────────────┐
│ WebhookController  │
│ ├─ Validate        │
│ ├─ Log             │
│ └─ Queue           │
└────┬───────────────┘
     │
     │ 6. Queue job
     ▼
┌────────────────────┐
│ ProcessWebhook Job │
│ ├─ Parse           │
│ ├─ Match           │
│ ├─ Approve         │
│ ├─ Update loan     │
│ └─ Notify          │
└────┬───────────────┘
     │
     │ 7. Update database
     ▼
┌──────────┐
│ Database │
│ ├─ payments (approved)
│ ├─ loans (balance updated)
│ └─ payment_transactions
└────┬─────┘
     │
     │ 8. Send notification
     ▼
┌──────────┐
│ Telegram │
└────┬─────┘
     │
     │ 9. Notification received
     ▼
┌──────────┐
│ Borrower │
│ ✅ Done! │
└──────────┘
```

---

## ⏱️ Timeline

```
Time    Event
─────────────────────────────────────────────────────────
00:00   Borrower requests payment
00:01   Payment request created (reference: PAY-LOAN-42-1)
00:02   Borrower sees reference code
─────────────────────────────────────────────────────────
        [Borrower opens banking app]
─────────────────────────────────────────────────────────
05:00   Borrower transfers $200 with reference
05:30   Bank processes transfer
05:31   Bank sends webhook to your server
05:32   Webhook received and queued (< 3 seconds)
05:33   Queue worker picks up job
05:34   Payment matched by reference
05:35   Payment approved
05:36   Loan balance updated
05:37   Transaction recorded
05:38   Telegram notification sent
05:39   Borrower receives notification
─────────────────────────────────────────────────────────
        ✅ Complete! (< 40 seconds from payment to confirmation)
```

---

## 🎓 Key Concepts Explained

### 1. Reference Code
```
PAY-LOAN-42-1
│    │    │  │
│    │    │  └─ Sequence number (1st payment for this loan)
│    │    └──── Loan ID (42)
│    └───────── Type (LOAN)
└────────────── Prefix (PAY)

Purpose: Uniquely identifies which payment this is for
No guessing needed!
```

### 2. Webhook
```
What: HTTP POST request from payment gateway to your server
When: Immediately after payment is processed
Why: Notifies you that payment was received
How: Contains transaction details and reference code
```

### 3. Queue Processing
```
Why use queues?
├─ Webhook endpoint responds fast (< 3 seconds)
├─ Payment gateway doesn't timeout
├─ Processing happens in background
└─ Can retry if something fails

How it works:
1. Webhook arrives → Queue job → Return 200 OK
2. Queue worker picks up job
3. Job processes payment
4. Job completes
```

### 4. Idempotency
```
Problem: What if same webhook is sent twice?
Solution: Check if transaction_id already processed

Example:
1st webhook: transaction_id = ABA-TXN-789456 → Process ✅
2nd webhook: transaction_id = ABA-TXN-789456 → Skip (already processed)

Result: Payment only processed once!
```

---

## 🎬 End Scene

```
┌─────────────────────────────────────────┐
│  🎉 Success!                            │
├─────────────────────────────────────────┤
│                                         │
│  ✅ Payment received                    │
│  ✅ Loan updated                        │
│  ✅ Borrower notified                   │
│  ✅ Transaction recorded                │
│  ✅ Audit trail created                 │
│                                         │
│  All done automatically!                │
│  No manual intervention needed!         │
│                                         │
└─────────────────────────────────────────┘
```

---

**Ready to see it in action?**

Open: `http://127.0.0.1:8000/payment-demo.html`

Or read: `QUICK_START.md`
