<?php
/**
 * Quick Check: Verify Required Folders Exist
 */

echo "<h2>📁 Google API Client Folder Check</h2>\n";
echo "<pre>\n";

$basePath = __DIR__ . '/vendor/google/apiclient';

echo "Checking: vendor/google/apiclient/\n";
echo str_repeat("=", 60) . "\n\n";

if (!is_dir($basePath)) {
    echo "❌ vendor/google/apiclient/ folder does NOT exist!\n";
    exit(1);
}

echo "✅ Found: vendor/google/apiclient/\n\n";

// List what's there
echo "📁 Current contents:\n";
$contents = scandir($basePath);
$hasSrc = false;
$hasVendor = false;

foreach ($contents as $item) {
    if ($item !== '.' && $item !== '..') {
        $itemPath = $basePath . '/' . $item;
        $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
        echo "   $type $item\n";
        
        if ($item === 'src') {
            $hasSrc = true;
        }
        if ($item === 'vendor') {
            $hasVendor = true;
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "REQUIRED FOLDERS CHECK:\n";
echo str_repeat("=", 60) . "\n\n";

// Check src folder
if ($hasSrc) {
    echo "✅ src/ folder exists\n";
    
    // Check multiple possible locations
    $possiblePaths = [
        $basePath . '/src/Google/Client.php',  // Standard location
        $basePath . '/src/Client.php',         // Alternative location (what user mentioned)
    ];
    
    $clientFound = false;
    $foundPath = '';
    
    foreach ($possiblePaths as $clientPath) {
        if (file_exists($clientPath)) {
            $clientFound = true;
            $foundPath = $clientPath;
            break;
        }
    }
    
    if ($clientFound) {
        echo "   ✅ Client.php found at: $foundPath\n";
        
        // Check if Google folder exists
        $googleFolder = $basePath . '/src/Google';
        if (is_dir($googleFolder)) {
            echo "   ✅ Google/ folder exists\n";
        } else {
            echo "   ⚠️  Google/ folder not found - files may be in different structure\n";
        }
    } else {
        echo "   ❌ Client.php NOT found in expected locations\n";
        echo "   Checked:\n";
        foreach ($possiblePaths as $path) {
            echo "     - $path\n";
        }
        
        // List what's actually in src/
        $srcPath = $basePath . '/src';
        if (is_dir($srcPath)) {
            echo "\n   📁 Actual contents of src/ folder:\n";
            $srcContents = scandir($srcPath);
            foreach ($srcContents as $item) {
                if ($item !== '.' && $item !== '..') {
                    $itemPath = $srcPath . '/' . $item;
                    $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
                    echo "      $type $item\n";
                }
            }
        }
    }
} else {
    echo "❌ src/ folder is MISSING!\n";
    echo "   ⚠️  CRITICAL: You need to restore this folder!\n";
    echo "   This contains all the library code (Client.php, etc.)\n";
}

// Check vendor folder
if ($hasVendor) {
    echo "\n✅ vendor/ folder exists (dependencies)\n";
    $autoloadPath = $basePath . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        echo "   ✅ autoload.php found\n";
    } else {
        echo "   ⚠️  autoload.php NOT found (vendor folder may be incomplete)\n";
    }
} else {
    echo "\n❌ vendor/ folder is MISSING!\n";
    echo "   ⚠️  CRITICAL: You need to restore this folder!\n";
    echo "   This contains all dependencies (Guzzle, etc.)\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "SUMMARY:\n";
echo str_repeat("=", 60) . "\n\n";

if ($hasSrc && $hasVendor) {
    echo "✅ All required folders are present!\n";
    echo "   The library should work correctly.\n";
} else {
    echo "❌ Missing required folders!\n\n";
    echo "ACTION REQUIRED:\n";
    echo "1. Re-extract the ZIP file from GitHub\n";
    echo "2. Copy src/ folder to: vendor/google/apiclient/src/\n";
    echo "3. Copy vendor/ folder to: vendor/google/apiclient/vendor/\n";
    echo "\nOR use Composer:\n";
    echo "   composer require google/apiclient\n";
}

echo "\n</pre>\n";

