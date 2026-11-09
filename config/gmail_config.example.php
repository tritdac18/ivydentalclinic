<?php
/**
 * Gmail API Configuration — EXAMPLE / TEMPLATE
 *
 * Copy this file to gmail_config.php and fill in your actual values.
 *
 * SETUP INSTRUCTIONS:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project
 * 3. Enable Gmail API
 * 4. Create OAuth 2.0 credentials (Web application)
 * 5. Download credentials.json and place it in config/ folder
 * 6. Update the values below to match your setup
 */

// Path to credentials.json from Google Cloud Console
define('GMAIL_CREDENTIALS_PATH', __DIR__ . '/credentials.json');

// Path to store access token
define('GMAIL_TOKEN_PATH', __DIR__ . '/gmail_token.json');

// Gmail account to send emails from (must match OAuth account)
define('GMAIL_SENDER_EMAIL', 'your_gmail_address@gmail.com');  // <-- fill in
define('GMAIL_SENDER_NAME',  'Your Clinic Name');               // <-- fill in

// OAuth redirect URI (must match Google Cloud Console settings exactly)
define('GMAIL_REDIRECT_URI', 'https://your-domain.com/auth/google/callback.php'); // <-- fill in

// Gmail API Scopes
define('GMAIL_SCOPE', 'https://www.googleapis.com/auth/gmail.send');

// Verification code settings
define('VERIFICATION_CODE_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRY_MINUTES', 15);

/**
 * Check if Gmail API is configured
 */
function isGmailConfigured() {
    return file_exists(GMAIL_CREDENTIALS_PATH);
}
