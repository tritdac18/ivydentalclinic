<?php
/**
 * Check Gmail Authorization
 * Verifies that ivydentalclinicservice@gmail.com is authorized to send emails
 */

require_once 'config/gmail_config.php';
require_once 'services/email_service.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Gmail Authorization Check</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>📧 Gmail Authorization Check</h1>
    <p>Verifying that <strong><?php echo htmlspecialchars(GMAIL_SENDER_EMAIL); ?></strong> is authorized to send emails.</p>

<?php
try {
    // Check configuration
    echo "<h2>1. Configuration Check</h2>";
    echo "<div class='info'>";
    echo "<p><strong>Sender Email:</strong> " . htmlspecialchars(GMAIL_SENDER_EMAIL) . "</p>";
    echo "<p><strong>Sender Name:</strong> " . htmlspecialchars(GMAIL_SENDER_NAME) . "</p>";
    echo "<p><strong>Credentials Path:</strong> " . htmlspecialchars(GMAIL_CREDENTIALS_PATH) . "</p>";
    echo "<p><strong>Token Path:</strong> " . htmlspecialchars(GMAIL_TOKEN_PATH) . "</p>";
    echo "</div>";
    
    // Check if credentials exist
    if (!file_exists(GMAIL_CREDENTIALS_PATH)) {
        echo "<div class='error'>✗ credentials.json NOT FOUND</div>";
        echo "<div class='info'>💡 Download credentials.json from Google Cloud Console and place it in config/ folder</div>";
        exit;
    } else {
        echo "<div class='success'>✓ credentials.json exists</div>";
    }
    
    // Check if token exists
    if (!file_exists(GMAIL_TOKEN_PATH)) {
        echo "<div class='warning'>⚠ gmail_token.json NOT FOUND - Authorization required</div>";
        echo "<div class='info'>💡 Visit: <a href='auth/google/setup.php'>auth/google/setup.php</a> to authorize</div>";
        exit;
    } else {
        echo "<div class='success'>✓ gmail_token.json exists</div>";
    }
    
    // Try to create EmailService
    echo "<h2>2. EmailService Initialization</h2>";
    $emailService = new EmailService();
    echo "<div class='success'>✓ EmailService created successfully</div>";
    
    // Get token info
    echo "<h2>3. Token Information</h2>";
    $tokenData = json_decode(file_get_contents(GMAIL_TOKEN_PATH), true);
    
    if (isset($tokenData['email'])) {
        $authorizedEmail = $tokenData['email'];
        echo "<div class='info'>";
        echo "<p><strong>Authorized Email:</strong> " . htmlspecialchars($authorizedEmail) . "</p>";
        
        if (strtolower($authorizedEmail) === strtolower(GMAIL_SENDER_EMAIL)) {
            echo "<div class='success'>✅ CORRECT! Token is authorized for " . htmlspecialchars(GMAIL_SENDER_EMAIL) . "</div>";
        } else {
            echo "<div class='error'>❌ MISMATCH! Token is authorized for " . htmlspecialchars($authorizedEmail) . " but config expects " . htmlspecialchars(GMAIL_SENDER_EMAIL) . "</div>";
            echo "<div class='info'>💡 You need to re-authorize with the correct email account. Visit: <a href='auth/google/setup.php'>auth/google/setup.php</a></div>";
        }
        echo "</div>";
    } else {
        echo "<div class='info'>Token doesn't contain email field. This is normal for OAuth tokens.</div>";
    }
    
    // Check token expiry
    if (isset($tokenData['created']) && isset($tokenData['expires_in'])) {
        $expiresAt = $tokenData['created'] + $tokenData['expires_in'];
        $now = time();
        if ($expiresAt > $now) {
            $remaining = $expiresAt - $now;
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            echo "<div class='info'>Token expires in: {$hours} hours, {$minutes} minutes</div>";
        } else {
            echo "<div class='warning'>⚠ Token has expired. It will be refreshed automatically when needed.</div>";
        }
    }
    
    // Test email sending capability
    echo "<h2>4. Email Sending Test</h2>";
    echo "<div class='info'>";
    echo "<p>To test if emails can be sent from " . htmlspecialchars(GMAIL_SENDER_EMAIL) . ", visit:</p>";
    echo "<p><a href='test_email.php'>test_email.php</a></p>";
    echo "</div>";
    
    echo "<div class='success'>";
    echo "<h2>✅ Authorization Check Complete!</h2>";
    echo "<p><strong>System is configured to send emails from:</strong> " . htmlspecialchars(GMAIL_SENDER_EMAIL) . "</p>";
    echo "<p>All emails will be sent from this address.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ ERROR</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    
    if (strpos($e->getMessage(), 'authorization required') !== false) {
        echo "<div class='info'>💡 SOLUTION: Visit <a href='auth/google/setup.php'>auth/google/setup.php</a> to authorize Gmail API</div>";
    }
    
    echo "</div>";
}
?>

</body>
</html>

