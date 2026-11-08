<?php
/**
 * Test Gmail API Functionality
 * This will test if the Gmail API is properly set up and working
 */

require_once __DIR__ . '/config/gmail_config.php';
require_once __DIR__ . '/config/db.php';

echo "<h2>🧪 Gmail API Functionality Test</h2>\n";
echo "<pre>\n";

$allTestsPassed = true;

// Test 1: Check if library loads
echo "Test 1: Loading Google API Client Library...\n";
try {
    require_once __DIR__ . '/services/email_service.php';
    echo "✅ Library loaded successfully!\n\n";
} catch (Exception $e) {
    echo "❌ Failed to load library: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// Test 2: Check configuration
echo "Test 2: Checking Configuration...\n";
if (!file_exists(GMAIL_CREDENTIALS_PATH)) {
    echo "❌ credentials.json not found at: " . GMAIL_CREDENTIALS_PATH . "\n";
    $allTestsPassed = false;
} else {
    echo "✅ credentials.json found\n";
    
    // Validate JSON - support both 'web' and 'installed' types
    $credentials = json_decode(file_get_contents(GMAIL_CREDENTIALS_PATH), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ credentials.json is invalid JSON: " . json_last_error_msg() . "\n";
        $allTestsPassed = false;
    } elseif ($credentials && (isset($credentials['installed']['client_id']) || isset($credentials['web']['client_id']))) {
        $type = isset($credentials['installed']['client_id']) ? 'installed' : 'web';
        echo "✅ credentials.json is valid ($type type)\n";
    } else {
        echo "❌ credentials.json is invalid or corrupted\n";
        echo "   Expected 'installed' or 'web' key with 'client_id'\n";
        $allTestsPassed = false;
    }
}

if (!file_exists(GMAIL_TOKEN_PATH)) {
    echo "⚠️  gmail_token.json not found - Gmail API not yet authorized\n";
    echo "   Visit: auth/google/setup.php to authorize\n";
} else {
    echo "✅ gmail_token.json found (Gmail API is authorized)\n";
}

echo "\n";

// Test 3: Check database table
echo "Test 3: Checking Database Table...\n";
try {
    $conn = getDBConnection();
    $tableCheck = $conn->query("SHOW TABLES LIKE 'tbl_email_verification'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        echo "✅ tbl_email_verification table exists\n";
    } else {
        echo "❌ tbl_email_verification table does NOT exist\n";
        echo "   Run: create_tbl_email_verification.sql\n";
        $allTestsPassed = false;
    }
    $conn->close();
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// Test 4: Try to create EmailService instance
echo "Test 4: Creating EmailService Instance...\n";
try {
    $emailService = new EmailService();
    echo "✅ EmailService created successfully!\n";
    echo "   Gmail API client is configured\n";
    echo "   Gmail service is ready\n\n";
} catch (Exception $e) {
    echo "❌ Failed to create EmailService: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'authorization required') !== false) {
        echo "💡 SOLUTION: Visit auth/google/setup.php to authorize Gmail API\n";
    } elseif (strpos($e->getMessage(), 'credentials') !== false) {
        echo "💡 SOLUTION: Check that credentials.json exists and is valid\n";
    } elseif (strpos($e->getMessage(), 'library not found') !== false) {
        echo "💡 SOLUTION: Make sure Google API Client library is properly installed\n";
    }
    
    $allTestsPassed = false;
}

// Test 5: Test verification code generation
if ($allTestsPassed) {
    echo "Test 5: Testing Verification Code Generation...\n";
    try {
        $testCode = $emailService->generateVerificationCode();
        if (strlen($testCode) === VERIFICATION_CODE_LENGTH && is_numeric($testCode)) {
            echo "✅ Verification code generated: $testCode\n";
            echo "   Code length: " . strlen($testCode) . " digits (correct)\n\n";
        } else {
            echo "❌ Verification code format is incorrect\n";
            $allTestsPassed = false;
        }
    } catch (Exception $e) {
        echo "❌ Error generating code: " . $e->getMessage() . "\n";
        $allTestsPassed = false;
    }
}

// Test 6: Test saving verification code to database
if ($allTestsPassed) {
    echo "Test 6: Testing Database Operations...\n";
    try {
        $testEmail = 'test@example.com';
        $testCode = $emailService->generateVerificationCode();
        $verificationId = $emailService->saveVerificationCode($testEmail, $testCode);
        
        if ($verificationId) {
            echo "✅ Verification code saved to database (ID: $verificationId)\n";
            
            // Test verification
            if ($emailService->verifyCode($testEmail, $testCode)) {
                echo "✅ Code verification works correctly\n";
            } else {
                echo "❌ Code verification failed\n";
                $allTestsPassed = false;
            }
        } else {
            echo "❌ Failed to save verification code\n";
            $allTestsPassed = false;
        }
    } catch (Exception $e) {
        echo "❌ Database operation error: " . $e->getMessage() . "\n";
        $allTestsPassed = false;
    }
}

// Test 7: Test email sending (optional - requires authorization)
if ($allTestsPassed) {
    echo "\nTest 7: Testing Email Sending Capability...\n";
    echo "   (This requires Gmail API to be authorized)\n";
    
    if (file_exists(GMAIL_TOKEN_PATH)) {
        echo "   ✅ Gmail API is authorized\n";
        echo "   📧 Email sending should work\n";
        echo "   💡 To test actual sending, use the registration flow\n";
    } else {
        echo "   ⚠️  Gmail API not yet authorized\n";
        echo "   💡 Visit: auth/google/setup.php to authorize\n";
        echo "   📧 Email sending will work after authorization\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

if ($allTestsPassed) {
    echo "✅ ALL TESTS PASSED!\n\n";
    echo "🎉 Gmail API is properly configured and ready to use!\n\n";
    echo "Next steps:\n";
    echo "1. If not authorized yet: Visit auth/google/setup.php\n";
    echo "2. Test email sending with the registration flow\n";
    echo "3. Verify emails are received correctly\n";
} else {
    echo "❌ SOME TESTS FAILED\n\n";
    echo "Please fix the issues above before proceeding.\n";
    echo "Check the error messages for solutions.\n";
}

echo "\n</pre>\n";

