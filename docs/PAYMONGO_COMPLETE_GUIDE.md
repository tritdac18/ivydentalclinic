# PayMongo Integration Guide - Complete Setup

## 📋 Table of Contents
1. [Creating PayMongo Account](#1-creating-paymongo-account)
2. [Getting API Keys](#2-getting-api-keys)
3. [Configuring the System](#3-configuring-the-system)
4. [Setting Up Webhooks](#4-setting-up-webhooks)
5. [Testing the Integration](#5-testing-the-integration)
6. [Going Live](#6-going-live)

---

## 1. Creating PayMongo Account

### Step 1.1: Sign Up (Individual Account is Fine for Testing)
1. Go to **https://paymongo.com/**
2. Click **"Sign Up"** or **"Get Started"**
3. Fill in your details:
   - **Account Type**: Select **"Individual"** (perfect for testing!)
   - Name: Your name
   - Email: Your email address
   - Password: Create a strong password
   - Phone Number: Your phone number
4. Verify your email address
5. **That's it!** For test mode, you don't need business verification

### Step 1.2: Test Mode Setup (No Business Verification Needed)
✅ **Good News**: For test mode, you can use PayMongo immediately without:
- Business verification
- Business documents
- Tax ID
- Business profile completion

**Note**: You'll only need business verification when you're ready to go **LIVE** (accept real payments). For now, test mode works perfectly with an individual account!

---

## 2. Getting API Keys

### Step 2.1: Access API Keys
1. Log into PayMongo Dashboard
2. Go to **Settings > API Keys**
3. You'll see two sections:
   - **Test Keys** (for development/testing)
   - **Live Keys** (for production)

### Step 2.2: Copy Test Keys (Start Here)
1. In the **Test Keys** section:
   - **Secret Key** (starts with `sk_test_...`)
   - **Public Key** (starts with `pk_test_...`)
2. Copy both keys (you'll need them in Step 3)

### Step 2.3: Copy Live Keys (For Production)
1. After testing, go to **Live Keys** section
2. Copy:
   - **Secret Key** (starts with `sk_live_...`)
   - **Public Key** (starts with `pk_live_...`)
3. Keep these secure - use them only in production

---

## 3. Configuring the System

### Step 3.1: Update Configuration File
1. Open `config/paymongo_config.php`
2. Replace the placeholder keys:

```php
// TEST MODE (for development)
define('PAYMONGO_SECRET_KEY', 'sk_test_YOUR_ACTUAL_TEST_SECRET_KEY');
define('PAYMONGO_PUBLIC_KEY', 'pk_test_YOUR_ACTUAL_TEST_PUBLIC_KEY');

// LIVE MODE (uncomment when ready for production)
// define('PAYMONGO_SECRET_KEY', 'sk_live_YOUR_ACTUAL_LIVE_SECRET_KEY');
// define('PAYMONGO_PUBLIC_KEY', 'pk_live_YOUR_ACTUAL_LIVE_PUBLIC_KEY');
```

3. Update the return URL:
```php
// In services/payment_service.php, update the return_url:
'return_url' => 'https://ivydentalbckup.gt.tc/payments/paymongo/return.php'
```

### Step 3.2: Verify Configuration
1. Visit: `check_paymongo_setup.php` (we'll create this)
2. Should show: ✅ PayMongo is configured

---

## 4. Setting Up Webhooks

### Step 4.1: Create Webhook Endpoint
1. In PayMongo Dashboard, go to **Settings > Webhooks**
2. Click **"Create Webhook"**
3. Enter:
   - **Webhook URL**: `https://ivydentalbckup.gt.tc/payments/paymongo/webhook.php`
   - **Events to listen for**:
     - ✅ `payment_intent.succeeded`
     - ✅ `payment_intent.payment_failed`
     - ✅ `payment_intent.awaiting_payment_method`
4. Click **"Create Webhook"**
5. Copy the **Webhook Secret** (starts with `whsec_...`)

### Step 4.2: Update Webhook Secret
1. Open `config/paymongo_config.php`
2. Update:
```php
define('PAYMONGO_WEBHOOK_SECRET', 'whsec_YOUR_ACTUAL_WEBHOOK_SECRET');
```

---

## 5. Testing the Integration

### Step 5.1: Test Payment Flow
1. **Create a test appointment** (as a patient)
2. **Go to downpayment page**
3. **Click "Pay with PayMongo"**
4. **Use PayMongo test cards**:
   - **Success**: `4242 4242 4242 4242`
   - **Declined**: `4000 0000 0000 0002`
   - **3D Secure**: `4000 0025 0000 3155`
   - **Expiry**: Any future date (e.g., 12/25)
   - **CVC**: Any 3 digits (e.g., 123)
   - **ZIP**: Any 5 digits (e.g., 12345)

### Step 5.2: Verify Test Payment
1. Check PayMongo Dashboard > **Transactions**
2. Should see test payment with status
3. Check your database - payment should be recorded
4. Appointment status should update to "UPCOMING"

### Step 5.3: Test Webhook
1. Make a test payment
2. Check webhook logs in PayMongo Dashboard
3. Verify webhook was received (check server logs)

---

## 6. Going Live (For Later - When Ready for Real Payments)

### Step 6.1: Upgrade to Business Account (When Needed)
1. **For now**: Individual account + Test mode is perfect!
2. **Later**: When ready for real payments:
   - Upgrade to Business account in PayMongo
   - Complete business verification
   - Submit business documents
   - Get approved for live mode

### Step 6.2: Switch to Live Keys (When Ready)
1. After business verification is approved
2. Get live API keys from PayMongo Dashboard
3. Update `config/paymongo_config.php`:
   - Comment out test keys
   - Uncomment live keys
   - Update webhook secret (get new one for live mode)

### Step 6.3: Update Webhook for Live Mode
1. Create new webhook in PayMongo (live mode)
2. Use same URL: `https://ivydentalbckup.gt.tc/payments/paymongo/webhook.php`
3. Update webhook secret in config

### Step 6.4: Final Testing
1. Make a small real payment
2. Verify payment appears in PayMongo Dashboard
3. Verify webhook receives events
4. Verify payment is recorded in database

**Note**: You can stay in test mode indefinitely! Only upgrade when you're ready to accept real payments.

---

## 🔧 Files That Need Updates

1. ✅ `config/paymongo_config.php` - API keys
2. ✅ `services/payment_service.php` - Payment processing
3. ✅ `process_downpayment.php` - Downpayment handler
4. ✅ `book_appointment.php` - Frontend payment form
5. ✅ `payments/paymongo/return.php` - Payment return page
6. ✅ `payments/paymongo/webhook.php` - Webhook handler

---

## 📱 Supported Payment Methods

PayMongo supports:
- ✅ **GCash** (via PayMongo)
- ✅ **GrabPay** (via PayMongo)
- ✅ **Credit/Debit Cards** (Visa, Mastercard)
- ✅ **PayMaya** (via PayMongo)

All handled through PayMongo's unified API!

---

## 🚨 Important Notes

1. **Never commit API keys to Git**
   - Add `config/paymongo_config.php` to `.gitignore`
   - Use environment variables in production

2. **Test thoroughly before going live**
   - Test all payment methods
   - Test failed payments
   - Test webhooks

3. **Monitor webhooks**
   - Check PayMongo Dashboard regularly
   - Set up error alerts
   - Log all webhook events

4. **Security**
   - Always verify webhook signatures
   - Use HTTPS for webhook URLs
   - Keep API keys secure

---

## 🆘 Troubleshooting

### Issue: "PayMongo is not configured"
**Solution:** Check that API keys are set in `config/paymongo_config.php`

### Issue: Payment fails
**Solution:** 
- Check PayMongo Dashboard for error details
- Verify API keys are correct
- Check webhook is receiving events

### Issue: Webhook not receiving events
**Solution:**
- Verify webhook URL is accessible
- Check webhook secret is correct
- Ensure webhook is enabled in PayMongo Dashboard

---

## ✅ Checklist

- [ ] PayMongo account created
- [ ] Business profile completed
- [ ] Test API keys obtained
- [ ] Configuration file updated
- [ ] Webhook created and configured
- [ ] Test payment successful
- [ ] Webhook receiving events
- [ ] Live API keys obtained (when ready)
- [ ] Live webhook configured
- [ ] Production testing complete

---

## 📞 Support

- **PayMongo Docs**: https://developers.paymongo.com/
- **PayMongo Support**: support@paymongo.com
- **API Reference**: https://developers.paymongo.com/reference

