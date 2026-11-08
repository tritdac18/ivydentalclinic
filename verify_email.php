<?php
/**
 * Email Verification Page
 * Users enter their verification code here after registration
 */

require_once 'config/db.php';
require_once 'services/email_service.php';

session_start();

$email = $_GET['email'] ?? $_SESSION['pending_email'] ?? '';
$errors = [];
$success = false;

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['verification_code'] ?? '');
    
    if (empty($email) || empty($code)) {
        $errors[] = "Email and verification code are required.";
    } else {
        try {
            $emailService = new EmailService();
            
            if ($emailService->verifyCode($email, $code)) {
                // Mark email as verified in session
                $_SESSION['email_verified'] = $email;
                $success = true;
                
                // Check if this is a registration verification
                if (isset($_GET['registration']) || isset($_SESSION['pending_registration'])) {
                    // Update pending registration to mark as verified
                    require_once 'config/db.php';
                    $conn = getDBConnection();
                    
                    // Check if new structure (has patient_id) or old structure
                    $column_check = $conn->query("SHOW COLUMNS FROM tbl_pending_registrations LIKE 'patient_id'");
                    if ($column_check && $column_check->num_rows > 0) {
                        // New structure: find patient_id from email
                        $patient_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? AND status = 'pending_verification' LIMIT 1");
                        $patient_stmt->bind_param("s", $email);
                        $patient_stmt->execute();
                        $patient_result = $patient_stmt->get_result();
                        if ($patient_result->num_rows > 0) {
                            $patient = $patient_result->fetch_assoc();
                            $update_stmt = $conn->prepare("UPDATE tbl_pending_registrations SET verified_at = NOW() WHERE patient_id = ? AND verification_code = ?");
                            $update_stmt->bind_param("is", $patient['patient_id'], $code);
                        } else {
                            $update_stmt = null;
                        }
                        $patient_stmt->close();
                    } else {
                        // Old structure: use email
                        $update_stmt = $conn->prepare("UPDATE tbl_pending_registrations SET verified_at = NOW() WHERE email = ? AND verification_code = ?");
                        $update_stmt->bind_param("ss", $email, $code);
                    }
                    
                    if ($update_stmt) {
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    $conn->close();
                    
                    // Redirect to complete registration
                    header("Location: complete_registration.php?email=" . urlencode($email));
                    exit();
                } else {
                    // Just email verification (not registration)
                    header("Location: register.php?email_verified=1&email=" . urlencode($email));
                    exit();
                }
            } else {
                $errors[] = "Invalid or expired verification code. Please check your email and try again.";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again later.";
            error_log('Email verification error: ' . $e->getMessage());
        }
    }
}

// If no email provided, redirect to registration
if (empty($email)) {
    header("Location: register.php");
    exit();
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Verify Email - Ivy Dental Clinic</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#136dec",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101822",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#0d131b] dark:text-slate-100 antialiased min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-[#1a2634] rounded-xl border border-[#e7ecf3] dark:border-slate-700 shadow-lg p-8">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 text-primary mb-4">
                    <span class="material-symbols-outlined text-3xl">mail</span>
                </div>
                <h1 class="text-2xl font-bold text-[#0d131b] dark:text-white mb-2">Verify Your Email</h1>
                <p class="text-sm text-[#4c6c9a] dark:text-slate-400">
                    We've sent a verification code to<br>
                    <strong class="text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($email); ?></strong>
                </p>
                <?php if (isset($_GET['registration']) || isset($_SESSION['pending_registration'])): ?>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 mt-2">
                    ⚠️ Your account will be created after email verification
                </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg">
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['message']) && !empty($_GET['message'])): ?>
                <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg">
                    <p class="text-sm"><?php echo htmlspecialchars($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error']) && !empty($_GET['error'])): ?>
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg">
                    <p class="text-sm"><?php echo htmlspecialchars($_GET['error']); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                
                <div>
                    <label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">
                        Verification Code
                    </label>
                    <input 
                        type="text" 
                        name="verification_code" 
                        maxlength="6" 
                        pattern="[0-9]{6}"
                        required 
                        autofocus
                        class="w-full rounded-lg border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 text-center text-2xl font-bold tracking-widest"
                        placeholder="000000"
                    />
                    <p class="text-xs text-[#4c6c9a] dark:text-slate-400 mt-2">
                        Enter the 6-digit code sent to your email
                    </p>
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-primary hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg transition-colors shadow-md shadow-blue-500/20"
                >
                    Verify Email
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-[#4c6c9a] dark:text-slate-400">
                    Didn't receive the code? 
                    <a href="resend_verification.php?email=<?php echo urlencode($email); ?><?php echo (isset($_GET['registration']) || isset($_SESSION['pending_registration'])) ? '&registration=1' : ''; ?>" class="text-primary hover:underline">
                        Resend Code
                    </a>
                </p>
            </div>

            <div class="mt-4 text-center">
                <a href="register.php" class="text-sm text-[#4c6c9a] dark:text-slate-400 hover:text-primary">
                    ← Back to Registration
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus and format input
        document.querySelector('input[name="verification_code"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>

