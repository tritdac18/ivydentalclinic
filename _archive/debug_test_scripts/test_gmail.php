<?php
/**
 * Gmail API Client Test Script
 * Use this to verify that the Google API Client library is installed correctly
 */

echo "<h2>Gmail API Client Installation Test</h2>\n";
echo "<pre>\n";

// Test 1: Check if Composer autoloader exists
echo "Test 1: Checking for Composer autoloader...\n";
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    echo "✅ Found: vendor/autoload.php\n";
    require_once $composerAutoload;
    $installationMethod = 'composer';
} else {
    echo "❌ Not found: vendor/autoload.php\n";
    $installationMethod = 'manual';
}

// Test 2: Check for manual installation
if ($installationMethod === 'manual') {
    echo "\nTest 2: Checking for manual installation...\n";
    
    // Check base folder structure
    $basePath = __DIR__ . '/vendor/google/apiclient';
    if (is_dir($basePath)) {
        echo "✅ Found: vendor/google/apiclient/ folder\n";
        
        // List what's inside
        echo "   Contents:\n";
        $contents = scandir($basePath);
        foreach ($contents as $item) {
            if ($item !== '.' && $item !== '..') {
                $itemPath = $basePath . '/' . $item;
                $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
                echo "   - $type $item\n";
            }
        }
        
        // Check for src folder
        $srcPath = $basePath . '/src';
        if (is_dir($srcPath)) {
            echo "\n   ✅ Found: vendor/google/apiclient/src/ folder\n";
            
            // Check for Google folder inside src
            $googlePath = $srcPath . '/Google';
            if (is_dir($googlePath)) {
                echo "   ✅ Found: vendor/google/apiclient/src/Google/ folder\n";
                
                // Check for Client.php
                $clientPath = $googlePath . '/Client.php';
                if (file_exists($clientPath)) {
                    echo "   ✅ Found: vendor/google/apiclient/src/Google/Client.php\n";
                    require_once $clientPath;
                } else {
                    echo "   ❌ Not found: Client.php inside Google folder\n";
                    echo "   Looking in: $googlePath\n";
                    if (is_dir($googlePath)) {
                        $googleContents = scandir($googlePath);
                        echo "   Contents of Google folder:\n";
                        foreach ($googleContents as $item) {
                            if ($item !== '.' && $item !== '..') {
                                echo "     - $item\n";
                            }
                        }
                    }
                }
            } else {
                echo "   ❌ Not found: vendor/google/apiclient/src/Google/ folder\n";
                echo "   Looking in: $srcPath\n";
                if (is_dir($srcPath)) {
                    $srcContents = scandir($srcPath);
                    echo "   Contents of src folder:\n";
                    foreach ($srcContents as $item) {
                        if ($item !== '.' && $item !== '..') {
                            echo "     - $item\n";
                        }
                    }
                }
            }
        } else {
            echo "   ❌ Not found: vendor/google/apiclient/src/ folder\n";
        }
        
        // Check for vendor folder (dependencies)
        $vendorPath = $basePath . '/vendor';
        if (is_dir($vendorPath)) {
            echo "\n   ✅ Found: vendor/google/apiclient/vendor/ folder (dependencies)\n";
            $vendorAutoload = $vendorPath . '/autoload.php';
            if (file_exists($vendorAutoload)) {
                echo "   ✅ Found dependencies autoloader\n";
                require_once $vendorAutoload;
            } else {
                echo "   ⚠️  Dependencies autoloader not found (may still work)\n";
            }
        } else {
            echo "\n   ⚠️  Not found: vendor/google/apiclient/vendor/ folder\n";
            echo "   Dependencies may be missing\n";
        }
        
        // Try to load Gmail service classes if Client.php was found
        if (class_exists('Google\Client')) {
            $gmailService = $basePath . '/src/Google/Service/Gmail.php';
            if (file_exists($gmailService)) {
                require_once $gmailService;
                echo "   ✅ Loaded: Gmail Service class\n";
            }
            
            $messageClass = $basePath . '/src/Google/Service/Gmail/Message.php';
            if (file_exists($messageClass)) {
                require_once $messageClass;
                echo "   ✅ Loaded: Gmail Message class\n";
            }
        }
    } else {
        echo "❌ Not found: vendor/google/apiclient/ folder\n";
        echo "\n❌ Google API Client library not found!\n";
        echo "\nInstallation options:\n";
        echo "1. Composer: composer require google/apiclient\n";
        echo "2. Manual: Copy extracted folder to vendor/google/apiclient/\n";
        echo "   See MANUAL_INSTALLATION_GUIDE.md for details\n";
        exit(1);
    }
}

