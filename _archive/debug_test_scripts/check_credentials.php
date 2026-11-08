<?php
/**
 * Check credentials.json structure and validity
 */

require_once __DIR__ . '/config/gmail_config.php';

echo "<h2>🔍 Credentials.json Diagnostic</h2>";
echo "<pre>\n";

$credentialsPath = GMAIL_CREDENTIALS_PATH;

if (!file_exists($credentialsPath)) {
    echo "❌ credentials.json NOT FOUND at: $credentialsPath\n";
    echo "\n";
    echo "📋 SOLUTION:\n";
    echo "1. Go to https://console.cloud.google.com/\n";
    echo "2. Select your project\n";
    echo "3. Go to APIs & Services > Credentials\n";
    echo "4. Click on your OAuth 2.0 Client ID\n";
    echo "5. Click 'Download JSON'\n";
    echo "6. Save it as: config/credentials.json\n";
    exit;
}

echo "✅ credentials.json found at: $credentialsPath\n\n";

// Read and validate JSON
$jsonContent = file_get_contents($credentialsPath);
$credentials = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ INVALID JSON: " . json_last_error_msg() . "\n";
    echo "\n";
    echo "📋 SOLUTION: The file is corrupted. Download a fresh copy from Google Cloud Console.\n";
    exit;
}

echo "✅ JSON is valid\n\n";

// Check structure
echo "📋 Checking credentials structure...\n\n";

$hasInstalled = isset($credentials['installed']);
$hasWeb = isset($credentials['web']);

if ($hasInstalled) {
    echo "✅ Found 'installed' key (Desktop app type)\n";
    echo "   Client ID: " . (isset($credentials['installed']['client_id']) ? substr($credentials['installed']['client_id'], 0, 30) . '...' : 'MISSING') . "\n";
    echo "   Client Secret: " . (isset($credentials['installed']['client_secret']) ? '***' . substr($credentials['installed']['client_secret'], -4) : 'MISSING') . "\n";
    echo "   Redirect URIs: " . (isset($credentials['installed']['redirect_uris']) ? count($credentials['installed']['redirect_uris']) . ' URI(s)' : 'MISSING') . "\n";
    echo "\n";
}

if ($hasWeb) {
    echo "✅ Found 'web' key (Web application type)\n";
    echo "   Client ID: " . (isset($credentials['web']['client_id']) ? substr($credentials['web']['client_id'], 0, 30) . '...' : 'MISSING') . "\n";
    echo "   Client Secret: " . (isset($credentials['web']['client_secret']) ? '***' . substr($credentials['web']['client_secret'], -4) : 'MISSING') . "\n";
    echo "   Redirect URIs: " . (isset($credentials['web']['redirect_uris']) ? count($credentials['web']['redirect_uris']) . ' URI(s)' : 'MISSING') . "\n";
    if (isset($credentials['web']['redirect_uris'])) {
        foreach ($credentials['web']['redirect_uris'] as $uri) {
            echo "      - $uri\n";
        }
    }
    echo "\n";
}

if (!$hasInstalled && !$hasWeb) {
    echo "❌ INVALID STRUCTURE: Neither 'installed' nor 'web' key found\n";
    echo "\n";
    echo "📋 SOLUTION: This credentials.json is not a valid OAuth 2.0 credential file.\n";
    echo "   Download a fresh OAuth 2.0 Client ID credential from Google Cloud Console.\n";
    echo "\n";
    echo "📄 Current file structure:\n";
    echo "   Top-level keys: " . implode(', ', array_keys($credentials)) . "\n";
    exit;
}

// Check if it matches what the code expects
echo "🔍 Compatibility Check:\n\n";

if ($hasInstalled && isset($credentials['installed']['client_id'])) {
    echo "✅ Compatible with current code (expects 'installed' type)\n";
    echo "   The test should pass.\n";
} elseif ($hasWeb && isset($credentials['web']['client_id'])) {
    echo "⚠️  WARNING: Credentials are 'web' type but code expects 'installed' type\n";
    echo "\n";
    echo "📋 SOLUTION OPTIONS:\n";
    echo "\n";
    echo "Option 1 (Recommended): Update code to support 'web' type\n";
    echo "   The email_service.php needs to be updated to check for 'web' key.\n";
    echo "\n";
    echo "Option 2: Create new 'Desktop app' credential\n";
    echo "   1. Go to Google Cloud Console > Credentials\n";
    echo "   2. Create new OAuth 2.0 Client ID\n";
    echo "   3. Select 'Desktop app' as application type\n";
    echo "   4. Download and replace credentials.json\n";
    echo "\n";
    echo "Option 3: Convert 'web' to 'installed' structure\n";
    echo "   (Not recommended - may cause issues)\n";
} else {
    echo "❌ Missing required fields (client_id)\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

if ($hasInstalled && isset($credentials['installed']['client_id'])) {
    echo "✅ credentials.json is VALID and COMPATIBLE\n";
    echo "   The issue might be elsewhere. Check:\n";
    echo "   1. File permissions (should be readable)\n";
    echo "   2. File encoding (should be UTF-8)\n";
    echo "   3. Try re-downloading from Google Cloud Console\n";
} elseif ($hasWeb && isset($credentials['web']['client_id'])) {
    echo "⚠️  credentials.json is VALID but INCOMPATIBLE TYPE\n";
    echo "   You need to either:\n";
    echo "   - Update the code to support 'web' type credentials\n";
    echo "   - Create a new 'Desktop app' credential\n";
} else {
    echo "❌ credentials.json is INVALID\n";
    echo "   Please download a fresh copy from Google Cloud Console\n";
}

echo "\n</pre>\n";

