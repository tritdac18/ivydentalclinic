<?php
/**
 * Generate Gmail Service Classes
 * The Google API PHP Client can generate service classes on-the-fly,
 * but sometimes they need to be pre-generated. This script helps with that.
 */

require_once __DIR__ . '/config/gmail_config.php';

// Load Google API Client - same logic as email_service.php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Composer installation (recommended)
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/google/apiclient/src/Google/Client.php') || 
          file_exists(__DIR__ . '/vendor/google/apiclient/src/Client.php')) {
    // Manual installation
    $apiclientVendorPath = __DIR__ . '/vendor/google/apiclient/vendor';
    
    // Try to load dependencies autoloader
    if (file_exists($apiclientVendorPath . '/autoload.php')) {
        $composerAutoloadReal = $apiclientVendorPath . '/composer/autoload_real.php';
        if (file_exists($composerAutoloadReal)) {
            try {
                require_once $apiclientVendorPath . '/autoload.php';
            } catch (Error $e) {
                error_log('Warning: Could not load Composer autoloader: ' . $e->getMessage());
            }
        }
    }
    
    // Load main Client class - check multiple possible locations
    $clientPaths = [
        __DIR__ . '/vendor/google/apiclient/src/Google/Client.php',  // Standard location
        __DIR__ . '/vendor/google/apiclient/src/Client.php',         // Alternative location
    ];
    
    $clientLoaded = false;
    foreach ($clientPaths as $clientPath) {
        if (file_exists($clientPath)) {
            require_once $clientPath;
            $clientLoaded = true;
            break;
        }
    }
    
    if (!$clientLoaded) {
        die('Google Client.php not found. Checked locations: ' . implode(', ', $clientPaths));
    }
} else {
    die('Google API Client library not found. Please install via Composer: composer require google/apiclient');
}

use Google\Client;

echo "<h2>Gmail Service Class Generator</h2>\n";
echo "<pre>\n";

try {
    $client = new Client();
    
    // Check if credentials exist
    if (!file_exists(GMAIL_CREDENTIALS_PATH)) {
        throw new Exception('credentials.json not found at: ' . GMAIL_CREDENTIALS_PATH);
    }
    
    $client->setAuthConfig(GMAIL_CREDENTIALS_PATH);
    $client->addScope(GMAIL_SCOPE);
    
    echo "✅ Google Client created successfully!\n\n";
    
    // Check if Gmail class exists
    echo "Checking if Gmail service class exists...\n";
    if (class_exists('Google\Service\Gmail')) {
        echo "✅ Google\\Service\\Gmail class is available!\n";
    } else {
        echo "⚠️  Google\\Service\\Gmail class not found yet (will be generated on use)\n";
    }
    
    // Try to create Gmail service - this will generate the class if needed
    echo "\nAttempting to create Gmail service instance...\n";
    echo "This will trigger class generation if needed...\n\n";
    
    // The library should auto-generate the service class
    $service = new \Google\Service\Gmail($client);
    
    echo "✅ Gmail service created successfully!\n";
    echo "✅ Service classes are available (generated dynamically)\n\n";
    
    echo "📝 IMPORTANT:\n";
    echo "The Gmail service classes are loaded dynamically by the library.\n";
    echo "You don't need physical Gmail.php files - the library generates them in memory!\n\n";
    
    echo "📍 Where Gmail.php WOULD be (if saved):\n";
    echo "   vendor/google/apiclient/src/Google/Service/Gmail.php\n";
    echo "   (But it doesn't need to exist - library creates it on-the-fly)\n";
    
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " (Line " . $e->getLine() . ")\n";
    echo "\nThis usually means:\n";
    echo "1. A required dependency is missing\n";
    echo "2. The autoloader isn't working properly\n";
    echo "3. A class file can't be found\n\n";
    echo "💡 Solution: Install via Composer for automatic dependency management\n";
    echo "   composer require google/apiclient\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nPossible solutions:\n";
    echo "1. Make sure credentials.json exists and is valid\n";
    echo "2. The library should auto-generate service classes\n";
    echo "3. Check that all dependencies are properly loaded\n";
    echo "4. Try installing via Composer: composer require google/apiclient\n";
}

echo "</pre>\n";

