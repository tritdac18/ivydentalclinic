<?php
/**
 * Email Service using Gmail API
 * Supports both Composer and manual installation
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/gmail_config.php';

// Load Google API Client
// Priority 1: Try Composer autoloader (recommended)
// Priority 2: Fall back to manual installation
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Composer installation (recommended)
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual installation fallback
    // Files are in src/ directly, not src/Google/
    $apiclientPath = __DIR__ . '/../vendor/google/apiclient';
    $basePath = $apiclientPath . '/src';
    
    $requiredFiles = [
        'Client.php',
        'Service.php',
        'Collection.php',
        'Model.php',
        'Exception.php',
    ];
    
    foreach ($requiredFiles as $file) {
        $filePath = $basePath . '/' . $file;
        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
    
    // Load aliases.php if it exists
    $aliasesPath = $basePath . '/aliases.php';
    if (file_exists($aliasesPath)) {
        require_once $aliasesPath;
    }
    
    // Set up autoloader for Google namespace (manual installation)
    spl_autoload_register(function ($class) {
        $basePath = __DIR__ . '/../vendor/google/apiclient/src';
        
        // Handle Google\Service\* classes (e.g., Google\Service\Gmail)
        if (strpos($class, 'Google\\Service\\') === 0) {
            $serviceName = substr($class, strlen('Google\\Service\\'));
            // Try Service folder first
            $servicePath = $basePath . '/Service/' . $serviceName . '.php';
            if (file_exists($servicePath)) {
                require_once $servicePath;
                return true;
            }
            // Service classes are generated dynamically, let Composer handle it if available
        }
        
        // Handle Google\* base classes (files are directly in src/, not src/Google/)
        // The namespace is Google but files don't have Google/ folder
        if (strpos($class, 'Google\\') === 0 && strpos($class, 'Google\\Service\\') !== 0) {
            // For classes like Google\Client, the file is at src/Client.php
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

class EmailService {
    private $client;
    private $service;
    
    public function __construct() {
        if (!isGmailConfigured()) {
            throw new Exception('Gmail API is not configured. Please set up credentials.json');
        }
        
        $this->client = new \Google\Client();
        
        // Load credentials and handle both 'web' and 'installed' types
        $credentials = json_decode(file_get_contents(GMAIL_CREDENTIALS_PATH), true);
        if (isset($credentials['web'])) {
            // Web application type
            $this->client->setClientId($credentials['web']['client_id']);
            $this->client->setClientSecret($credentials['web']['client_secret']);
            if (isset($credentials['web']['redirect_uris']) && !empty($credentials['web']['redirect_uris'])) {
                $this->client->setRedirectUri($credentials['web']['redirect_uris'][0]);
            } else {
                $this->client->setRedirectUri(GMAIL_REDIRECT_URI);
            }
        } elseif (isset($credentials['installed'])) {
            // Desktop app type (original)
            $this->client->setAuthConfig(GMAIL_CREDENTIALS_PATH);
        } else {
            throw new Exception('Invalid credentials.json format. Must have either "web" or "installed" key.');
        }
        
        $this->client->addScope(GMAIL_SCOPE);
        if (!isset($credentials['web'])) {
            // Only set redirect URI if using installed type
            $this->client->setRedirectUri(GMAIL_REDIRECT_URI);
        }
        
        // Load token if exists
        if (file_exists(GMAIL_TOKEN_PATH)) {
            $accessToken = json_decode(file_get_contents(GMAIL_TOKEN_PATH), true);
            $this->client->setAccessToken($accessToken);
        }
        
        // Refresh token if expired
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                file_put_contents(GMAIL_TOKEN_PATH, json_encode($this->client->getAccessToken()));
            } else {
                throw new Exception('Gmail authorization required. Please visit: ' . $this->getAuthUrl());
            }
        }
        
        // Create Gmail service
        // The Gmail service class is generated dynamically by the Google Client library
        if (!class_exists('Google\Service\Gmail')) {
            // Try to load it manually if autoloader didn't catch it
            $gmailPath = __DIR__ . '/../vendor/google/apiclient/src/Google/Service/Gmail.php';
            if (file_exists($gmailPath)) {
                require_once $gmailPath;
            }
        }
        
        $this->service = new \Google\Service\Gmail($this->client);
    }
    
    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }
    
    public function generateVerificationCode() {
        return str_pad(rand(0, 999999), VERIFICATION_CODE_LENGTH, '0', STR_PAD_LEFT);
    }
    
    public function saveVerificationCode($email, $code) {
        $conn = getDBConnection();
        
        // Delete old codes
        $delete_stmt = $conn->prepare("DELETE FROM tbl_email_verification WHERE email = ? AND (is_used = 1 OR expires_at < NOW())");
        $delete_stmt->bind_param("s", $email);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Insert new code
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY_MINUTES . ' minutes'));
        $insert_stmt = $conn->prepare("INSERT INTO tbl_email_verification (email, verification_code, expires_at) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("sss", $email, $code, $expires_at);
        $insert_stmt->execute();
        $insert_id = $conn->insert_id;
        $insert_stmt->close();
        $conn->close();
        
        return $insert_id;
    }
    
    public function verifyCode($email, $code) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT verification_id, expires_at, is_used 
            FROM tbl_email_verification 
            WHERE email = ? AND verification_code = ? AND expires_at > NOW() AND is_used = 0
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Mark code as used
            $update_stmt = $conn->prepare("UPDATE tbl_email_verification SET is_used = 1, verified_at = NOW() WHERE verification_id = ?");
            $update_stmt->bind_param("i", $row['verification_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            $stmt->close();
            $conn->close();
            return true;
        }
        
        $stmt->close();
        $conn->close();
        return false;
    }
    
    public function sendVerificationEmail($email, $code) {
        $subject = 'Ivy Dental Clinic - Email Verification';
        $body = $this->getVerificationEmailTemplate($code);
        
        try {
            if (!$this->service) {
                throw new Exception('Gmail service is not initialized');
            }
            
            $message = $this->createMessage($email, $subject, $body);
            $this->service->users_messages->send('me', $message);
            return true;
        } catch (Exception $e) {
            error_log('Gmail API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function createMessage($to, $subject, $htmlBody) {
        $message = new \Google\Service\Gmail\Message();
        
        $rawMessage = "To: {$to}\r\n";
        $rawMessage .= "From: " . GMAIL_SENDER_NAME . " <" . GMAIL_SENDER_EMAIL . ">\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
        $rawMessage .= "\r\n{$htmlBody}";
        
        $message->setRaw(base64_encode($rawMessage));
        
        return $message;
    }
    
    public function sendPaymentConfirmationEmail($email, $amount, $appointment_id, $service_name) {
        $subject = 'Ivy Dental Clinic - Payment Confirmation';
        $body = $this->getPaymentConfirmationTemplate($amount, $appointment_id, $service_name);
        
        try {
            if (!$this->service) {
                throw new Exception('Gmail service is not initialized');
            }
            
            $message = $this->createMessage($email, $subject, $body);
            $this->service->users_messages->send('me', $message);
            return true;
        } catch (Exception $e) {
            error_log('Gmail API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getPaymentConfirmationTemplate($amount, $appointment_id, $service_name) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #136dec; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .amount { background: #136dec; color: white; font-size: 32px; font-weight: bold; 
                        padding: 20px; text-align: center; margin: 20px 0; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #136dec; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ivy Dental Clinic</h1>
                </div>
                <div class='content'>
                    <h2>Payment Confirmation</h2>
                    <p>Thank you for your payment! We have successfully received your downpayment via PayMongo.</p>
                    
                    <div class='amount'>₱" . number_format($amount, 2) . "</div>
                    
                    <div class='info-box'>
                        <p><strong>Appointment ID:</strong> #{$appointment_id}</p>
                        <p><strong>Service:</strong> {$service_name}</p>
                        <p><strong>Payment Method:</strong> PayMongo</p>
                        <p><strong>Payment Date:</strong> " . date('F d, Y h:i A') . "</p>
                    </div>
                    
                    <p>Your appointment has been confirmed. You will receive a reminder before your scheduled appointment.</p>
                    <p>If you have any questions, please don't hesitate to contact us.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Ivy Dental Clinic. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getVerificationEmailTemplate($code) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #136dec; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .code { background: #136dec; color: white; font-size: 32px; font-weight: bold; 
                        padding: 20px; text-align: center; letter-spacing: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ivy Dental Clinic</h1>
                </div>
                <div class='content'>
                    <h2>Email Verification</h2>
                    <p>Thank you for registering with Ivy Dental Clinic. Please verify your email address by entering the code below:</p>
                    <div class='code'>{$code}</div>
                    <p>This code will expire in " . VERIFICATION_CODE_EXPIRY_MINUTES . " minutes.</p>
                    <p>If you did not request this verification, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Ivy Dental Clinic. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    public function sendStaffActivationEmail($email, $first_name, $activation_url) {
        $subject = 'Ivy Dental Clinic - Staff Account Activation';
        $body = $this->getStaffActivationEmailTemplate($first_name, $activation_url);
        
        try {
            if (!$this->service) {
                throw new Exception('Gmail service is not initialized');
            }
            
            $message = $this->createMessage($email, $subject, $body);
            $this->service->users_messages->send('me', $message);
            return true;
        } catch (Exception $e) {
            error_log('Gmail API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getStaffActivationEmailTemplate($first_name, $activation_url) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #136dec; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .button { display: inline-block; background: #136dec; color: white; padding: 15px 30px; 
                         text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #136dec; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ivy Dental Clinic</h1>
                </div>
                <div class='content'>
                    <h2>Welcome to Ivy Dental Clinic, {$first_name}!</h2>
                    <p>Your staff account has been created. To activate your account and set your password, please click the button below:</p>
                    <div style='text-align: center;'>
                        <a href='{$activation_url}' class='button'>Activate Account</a>
                    </div>
                    <div class='info-box'>
                        <p><strong>Important:</strong> This activation link will expire in 24 hours.</p>
                        <p>If the button doesn't work, copy and paste this link into your browser:</p>
                        <p style='word-break: break-all; font-size: 12px; color: #666;'>{$activation_url}</p>
                    </div>
                    <p>If you did not expect this email, please contact the clinic administrator.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Ivy Dental Clinic. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Send maintenance reminder email to patient
     * @param string $email Patient email
     * @param string $patient_name Patient first name
     * @param string $service_name Service name (e.g., "Braces")
     * @param string $next_due_date Next recommended visit date
     * @param string $reminder_type Type of reminder: 'first', 'second', or 'overdue'
     * @return bool Success status
     */
    public function sendMaintenanceReminderEmail($email, $patient_name, $service_name, $next_due_date, $reminder_type = 'first') {
        $subject = 'Ivy Dental Clinic - Treatment Plan Reminder';
        
        // Determine subject and urgency based on reminder type
        if ($reminder_type === 'overdue') {
            $subject = 'Ivy Dental Clinic - Urgent: Treatment Plan Overdue';
        } elseif ($reminder_type === 'second') {
            $subject = 'Ivy Dental Clinic - Follow-up: Schedule Your Appointment';
        }
        
        $body = $this->getMaintenanceReminderTemplate($patient_name, $service_name, $next_due_date, $reminder_type);
        
        try {
            if (!$this->service) {
                throw new Exception('Gmail service is not initialized');
            }
            
            $message = $this->createMessage($email, $subject, $body);
            $this->service->users_messages->send('me', $message);
            return true;
        } catch (Exception $e) {
            error_log('Gmail API Error (Maintenance Reminder): ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function getMaintenanceReminderTemplate($patient_name, $service_name, $next_due_date, $reminder_type) {
        $formatted_date = date('F d, Y', strtotime($next_due_date));
        $days_until = ceil((strtotime($next_due_date) - time()) / (60 * 60 * 24));
        
        // Determine urgency and message based on reminder type
        $urgency_color = '#136dec'; // Blue for first reminder
        $urgency_text = 'Gentle Reminder';
        $main_message = "You're due for your next {$service_name} appointment. Please schedule your visit to maintain your treatment progress.";
        
        if ($reminder_type === 'second') {
            $urgency_color = '#f59e0b'; // Amber for second reminder
            $urgency_text = 'Follow-up Reminder';
            $main_message = "This is a follow-up reminder about your upcoming {$service_name} appointment. Please schedule soon to avoid delays in your treatment.";
        } elseif ($reminder_type === 'overdue') {
            $urgency_color = '#dc2626'; // Red for overdue
            $urgency_text = 'Urgent: Overdue';
            $main_message = "Your {$service_name} appointment is overdue. Please schedule immediately to prevent treatment interruption and ensure optimal results.";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #136dec; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .urgency-box { background: {$urgency_color}; color: white; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .date-box { background: white; padding: 20px; border-left: 4px solid {$urgency_color}; margin: 20px 0; }
                .button { display: inline-block; background: #136dec; color: white; padding: 15px 30px; 
                         text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #136dec; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ivy Dental Clinic</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$patient_name},</h2>
                    <p>{$main_message}</p>
                    
                    <div class='urgency-box'>
                        <strong>{$urgency_text}</strong>
                    </div>
                    
                    <div class='date-box'>
                        <p><strong>Recommended Next Visit:</strong></p>
                        <p style='font-size: 24px; font-weight: bold; color: {$urgency_color}; margin: 10px 0;'>{$formatted_date}</p>";
        
        if ($reminder_type !== 'overdue' && $days_until > 0) {
            $template .= "<p style='color: #666; font-size: 14px;'>In approximately {$days_until} day" . ($days_until > 1 ? 's' : '') . "</p>";
        } elseif ($reminder_type === 'overdue') {
            $template .= "<p style='color: #dc2626; font-size: 14px; font-weight: bold;'>Your appointment window has passed. Please contact us immediately.</p>";
        }
        
        $template .= "
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <p style='margin-bottom: 15px;'>To schedule your appointment, please:</p>
                        <p style='margin: 10px 0;'>1. Log in to your patient portal</p>
                        <p style='margin: 10px 0;'>2. Navigate to the Appointments section</p>
                        <p style='margin: 10px 0;'>3. Book your next visit</p>
                    </div>
                    
                    <div class='info-box'>
                        <p><strong>Why regular visits matter:</strong></p>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Maintain treatment progress</li>
                            <li>Prevent complications</li>
                            <li>Ensure optimal results</li>
                            <li>Adjust treatment as needed</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions or need to reschedule, please contact our clinic directly.</p>
                    <p>Thank you for choosing Ivy Dental Clinic.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Ivy Dental Clinic. All rights reserved.</p>
                    <p style='font-size: 11px; color: #999; margin-top: 10px;'>This is an automated reminder. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        return $template;
    }
}
