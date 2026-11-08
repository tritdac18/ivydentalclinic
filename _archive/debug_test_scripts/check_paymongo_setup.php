<?php
/**
 * Check PayMongo Setup
 * Verifies PayMongo configuration
 */

require_once 'config/paymongo_config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>PayMongo Setup Check</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>💳 PayMongo Setup Check</h1>

<?php
$allChecksPassed = true;

// Check 1: Configuration
echo "<h2>1. Configuration Check</h2>";
if (isPayMongoConfigured()) {
    echo "<div class='success'>✓ PayMongo is configured</div>";
    
    // Check if test or live keys
    if (strpos(PAYMONGO_SECRET_KEY, 'sk_test_') === 0) {
        echo "<div class='info'>⚠ Using TEST keys (for development)</div>";
    } elseif (strpos(PAYMONGO_SECRET_KEY, 'sk_live_') === 0) {
        echo "<div class='success'>✓ Using LIVE keys (for production)</div>";
    } else {
        echo "<div class='error'>✗ Invalid secret key format</div>";
        $allChecksPassed = false;
    }
    
    if (strpos(PAYMONGO_PUBLIC_KEY, 'pk_test_') === 0) {
        echo "<div class='info'>⚠ Using TEST public key</div>";
    } elseif (strpos(PAYMONGO_PUBLIC_KEY, 'pk_live_') === 0) {
        echo "<div class='success'>✓ Using LIVE public key</div>";
    } else {
        echo "<div class='error'>✗ Invalid public key format</div>";
        $allChecksPassed = false;
    }
} else {
    echo "<div class='error'>✗ PayMongo is NOT configured</div>";
    echo "<div class='info'>💡 Update API keys in <code>config/paymongo_config.php</code></div>";
    $allChecksPassed = false;
}

// Check 2: Payment Service
echo "<h2>2. Payment Service Check</h2>";
try {
    require_once 'services/payment_service.php';
    $paymentService = new PaymentService();
    echo "<div class='success'>✓ PaymentService created successfully</div>";
} catch (Exception $e) {
    echo "<div class='error'>✗ Failed to create PaymentService: " . htmlspecialchars($e->getMessage()) . "</div>";
    $allChecksPassed = false;
}

// Check 3: Webhook Secret
echo "<h2>3. Webhook Configuration</h2>";
if (!empty(PAYMONGO_WEBHOOK_SECRET) && PAYMONGO_WEBHOOK_SECRET !== 'whsec_xxxxxxxxxxxxxxxxxxxxx') {
    echo "<div class='success'>✓ Webhook secret is configured</div>";
} else {
    echo "<div class='warning'>⚠ Webhook secret not configured</div>";
    echo "<div class='info'>💡 Get webhook secret from PayMongo Dashboard > Webhooks</div>";
}

// Check 4: Required Files
echo "<h2>4. Required Files Check</h2>";
$requiredFiles = [
    'services/payment_service.php' => 'Payment Service',
    'process_downpayment_paymongo.php' => 'Downpayment Handler',
    'payments/paymongo/webhook.php' => 'Webhook Handler',
    'payments/paymongo/return.php' => 'Return Page',
];

foreach ($requiredFiles as $file => $name) {
    if (file_exists($file)) {
        echo "<div class='success'>✓ {$name} exists</div>";
    } else {
        echo "<div class='error'>✗ {$name} NOT FOUND: <code>{$file}</code></div>";
        $allChecksPassed = false;
    }
}

// Summary
echo "<h2>📋 Summary</h2>";
if ($allChecksPassed) {
    echo "<div class='success'><h3>✅ Setup Complete!</h3><p>PayMongo is ready to use.</p></div>";
} else {
    echo "<div class='error'><h3>❌ Setup Incomplete</h3><p>Please fix the issues above.</p></div>";
}
?>

</body>
</html>

