# Gmail API & PayMongo Integration Guide

## Part 1: Gmail API Setup for Email Verification

### Step 1: Google Cloud Console Setup

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/
   - Sign in with your Google account

2. **Create a New Project**
   - Click on the project dropdown at the top
   - Click "New Project"
   - Name: "Ivy Dental Clinic Email Service"
   - Click "Create"

3. **Enable Gmail API**
   - Go to "APIs & Services" > "Library"
   - Search for "Gmail API"
   - Click on it and press "Enable"

4. **Create OAuth 2.0 Credentials**
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth client ID"
   - If prompted, configure OAuth consent screen first:
     - User Type: External
     - App name: "Ivy Dental Clinic"
     - User support email: Your email
     - Developer contact: Your email
     - Click "Save and Continue"
     - Scopes: Add `https://www.googleapis.com/auth/gmail.send`
     - Test users: Add your email (for testing)
   - Application type: "Web application"
   - Name: "Ivy Dental Email Service"
   - Authorized redirect URIs: `http://localhost/ivydentalclinic/auth/google/callback.php` (adjust to your domain)
   - Click "Create"
   - **IMPORTANT**: Download the JSON file (credentials.json) - you'll need this!

### Step 2: Install Required PHP Libraries

```bash
composer require google/apiclient
composer require phpmailer/phpmailer
```

Or manually download:
- Google API PHP Client: https://github.com/googleapis/google-api-php-client
- PHPMailer: https://github.com/PHPMailer/PHPMailer

### Step 3: File Structure

Create these files:
```
config/
  credentials.json (from Google Cloud Console)
  gmail_config.php
auth/
  google/
    callback.php
  verify_email.php
  send_verification.php
```

---

## Part 2: PayMongo Setup for Payments

### Step 1: Create PayMongo Account

1. **Sign Up**
   - Visit: https://paymongo.com/
   - Click "Sign Up"
   - Complete registration

2. **Get API Keys**
   - Go to Dashboard > Settings > API Keys
   - Copy your **Secret Key** (starts with `sk_`)
   - Copy your **Public Key** (starts with `pk_`)
   - **IMPORTANT**: Keep secret key secure! Never expose it in frontend code.

3. **Test Mode vs Live Mode**
   - Use Test Mode for development
   - Switch to Live Mode when ready for production

### Step 2: Install PayMongo PHP SDK

```bash
composer require paymongo/paymongo-php
```

Or download from: https://github.com/paymongo/paymongo-php

### Step 3: File Structure

Create these files:
```
config/
  paymongo_config.php
payments/
  paymongo/
    create_payment.php
    verify_payment.php
    webhook.php
```

---

## Implementation Steps

### Phase 1: Email Verification System

1. ✅ Set up Google Cloud Project
2. ✅ Download credentials.json
3. ✅ Install Google API Client
4. ✅ Create email sending service
5. ✅ Create verification code storage (database table)
6. ✅ Modify registration to require verification
7. ✅ Create verification page

### Phase 2: PayMongo Payment System

1. ✅ Set up PayMongo account
2. ✅ Get API keys
3. ✅ Install PayMongo SDK
4. ✅ Create payment intent creation
5. ✅ Modify downpayment flow
6. ✅ Create payment verification
7. ✅ Set up webhook for payment status

---

## Next Steps

After completing the setup steps above, we'll implement:
1. Database tables for email verification
2. Email verification service
3. Modified registration flow
4. PayMongo payment integration
5. Updated downpayment process

