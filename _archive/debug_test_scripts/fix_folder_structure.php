<?php
/**
 * Fix Google API Client Folder Structure
 * This script will check and help fix the folder structure
 */

echo "<h2>🔧 Fix Google API Client Folder Structure</h2>\n";
echo "<pre>\n";

$basePath = __DIR__ . '/vendor/google/apiclient';
$srcPath = $basePath . '/src';

echo "Checking current structure...\n";
echo str_repeat("=", 60) . "\n\n";

if (!is_dir($srcPath)) {
    echo "❌ src/ folder does not exist!\n";
    exit(1);
}

echo "✅ src/ folder exists\n\n";

// List what's in src/
echo "📁 Contents of src/ folder:\n";
$srcContents = scandir($srcPath);
$hasGoogleFolder = false;
$hasClientDirectly = false;
$filesInSrc = [];
$foldersInSrc = [];

foreach ($srcContents as $item) {
    if ($item !== '.' && $item !== '..') {
        $itemPath = $srcPath . '/' . $item;
        if (is_dir($itemPath)) {
            $foldersInSrc[] = $item;
            if ($item === 'Google') {
                $hasGoogleFolder = true;
            }
        } else {
            $filesInSrc[] = $item;
            if ($item === 'Client.php') {
                $hasClientDirectly = true;
            }
        }
    }
}

// Show structure
foreach ($foldersInSrc as $folder) {
    echo "   [DIR] $folder/\n";
}
foreach ($filesInSrc as $file) {
    echo "   [FILE] $file\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "ANALYSIS:\n";
echo str_repeat("=", 60) . "\n\n";

if ($hasGoogleFolder) {
    echo "✅ Google/ folder exists\n";
    $googlePath = $srcPath . '/Google';
    $googleContents = scandir($googlePath);
    echo "   Contents of Google/ folder:\n";
    foreach ($googleContents as $item) {
        if ($item !== '.' && $item !== '..') {
            $itemPath = $googlePath . '/' . $item;
            $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
            echo "      $type $item\n";
        }
    }
    
    $clientPath = $googlePath . '/Client.php';
    if (file_exists($clientPath)) {
        echo "\n   ✅ Client.php found in Google/ folder - Structure is CORRECT!\n";
    } else {
        echo "\n   ⚠️  Client.php not found in Google/ folder\n";
    }
} else {
    echo "❌ Google/ folder is MISSING!\n\n";
    
    if ($hasClientDirectly) {
        echo "⚠️  PROBLEM DETECTED:\n";
        echo "   Client.php exists directly in src/ folder\n";
        echo "   But it needs to be in src/Google/ folder for namespace to work\n\n";
        
        echo "🔧 SOLUTION:\n";
        echo "   You need to:\n";
        echo "   1. Create folder: src/Google/\n";
        echo "   2. Move Client.php to: src/Google/Client.php\n";
        echo "   3. Move other files/folders as needed\n\n";
        
        echo "   Expected structure:\n";
        echo "   src/\n";
        echo "   └── Google/\n";
        echo "       ├── Client.php\n";
        echo "       ├── Service/\n";
        echo "       └── ... (other Google namespace files)\n\n";
        
        // Check if we can see what other files need to be moved
        if (count($filesInSrc) > 0 || count($foldersInSrc) > 0) {
            echo "   Files/folders that might need to be moved to Google/:\n";
            foreach ($filesInSrc as $file) {
                if (strpos($file, '.php') !== false) {
                    echo "      - $file (might belong in Google/)\n";
                }
            }
            foreach ($foldersInSrc as $folder) {
                if ($folder !== 'Google') {
                    echo "      - $folder/ (might belong in Google/)\n";
                }
            }
        }
    } else {
        echo "❌ Client.php not found anywhere!\n";
        echo "   The library files may be missing or in wrong location.\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "RECOMMENDED ACTION:\n";
echo str_repeat("=", 60) . "\n\n";

if (!$hasGoogleFolder) {
    echo "1. Re-extract the ZIP file from GitHub\n";
    echo "2. Make sure the extracted folder has: src/Google/Client.php\n";
    echo "3. Copy ALL contents to: vendor/google/apiclient/\n";
    echo "4. Verify structure: vendor/google/apiclient/src/Google/Client.php exists\n\n";
    
    echo "OR use Composer (easiest):\n";
    echo "   composer require google/apiclient\n";
} else {
    echo "✅ Folder structure looks correct!\n";
    echo "   The library should work properly.\n";
}

echo "\n</pre>\n";

