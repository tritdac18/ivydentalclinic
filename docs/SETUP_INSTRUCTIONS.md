# Step-by-Step Setup Instructions

## Part 1: Gmail API Setup (Email Verification)

### Step 1: Google Cloud Console Setup

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/
   - Sign in with your Google account

2. **Create a New Project**
   - Click the project dropdown at the top
   - Click "New Project"
   - Name: "Ivy Dental Clinic Email Service"
   - Click "Create"
   - Wait for project creation, then select it

3. **Enable Gmail API**
   - Go to "APIs & Services" > "Library" (left sidebar)
   - Search for "Gmail API"
   - Click on "Gmail API"
   - Click "Enable"

4. **Configure OAuth Consent Screen**
   - Go to "APIs & Services" > "OAuth consent screen"
   - User Type: Select "External" (unless you have Google Workspace)
   - Click "Create"
   - Fill in:
     - App name: "Ivy Dental Clinic"
     - User support email: Your email
     - Developer contact: Your email
   - Click "Save and Continue"
   - Scopes: Click "Add or Remove Scopes"
     - Search for: `https://www.googleapis.com/auth/gmail.send`
     - Check the box
     - Click "Update" then "Save and Continue"
   - Test users: Add your email address (for testing)
   - Click "Save and Continue" then "Back to Dashboard"

5. **Create OAuth 2.0 Credentials**
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth client ID"
   - Application type: "Web application"
   - Name: "Ivy Dental Email Service"
   - Authorized redirect URIs: 
     - **IMPORTANT**: The URI must match EXACTLY what's in your code
     - For your domain: `https://ivydentalbckup.gt.tc/auth/google/callback.php`
     - For local testing: `http://localhost/ivydentalclinic/auth/google/callback.php`
     - **NO trailing slashes, exact match required**
   - Click "Create"
   - **IMPORTANT**: A popup will show your Client ID and Client Secret
   - Click "Download JSON" - this is your `credentials.json` file
   - Save it to: `config/credentials.json`

6. **Update Configuration**
   - Open `config/gmail_config.php`
   - Update `GMAIL_REDIRECT_URI` to match your domain:
     ```php
     define('GMAIL_REDIRECT_URI', 'https://ivydentalbckup.gt.tc/auth/google/callback.php');
     ```
   - Update `GMAIL_SENDER_EMAIL` to your Gmail address

7. **First-Time Authorization**
   - Visit: `https://ivydentalbckup.gt.tc/auth/google/setup.php`
   - This page will show you the exact redirect URI to use
   - Make sure it matches in Google Cloud Console
   - Click "Authorize Gmail API"
   - Authorize the application
   - Token will be saved automatically

### Step 2: Install Dependencies

**Option A: Using Composer (Recommended)**
```bash
cd C:\Users\Acer\Downloads\ivydentalclinic
composer require google/apiclient
```

**Option B: Manual Installation**
1. Download: https://github.com/googleapis/google-api-php-client/releases
2. Extract to `vendor/google/apiclient/`
3. Update autoload paths in `services/email_service.php`

### Step 3: Update Configuration

1. **Update `config/gmail_config.php`**:
   - Set `GMAIL_SENDER_EMAIL` to your Gmail address
   - Set `GMAIL_REDIRECT_URI` to match your domain

2. **Place `credentials.json`**:
   - Copy the downloaded file to `config/credentials.json`

3. **Run Database Migration**:
   ```sql
   -- Run this in your database
   source create_tbl_email_verification.sql;
   ```

### Step 4: Test Email Service

1. Visit: `http://localhost/ivydentalclinic/auth/google/callback.php?setup=1`
2. Authorize Gmail access
3. Test sending: Create a test script or use registration form

---

## Part 2: PayMongo Setup (Payments)

### Step 1: Create PayMongo Account

1. **Sign Up**
   - Visit: https://paymongo.com/
   - Click "Sign Up"
   - Complete registration with business details

2. **Get API Keys**
   - Go to Dashboard
   - Click "Settings" > "API Keys"
   - You'll see:
     - **Secret Key** (starts with `sk_test_` for test mode)
     - **Public Key** (starts with `pk_test_` for test mode)
   - Copy both keys

3. **Set Up Webhook** (for payment status updates)
   - Go to Dashboard > Settings > Webhooks
   - Click "Create Webhook"
   - URL: `https://yourdomain.com/payments/paymongo/webhook.php`
   - Events: Select "payment.paid", "payment.failed"
   - Copy the **Webhook Secret** (starts with `whsec_`)

### Step 2: Install PayMongo SDK

**Option A: Using Composer**
```bash
composer require paymongo/paymongo-php
```

**Option B: Manual Installation**
- Download: https://github.com/paymongo/paymongo-php
- Extract to `vendor/paymongo/`

### Step 3: Update Configuration

1. **Update `config/paymongo_config.php`**:
   ```php
   define('PAYMONGO_SECRET_KEY', 'sk_test_YOUR_ACTUAL_KEY');
   define('PAYMONGO_PUBLIC_KEY', 'pk_test_YOUR_ACTUAL_KEY');
   define('PAYMONGO_WEBHOOK_SECRET', 'whsec_YOUR_ACTUAL_SECRET');
   ```

2. **Update Redirect URLs**:
   - In `services/payment_service.php`, update return URLs
   - In PayMongo dashboard, add your domain to allowed origins

### Step 4: Test Payment Flow

1. Use test cards from PayMongo documentation
2. Test successful payment
3. Test failed payment
4. Verify webhook receives events

---

## Part 3: Integration Checklist

### Email Verification
- [ ] Google Cloud project created
- [ ] Gmail API enabled
- [ ] OAuth credentials created
- [ ] credentials.json downloaded to config/
- [ ] Gmail API authorized (token saved)
- [ ] Database table created (tbl_email_verification)
- [ ] Test email sending works
- [ ] Registration flow modified
- [ ] Verification page working

### PayMongo Payments
- [ ] PayMongo account created
- [ ] API keys obtained
- [ ] Webhook configured
- [ ] SDK installed
- [ ] Configuration updated
- [ ] Test payment successful
- [ ] Webhook receiving events
- [ ] Downpayment flow updated
- [ ] Payment verification working

---

## Troubleshooting

### Gmail API Issues
- **"Invalid credentials"**: Check credentials.json path and content
- **"Token expired"**: Re-authorize via callback.php
- **"Permission denied"**: Check OAuth scopes in Google Cloud Console
- **"Email not sending"**: Check Gmail sender email matches OAuth account

### PayMongo Issues
- **"Invalid API key"**: Check secret/public key are correct
- **"Payment failed"**: Check test card numbers and amounts
- **"Webhook not receiving"**: Check webhook URL is publicly accessible
- **"CORS errors"**: Add domain to PayMongo allowed origins

---

## Security Notes

1. **Never commit credentials.json or API keys to Git**
   - Add to `.gitignore`
   - Use environment variables in production

2. **Use HTTPS in production**
   - Required for OAuth callbacks
   - Required for webhooks

3. **Rotate keys regularly**
   - Change API keys if compromised
   - Regenerate OAuth credentials if needed

4. **Monitor API usage**
   - Check Google Cloud quotas
   - Monitor PayMongo transaction limits

