<?php
/**
 * Verify Composer Setup
 * Run this after uploading files to hosting to check if everything is correct
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Composer Setup Verification</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h2 { color: #136dec; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Composer Setup Verification</h1>
    <p>This script checks if Composer is properly set up for Gmail API.</p>

<?php
$allChecksPassed = true;

// Check 1: composer.json
echo "<h2>1. Checking composer.json</h2>";
$composerJson = __DIR__ . '/composer.json';
if (file_exists($composerJson)) {
    echo "<div class='success'>✓ composer.json exists</div>";
    $composerData = json_decode(file_get_contents($composerJson), true);
    if (isset($composerData['require']['google/apiclient'])) {
        echo "<div class='success'>✓ google/apiclient is listed in dependencies</div>";
    } else {
        echo "<div class='error'>✗ google/apiclient is NOT in dependencies</div>";
        $allChecksPassed = false;
    }
} else {
    echo "<div class='error'>✗ composer.json NOT FOUND</div>";
    echo "<div class='info'>💡 Run: <code>composer require google/apiclient</code> in your project root</div>";
    $allChecksPassed = false;
}

// Check 2: vendor/autoload.php
echo "<h2>2. Checking vendor/autoload.php</h2>";
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "<div class='success'>✓ vendor/autoload.php exists</div>";
    
    // Try to load it
    try {
        require_once $autoloadPath;
        echo "<div class='success'>✓ vendor/autoload.php loaded successfully</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Failed to load vendor/autoload.php: " . htmlspecialchars($e->getMessage()) . "</div>";
        $allChecksPassed = false;
    }
} else {
    echo "<div class='error'>✗ vendor/autoload.php NOT FOUND</div>";
    echo "<div class='info'>💡 Run: <code>composer install</code> or <code>composer require google/apiclient</code></div>";
    $allChecksPassed = false;
}

// Check 3: Google API Client
echo "<h2>3. Checking Google API Client</h2>";
$googleApiPath = __DIR__ . '/vendor/google/apiclient';
if (file_exists($googleApiPath)) {
    echo "<div class='success'>✓ vendor/google/apiclient/ exists</div>";
    
    // Check correct path: src/Client.php (not src/Google/Client.php)
    $clientPath = $googleApiPath . '/src/Client.php';
    if (file_exists($clientPath)) {
        echo "<div class='success'>✓ Client.php exists at src/Client.php</div>";
    } else {
        // Also check old path for backward compatibility
        $oldClientPath = $googleApiPath . '/src/Google/Client.php';
        if (file_exists($oldClientPath)) {
            echo "<div class='success'>✓ Client.php exists at src/Google/Client.php (old structure)</div>";
        } else {
            echo "<div class='error'>✗ Client.php NOT FOUND</div>";
            echo "<div class='info'>Checked paths:<br>";
            echo "- <code>" . htmlspecialchars($clientPath) . "</code><br>";
            echo "- <code>" . htmlspecialchars($oldClientPath) . "</code></div>";
            $allChecksPassed = false;
        }
    }
} else {
    echo "<div class='error'>✗ vendor/google/apiclient/ NOT FOUND</div>";
    echo "<div class='info'>💡 Run: <code>composer require google/apiclient</code></div>";
    $allChecksPassed = false;
}

// Check 4: Try to load Google Client class
echo "<h2>4. Testing Google Client Class</h2>";
if (file_exists($autoloadPath)) {
    try {
        if (class_exists('Google\Client')) {
            echo "<div class='success'>✓ Google\\Client class is available</div>";
        } else {
            echo "<div class='error'>✗ Google\\Client class NOT FOUND</div>";
            $allChecksPassed = false;
        }
        
        if (class_exists('Google\Service\Gmail')) {
            echo "<div class='success'>✓ Google\\Service\\Gmail class is available</div>";
        } else {
            echo "<div class='warning'>⚠ Google\\Service\\Gmail class not found (may be generated dynamically)</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error checking classes: " . htmlspecialchars($e->getMessage()) . "</div>";
        $allChecksPassed = false;
    }
} else {
    echo "<div class='warning'>⚠ Skipped (vendor/autoload.php not found)</div>";
}

// Check 5: Gmail Configuration
echo "<h2>5. Checking Gmail Configuration</h2>";
$credentialsPath = __DIR__ . '/config/credentials.json';
$tokenPath = __DIR__ . '/config/gmail_token.json';

if (file_exists($credentialsPath)) {
    echo "<div class='success'>✓ credentials.json exists</div>";
} else {
    echo "<div class='error'>✗ credentials.json NOT FOUND</div>";
    echo "<div class='info'>💡 Download from Google Cloud Console and place in config/ folder</div>";
}

if (file_exists($tokenPath)) {
    echo "<div class='success'>✓ gmail_token.json exists (Gmail API is authorized)</div>";
} else {
    echo "<div class='warning'>⚠ gmail_token.json NOT FOUND (Gmail API not yet authorized)</div>";
    echo "<div class='info'>💡 Visit: <a href='auth/google/setup.php'>auth/google/setup.php</a> to authorize</div>";
}

// Check 6: Test EmailService
echo "<h2>6. Testing EmailService</h2>";
if ($allChecksPassed) {
    try {
        require_once __DIR__ . '/services/email_service.php';
        $emailService = new EmailService();
        echo "<div class='success'>✓ EmailService created successfully!</div>";
        echo "<div class='success'>✅ All checks passed! Gmail API is ready to use.</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Failed to create EmailService: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='info'><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
        
        if (strpos($e->getMessage(), 'authorization required') !== false) {
            echo "<div class='info'>💡 SOLUTION: Visit <a href='auth/google/setup.php'>auth/google/setup.php</a> to authorize Gmail API</div>";
        }
    }
} else {
    echo "<div class='warning'>⚠ Skipped (some checks failed above)</div>";
}

// Summary
echo "<h2>📋 Summary</h2>";
if ($allChecksPassed) {
    echo "<div class='success'><h3>✅ Setup Complete!</h3><p>Your Composer setup is correct. You can now use Gmail API.</p></div>";
} else {
    echo "<div class='error'><h3>❌ Setup Incomplete</h3><p>Please fix the issues above before proceeding.</p></div>";
    echo "<div class='info'><h4>Quick Fix Commands:</h4><pre>composer require google/apiclient\ncomposer install</pre></div>";
}
?>

</body>
</html>

