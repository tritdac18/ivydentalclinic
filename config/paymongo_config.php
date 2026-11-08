<?php
/**
 * PayMongo API Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Sign up at https://paymongo.com/
 * 2. Go to Dashboard > Settings > API Keys
 * 3. Copy your Secret Key (starts with sk_)
 * 4. Copy your Public Key (starts with pk_)
 * 5. Update the keys below
 * 6. Use test keys for development, live keys for production
 */

// PayMongo API Keys
// TEST MODE (for development)
define('PAYMONGO_SECRET_KEY', 'sk_test_oPm3QY1v7Y78sjtoz1GfBmbN'); // UPDATE THIS
define('PAYMONGO_PUBLIC_KEY', 'pk_test_3EWEiGWpzDSCZbzWLfCNnBGm');

// Webhook Secret Key (for verifying webhook signatures)
define('PAYMONGO_WEBHOOK_SECRET', 'whsk_PyB3zEzA7HpBcuvKjaBaoMNf');

// LIVE MODE (for production) - uncomment when ready
// define('PAYMONGO_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxxxxxx');
// define('PAYMONGO_PUBLIC_KEY', 'pk_live_xxxxxxxxxxxxxxxxxxxxx');

// PayMongo API Base URL
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Payment settings
define('PAYMONGO_CURRENCY', 'PHP');
define('PAYMONGO_DOWNPAYMENT_RATE', 0.35); // 35% downpayment

/**
 * Check if PayMongo is configured
 */
function isPayMongoConfigured() {
    return !empty(PAYMONGO_SECRET_KEY) && 
           !empty(PAYMONGO_PUBLIC_KEY) && 
           PAYMONGO_SECRET_KEY !== 'sk_test_xxxxxxxxxxxxxxxxxxxxx';
}

