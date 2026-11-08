<?php
/**
 * Detailed Gmail API Client Test Script
 * This will show you exactly what's in your vendor folder
 */

echo "<h2>🔍 Detailed Gmail API Client Installation Test</h2>\n";
echo "<pre>\n";

// Check the exact structure
$basePath = __DIR__ . '/vendor/google/apiclient';

echo "Checking folder structure...\n";
echo str_repeat("=", 60) . "\n\n";

if (!is_dir($basePath)) {
    echo "❌ vendor/google/apiclient/ folder does NOT exist!\n";
    echo "\nExpected structure:\n";
    echo "vendor/\n";
    echo "└── google/\n";
    echo "    └── apiclient/\n";
    echo "        ├── src/\n";
    echo "        │   └── Google/\n";
    echo "        │       ├── Client.php\n";
    echo "        │       └── Service/\n";
    echo "        └── vendor/ (dependencies)\n";
    exit(1);
}

echo "✅ Found: vendor/google/apiclient/\n\n";

// List everything in apiclient folder
echo "📁 Contents of vendor/google/apiclient/:\n";
$apiclientContents = scandir($basePath);
foreach ($apiclientContents as $item) {
    if ($item !== '.' && $item !== '..') {
        $itemPath = $basePath . '/' . $item;
        $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
        $size = is_file($itemPath) ? ' (' . number_format(filesize($itemPath)) . ' bytes)' : '';
        echo "   $type $item$size\n";
    }
}

// Check src folder
echo "\n📁 Checking vendor/google/apiclient/src/:\n";
$srcPath = $basePath . '/src';
if (is_dir($srcPath)) {
    echo "   ✅ src/ folder exists\n";
    
    $srcContents = scandir($srcPath);
    echo "   Contents:\n";
    foreach ($srcContents as $item) {
        if ($item !== '.' && $item !== '..') {
            $itemPath = $srcPath . '/' . $item;
            $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
            echo "      $type $item\n";
        }
    }
    
    // Check for Google folder
    $googlePath = $srcPath . '/Google';
    if (is_dir($googlePath)) {
        echo "\n   ✅ Found: src/Google/ folder\n";
        echo "   Contents:\n";
        $googleContents = scandir($googlePath);
        foreach ($googleContents as $item) {
            if ($item !== '.' && $item !== '..') {
                $itemPath = $googlePath . '/' . $item;
                $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
                echo "      $type $item\n";
            }
        }
        
        // Check for Client.php
        $clientPath = $googlePath . '/Client.php';
        echo "\n   🔍 Looking for Client.php...\n";
        if (file_exists($clientPath)) {
            echo "   ✅ FOUND: " . $clientPath . "\n";
            echo "   File size: " . number_format(filesize($clientPath)) . " bytes\n";
        } else {
            echo "   ❌ NOT FOUND: " . $clientPath . "\n";
        }
        
        // Check for Service folder
        $servicePath = $googlePath . '/Service';
        if (is_dir($servicePath)) {
            echo "\n   ✅ Found: src/Google/Service/ folder\n";
            $serviceContents = scandir($servicePath);
            echo "   Contents:\n";
            foreach ($serviceContents as $item) {
                if ($item !== '.' && $item !== '..') {
                    $itemPath = $servicePath . '/' . $item;
                    $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
                    echo "      $type $item\n";
                }
            }
            
            // Check for Gmail.php
            $gmailPath = $servicePath . '/Gmail.php';
            if (file_exists($gmailPath)) {
                echo "\n   ✅ FOUND: Gmail.php\n";
            } else {
                echo "\n   ❌ NOT FOUND: Gmail.php\n";
            }
        } else {
            echo "\n   ❌ NOT FOUND: src/Google/Service/ folder\n";
        }
    } else {
        echo "\n   ❌ NOT FOUND: src/Google/ folder\n";
        echo "   This is a problem! The Google folder should be inside src/\n";
    }
} else {
    echo "   ❌ src/ folder does NOT exist!\n";
}

// Check vendor folder (dependencies)
echo "\n📁 Checking vendor/google/apiclient/vendor/ (dependencies):\n";
$vendorPath = $basePath . '/vendor';
if (is_dir($vendorPath)) {
    echo "   ✅ vendor/ folder exists (dependencies)\n";
    
    $vendorAutoload = $vendorPath . '/autoload.php';
    if (file_exists($vendorAutoload)) {
        echo "   ✅ FOUND: vendor/autoload.php (dependencies autoloader)\n";
    } else {
        echo "   ⚠️  NOT FOUND: vendor/autoload.php\n";
        echo "   Dependencies may not be properly set up\n";
    }
    
    // List some contents
    $vendorContents = scandir($vendorPath);
    $count = 0;
    echo "   Sample contents (first 10 items):\n";
    foreach ($vendorContents as $item) {
        if ($item !== '.' && $item !== '..' && $count < 10) {
            $itemPath = $vendorPath . '/' . $item;
            $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
            echo "      $type $item\n";
            $count++;
        }
    }
    if (count($vendorContents) > 12) {
        echo "      ... and " . (count($vendorContents) - 12) . " more items\n";
    }
} else {
    echo "   ⚠️  vendor/ folder does NOT exist\n";
    echo "   Dependencies may be missing\n";
}

// Try to load if found
echo "\n" . str_repeat("=", 60) . "\n";
echo "ATTEMPTING TO LOAD...\n";
echo str_repeat("=", 60) . "\n\n";

$clientPath = $basePath . '/src/Google/Client.php';
if (file_exists($clientPath)) {
    echo "✅ Client.php found, attempting to load...\n";
    try {
        require_once $clientPath;
        echo "✅ Client.php loaded successfully!\n";
        
        // Try to load dependencies
        $vendorAutoload = $basePath . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
            echo "✅ Dependencies autoloader loaded!\n";
        }
        
        // Try to instantiate
        if (class_exists('Google\Client')) {
            $client = new Google\Client();
            echo "✅ Google\\Client instantiated successfully!\n";
            echo "\n🎉 SUCCESS! The library is installed correctly!\n";
        } else {
            echo "❌ Google\\Client class not found after loading file\n";
            echo "   This may be a namespace or dependency issue\n";
        }
    } catch (Exception $e) {
        echo "❌ Error loading: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "❌ Fatal error: " . $e->getMessage() . "\n";
        echo "   This usually means a required dependency is missing\n";
    }
} else {
    echo "❌ Cannot load - Client.php not found at expected location\n";
    echo "   Expected: $clientPath\n";
}

echo "\n</pre>\n";

