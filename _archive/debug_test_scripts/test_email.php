<?php
/**
 * Simple Email Test
 * Tests sending email to sstrkq@gmail.com
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$test_email = 'sstrkq@gmail.com';

try {
    require_once 'config/db.php';
} catch (Exception $e) {
    die("Database config error: " . $e->getMessage());
}

try {
    require_once 'services/email_service.php';
} catch (Exception $e) {
    die("Email service error: " . $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine());
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📧 Email Sending Test</h1>
    <p>Testing email to: <strong><?php echo htmlspecialchars($test_email); ?></strong></p>

<?php
try {
    echo "<div class='info'><h3>Step 1: Checking File Paths</h3></div>";
    $apiclientPath = __DIR__ . '/vendor/google/apiclient';
    echo "<p>API Client Path: <code>$apiclientPath</code> - " . (file_exists($apiclientPath) ? "✓ Exists" : "✗ Missing") . "</p>";
    
    $clientPath = $apiclientPath . '/src/Google/Client.php';
    echo "<p>Client.php: <code>$clientPath</code> - " . (file_exists($clientPath) ? "✓ Exists" : "✗ Missing") . "</p>";
    
    $credentialsPath = __DIR__ . '/config/credentials.json';
    echo "<p>credentials.json: <code>$credentialsPath</code> - " . (file_exists($credentialsPath) ? "✓ Exists" : "✗ Missing") . "</p>";
    
    $tokenPath = __DIR__ . '/config/gmail_token.json';
    echo "<p>gmail_token.json: <code>$tokenPath</code> - " . (file_exists($tokenPath) ? "✓ Exists" : "✗ Missing") . "</p>";
    
    echo "<div class='info'><h3>Step 2: Initializing EmailService</h3></div>";
    $emailService = new EmailService();
    echo "<div class='success'>✓ EmailService initialized</div>";
    
    echo "<div class='info'><h3>Step 3: Generating Verification Code</h3></div>";
    $code = $emailService->generateVerificationCode();
    echo "<p>Code: <strong>$code</strong></p>";
    
    echo "<div class='info'><h3>Step 4: Saving Code to Database</h3></div>";
    $verification_id = $emailService->saveVerificationCode($test_email, $code);
    echo "<div class='success'>✓ Code saved (ID: $verification_id)</div>";
    
    echo "<div class='info'><h3>Step 5: Sending Email</h3></div>";
    $emailService->sendVerificationEmail($test_email, $code);
    
    echo "<div class='success'>";
    echo "<h2>✅ SUCCESS!</h2>";
    echo "<p><strong>Email sent successfully to: $test_email</strong></p>";
    echo "<p>Verification code: <strong>$code</strong></p>";
    echo "<p>Please check your inbox (and spam folder).</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ ERROR</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>

</body>
</html>