// Test 3: Try to instantiate Google Client
echo "\nTest 3: Testing Google Client instantiation...\n";
try {
    $client = new Google\Client();
    echo "✅ Google\\Client instantiated successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Check if Gmail service can be loaded
echo "\nTest 4: Testing Gmail Service...\n";
try {
    if (class_exists('Google\Service\Gmail')) {
        echo "✅ Google\\Service\\Gmail class found!\n";
    } else {
        echo "⚠️  Google\\Service\\Gmail class not found (may need manual loading)\n";
    }
} catch (Exception $e) {
    echo "⚠️  Warning: " . $e->getMessage() . "\n";
}

// Test 5: Check configuration files
echo "\nTest 5: Checking configuration files...\n";
$credentialsPath = __DIR__ . '/config/credentials.json';
if (file_exists($credentialsPath)) {
    echo "✅ Found: config/credentials.json\n";
    
    // Validate JSON
    $credentials = json_decode(file_get_contents($credentialsPath), true);
    if ($credentials && isset($credentials['installed']['client_id'])) {
        echo "✅ credentials.json is valid\n";
        echo "   Client ID: " . substr($credentials['installed']['client_id'], 0, 20) . "...\n";
    } else {
        echo "⚠️  Warning: credentials.json may be invalid\n";
    }
} else {
    echo "❌ Not found: config/credentials.json\n";
    echo "   Download from Google Cloud Console and save to config/credentials.json\n";
}

$tokenPath = __DIR__ . '/config/gmail_token.json';
if (file_exists($tokenPath)) {
    echo "✅ Found: config/gmail_token.json (Gmail API is authorized)\n";
} else {
    echo "⚠️  Not found: config/gmail_token.json (Gmail API not yet authorized)\n";
    echo "   Visit: auth/google/setup.php to authorize\n";
}

// Test 6: Check gmail_config.php
echo "\nTest 6: Checking Gmail configuration...\n";
$configPath = __DIR__ . '/config/gmail_config.php';
if (file_exists($configPath)) {
    echo "✅ Found: config/gmail_config.php\n";
    require_once $configPath;
    
    if (defined('GMAIL_REDIRECT_URI')) {
        echo "   Redirect URI: " . GMAIL_REDIRECT_URI . "\n";
    }
    if (defined('GMAIL_SENDER_EMAIL')) {
        echo "   Sender Email: " . GMAIL_SENDER_EMAIL . "\n";
    }
} else {
    echo "❌ Not found: config/gmail_config.php\n";
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";

$allGood = true;

if (!file_exists($composerAutoload) && !file_exists(__DIR__ . '/vendor/google/apiclient/src/Google/Client.php')) {
    echo "❌ Google API Client library is NOT installed\n";
    $allGood = false;
} else {
    echo "✅ Google API Client library is installed\n";
}

if (!file_exists($credentialsPath)) {
    echo "❌ credentials.json is missing\n";
    $allGood = false;
} else {
    echo "✅ credentials.json is present\n";
}

if ($allGood) {
    echo "\n✅ All checks passed! Gmail API should work.\n";
    echo "\nNext steps:\n";
    echo "1. Visit: auth/google/setup.php to authorize Gmail API\n";
    echo "2. Test email sending with the registration flow\n";
} else {
    echo "\n⚠️  Some issues found. Please fix them before proceeding.\n";
}

echo "</pre>\n";

