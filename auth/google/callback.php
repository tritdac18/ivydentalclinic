<?php
/**
 * Google OAuth Callback Handler
 * This handles the OAuth redirect from Google
 */

require_once __DIR__ . '/../../config/gmail_config.php';

// Load Google API Client - same logic as email_service.php
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    // Composer installation (recommended)
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    // Manual installation fallback
    $apiclientPath = __DIR__ . '/../../vendor/google/apiclient';
    $basePath = $apiclientPath . '/src';
    
    $requiredFiles = ['Client.php', 'Service.php', 'Collection.php', 'Model.php', 'Exception.php'];
    foreach ($requiredFiles as $file) {
        $filePath = $basePath . '/' . $file;
        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
    
    $aliasesPath = $basePath . '/aliases.php';
    if (file_exists($aliasesPath)) {
        require_once $aliasesPath;
    }
    
    spl_autoload_register(function ($class) {
        $basePath = __DIR__ . '/../../vendor/google/apiclient/src';
        if (strpos($class, 'Google\\Service\\') === 0) {
            $serviceName = substr($class, strlen('Google\\Service\\'));
            $servicePath = $basePath . '/Service/' . $serviceName . '.php';
            if (file_exists($servicePath)) {
                require_once $servicePath;
                return true;
            }
        }
        if (strpos($class, 'Google\\') === 0 && strpos($class, 'Google\\Service\\') !== 0) {
            $className = substr($class, strlen('Google\\'));
            $classPath = $basePath . '/' . $className . '.php';
            if (file_exists($classPath)) {
                require_once $classPath;
                return true;
            }
        }
        return false;
    }, true, true);
}

if (!class_exists('Google\Client')) {
    die('Google API Client library not found. Please install via Composer: composer require google/apiclient');
}

use Google\Client;

// Handle errors from Google
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Unknown error';
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Gmail API Authorization Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error-box { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0; }
            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <h1>❌ Authorization Error</h1>
        <div class="error-box">
            <p><strong>Error:</strong> ' . htmlspecialchars($error) . '</p>
            <p><strong>Description:</strong> ' . htmlspecialchars($errorDescription) . '</p>
        </div>
        
        <h3>Common Solutions:</h3>
        <ul>
            <li><strong>redirect_uri_mismatch:</strong> The redirect URI in Google Cloud Console must match exactly: <code>' . htmlspecialchars(GMAIL_REDIRECT_URI) . '</code></li>
            <li><strong>access_denied:</strong> You cancelled the authorization. Try again.</li>
        </ul>
        
        <p><a href="setup.php">← Back to Setup</a></p>
    </body>
    </html>';
    exit;
}

if (isset($_GET['code'])) {
    try {
        $client = new Client();
        $client->setAuthConfig(GMAIL_CREDENTIALS_PATH);
        $client->addScope(GMAIL_SCOPE);
        $client->setRedirectUri(GMAIL_REDIRECT_URI);
        
        // Exchange authorization code for access token
        $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (array_key_exists('error', $accessToken)) {
            throw new Exception($accessToken['error_description'] ?? $accessToken['error']);
        }
        
        // Save token to file
        if (!file_exists(dirname(GMAIL_TOKEN_PATH))) {
            mkdir(dirname(GMAIL_TOKEN_PATH), 0700, true);
        }
        file_put_contents(GMAIL_TOKEN_PATH, json_encode($accessToken));
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Gmail API Authorization Success</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
                .success-box { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 20px; margin: 20px 0; }
                .button { display: inline-block; background: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="success-box">
                <h1>✅ Gmail API Authorization Successful!</h1>
                <p>Your Gmail API is now configured and ready to send verification emails.</p>
            </div>
            <p><a href="../../register.php" class="button">Go to Registration</a></p>
            <p><a href="setup.php">View Setup Status</a></p>
        </body>
        </html>';
        
    } catch (Exception $e) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Gmail API Authorization Error</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error-box { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <h1>❌ Error</h1>
            <div class="error-box">
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
            </div>
            <p><a href="setup.php">← Back to Setup</a></p>
        </body>
        </html>';
    }
} else {
    // No code parameter - this is normal if visiting directly
    // Check if we already have a valid token
    if (file_exists(GMAIL_TOKEN_PATH)) {
        try {
            $client = new Client();
            $client->setAuthConfig(GMAIL_CREDENTIALS_PATH);
            $accessToken = json_decode(file_get_contents(GMAIL_TOKEN_PATH), true);
            $client->setAccessToken($accessToken);
            
            if (!$client->isAccessTokenExpired()) {
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <title>Gmail API Callback</title>
                    <style>
                        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
                        .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 20px 0; }
                        .button { display: inline-block; background: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <div class="info-box">
                        <h1>ℹ️ Gmail API Already Authorized</h1>
                        <p>You already have a valid Gmail API token.</p>
                        <p>Token expires: ' . date('Y-m-d H:i:s', $accessToken['created'] + $accessToken['expires_in']) . '</p>
                        <p>If you need to re-authorize, visit the setup page.</p>
                    </div>
                    <p><a href="setup.php" class="button">Go to Setup</a></p>
                    <p><a href="../../test_email_send.php" class="button">Test Email Sending</a></p>
                </body>
                </html>';
                exit;
            }
        } catch (Exception $e) {
            // Token might be invalid, continue to show setup link
        }
    }
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Gmail API Callback</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; background: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="info-box">
            <h1>ℹ️ No Authorization Code</h1>
            <p>This page is the OAuth callback endpoint. It\'s normal to see this message if you visit it directly.</p>
            <p><strong>This is not an error!</strong> The callback URL is only used when Google redirects here after authorization.</p>
        </div>
        <p>To authorize Gmail API access, visit the setup page:</p>
        <p><a href="setup.php" class="button">Go to Setup</a></p>
        <p><a href="../../test_email_send.php" class="button">Test Email Sending</a></p>
    </body>
    </html>';
}

