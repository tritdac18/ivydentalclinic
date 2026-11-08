<?php
/**
 * Gmail API Setup Helper
 * Use this to get the authorization URL and complete OAuth setup
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
    die('
    <h2>Google API Client library not found!</h2>
    <p><strong>Option 1 (Recommended):</strong> Install via Composer:</p>
    <pre>composer require google/apiclient</pre>
    
    <p><strong>Option 2 (Manual):</strong></p>
    <ol>
        <li>Download from: <a href="https://github.com/googleapis/google-api-php-client/releases">GitHub Releases</a></li>
        <li>Extract the ZIP file</li>
        <li>Copy the entire extracted folder to: <code>vendor/google/apiclient/</code></li>
        <li>Make sure the structure is: <code>vendor/google/apiclient/src/Google/Client.php</code></li>
    </ol>
    <p>See <code>MANUAL_INSTALLATION_GUIDE.md</code> for detailed instructions.</p>
    ');
}

use Google\Client;

// Check if credentials.json exists
if (!file_exists(GMAIL_CREDENTIALS_PATH)) {
    die('
    <h1>❌ credentials.json not found</h1>
    <p>Please download credentials.json from Google Cloud Console and place it in: <code>' . GMAIL_CREDENTIALS_PATH . '</code></p>
    <p><strong>Steps:</strong></p>
    <ol>
        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
        <li>Select your project</li>
        <li>Go to APIs & Services > Credentials</li>
        <li>Click on your OAuth 2.0 Client ID</li>
        <li>Click "Download JSON"</li>
        <li>Save it as <code>config/credentials.json</code></li>
    </ol>
    ');
}

try {
    $client = new Client();
    $client->setAuthConfig(GMAIL_CREDENTIALS_PATH);
    $client->addScope(GMAIL_SCOPE);
    $client->setRedirectUri(GMAIL_REDIRECT_URI);
    
    // Check if we already have a valid token
    if (file_exists(GMAIL_TOKEN_PATH)) {
        $accessToken = json_decode(file_get_contents(GMAIL_TOKEN_PATH), true);
        $client->setAccessToken($accessToken);
        
        if (!$client->isAccessTokenExpired()) {
            echo '<h1>✅ Gmail API Already Authorized!</h1>';
            echo '<p>Your Gmail API is already set up and ready to use.</p>';
            echo '<p>Token expires: ' . date('Y-m-d H:i:s', $accessToken['created'] + $accessToken['expires_in']) . '</p>';
            echo '<p><a href="../../register.php">Go to Registration</a></p>';
            exit;
        }
    }
    
    // Get authorization URL
    $authUrl = $client->createAuthUrl();
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Gmail API Setup</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; }
            .warning-box { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; }
            .success-box { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0; }
            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
            .button { display: inline-block; background: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
            .button:hover { background: #1976d2; }
        </style>
    </head>
    <body>
        <h1>🔧 Gmail API Setup</h1>
        
        <div class="info-box">
            <h3>📋 Current Configuration</h3>
            <p><strong>Redirect URI:</strong> <code><?php echo htmlspecialchars(GMAIL_REDIRECT_URI); ?></code></p>
            <p><strong>Credentials File:</strong> <code><?php echo htmlspecialchars(GMAIL_CREDENTIALS_PATH); ?></code> <?php echo file_exists(GMAIL_CREDENTIALS_PATH) ? '✅' : '❌'; ?></p>
            <p><strong>Token File:</strong> <code><?php echo htmlspecialchars(GMAIL_TOKEN_PATH); ?></code> <?php echo file_exists(GMAIL_TOKEN_PATH) ? '✅' : '❌'; ?></p>
        </div>
        
        <div class="warning-box">
            <h3>⚠️ Important: Configure Google Cloud Console</h3>
            <p>Before clicking the authorization link below, make sure you have added this <strong>exact</strong> redirect URI in Google Cloud Console:</p>
            <p><code style="background: white; padding: 10px; display: block; margin: 10px 0;"><?php echo htmlspecialchars(GMAIL_REDIRECT_URI); ?></code></p>
            <p><strong>Steps:</strong></p>
            <ol>
                <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console > Credentials</a></li>
                <li>Click on your OAuth 2.0 Client ID</li>
                <li>Under "Authorized redirect URIs", click "Add URI"</li>
                <li>Enter: <code><?php echo htmlspecialchars(GMAIL_REDIRECT_URI); ?></code></li>
                <li>Click "Save"</li>
                <li>Wait a few minutes for changes to propagate</li>
            </ol>
        </div>
        
        <div class="success-box">
            <h3>✅ Ready to Authorize</h3>
            <p>Once you've added the redirect URI in Google Cloud Console, click the button below to authorize Gmail API access:</p>
            <a href="<?php echo htmlspecialchars($authUrl); ?>" class="button">Authorize Gmail API</a>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h3>🔍 Troubleshooting</h3>
            <p><strong>Error: "Invalid Origin" or "URIs must not contain a path"</strong></p>
            <ul>
                <li>Make sure the redirect URI in Google Cloud Console matches <strong>exactly</strong> (including https/http)</li>
                <li>No trailing slashes</li>
                <li>Wait a few minutes after saving in Google Cloud Console</li>
                <li>Try clearing browser cache</li>
            </ul>
            
            <p><strong>Error: "redirect_uri_mismatch"</strong></p>
            <ul>
                <li>The redirect URI in the code must match Google Cloud Console exactly</li>
                <li>Check both HTTP and HTTPS versions if testing locally</li>
            </ul>
        </div>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    echo '<h1>❌ Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Check that credentials.json is valid and properly formatted.</p>';
}

