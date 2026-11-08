<?php
/**
 * Gmail API Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project
 * 3. Enable Gmail API
 * 4. Create OAuth 2.0 credentials (Web application)
 * 5. Download credentials.json and place it in config/ folder
 * 6. Update the paths below to match your setup
 */

// Path to credentials.json from Google Cloud Console
define('GMAIL_CREDENTIALS_PATH', __DIR__ . '/credentials.json');

// Path to store access token
define('GMAIL_TOKEN_PATH', __DIR__ . '/gmail_token.json');

// Gmail account to send emails from (must match OAuth account)
define('GMAIL_SENDER_EMAIL', 'ivydentalclinicservice@gmail.com');
define('GMAIL_SENDER_NAME', 'Ivy Dental Clinic');

// OAuth redirect URI (must match Google Cloud Console settings exactly)
// IMPORTANT: This must match EXACTLY what you enter in Google Cloud Console > Credentials > OAuth 2.0 Client ID > Authorized redirect URIs
// For your domain: https://ivydentalbckup.gt.tc/auth/google/callback.php
// For local testing: http://localhost/ivydentalclinic/auth/google/callback.php
define('GMAIL_REDIRECT_URI', 'https://ivydentalbckup.gt.tc/auth/google/callback.php'); // UPDATE THIS to match your domain

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

