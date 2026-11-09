<?php
/**
 * PayMongo API Configuration — EXAMPLE / TEMPLATE
 *
 * Copy this file to paymongo_config.php and fill in your actual keys.
 *
 * SETUP INSTRUCTIONS:
 * 1. Sign up at https://paymongo.com/
 * 2. Go to Dashboard > Settings > API Keys
 * 3. Copy your Secret Key (starts with sk_test_ or sk_live_)
 * 4. Copy your Public Key (starts with pk_test_ or pk_live_)
 * 5. Copy your Webhook Secret (starts with whsk_)
 * 6. Use test keys for development, live keys for production
 */

// PayMongo API Keys — TEST MODE (for development)
define('PAYMONGO_SECRET_KEY',      'sk_test_xxxxxxxxxxxxxxxxxxxx');  // <-- fill in
define('PAYMONGO_PUBLIC_KEY',      'pk_test_xxxxxxxxxxxxxxxxxxxx');  // <-- fill in
define('PAYMONGO_WEBHOOK_SECRET',  'whsk_xxxxxxxxxxxxxxxxxxxx');     // <-- fill in

// LIVE MODE (for production) — uncomment and fill in when ready
// define('PAYMONGO_SECRET_KEY',     'sk_live_xxxxxxxxxxxxxxxxxxxx');
// define('PAYMONGO_PUBLIC_KEY',     'pk_live_xxxxxxxxxxxxxxxxxxxx');

// PayMongo API Base URL
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Payment settings
define('PAYMONGO_CURRENCY',         'PHP');
define('PAYMONGO_DOWNPAYMENT_RATE', 0.35); // 35% downpayment

/**
 * Check if PayMongo is configured
 */
function isPayMongoConfigured() {
    return !empty(PAYMONGO_SECRET_KEY) &&
           !empty(PAYMONGO_PUBLIC_KEY) &&
           PAYMONGO_SECRET_KEY !== 'sk_test_xxxxxxxxxxxxxxxxxxxx';
}
