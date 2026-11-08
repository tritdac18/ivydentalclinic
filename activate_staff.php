<?php
require_once 'config/db.php';
require_once 'config/security.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if (empty($token)) {
    $error = "Invalid activation link.";
} else {
    try {
        $conn = getDBConnection();
        
        // Check if token exists and is valid
        $stmt = $conn->prepare("
            SELECT sat.staff_id, sat.activation_token, sat.expires_at, s.first_name, s.last_name, s.email, s.username
            FROM tbl_staff_activation_tokens sat
            INNER JOIN tbl_staff s ON sat.staff_id = s.staff_id
            WHERE sat.activation_token = ? AND sat.expires_at > NOW() AND sat.is_used = 0
            ORDER BY sat.created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Invalid or expired activation link.";
            $stmt->close();
        } else {
            $token_data = $result->fetch_assoc();
            $staff_id = $token_data['staff_id'];
            
            // Handle password submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['confirm_password'])) {
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($password) || strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } else {
                    // Hash password and update staff record
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE tbl_staff SET password = ? WHERE staff_id = ?");
                    $update_stmt->bind_param("si", $hashed_password, $staff_id);
                    
                    if ($update_stmt->execute()) {
                        // Mark token as used
                        $mark_stmt = $conn->prepare("UPDATE tbl_staff_activation_tokens SET is_used = 1, used_at = NOW() WHERE activation_token = ?");
                        $mark_stmt->bind_param("s", $token);
                        $mark_stmt->execute();
                        $mark_stmt->close();
                        
                        $success = true;
                        $update_stmt->close();
                    } else {
                        $error = "Failed to set password. Please try again.";
                    }
                }
            }
            
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Activate Staff Account - Ivy Dental Clinic</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-lg p-8">
<div class="text-center mb-6">
<div class="size-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
<span class="material-symbols-outlined text-4xl">dentistry</span>
</div>
<h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Activate Staff Account</h1>
<?php if (isset($token_data)): ?>
<p class="text-sm text-slate-500 dark:text-slate-400">Welcome, <?php echo htmlspecialchars($token_data['first_name'] . ' ' . $token_data['last_name']); ?>!</p>
<?php endif; ?>
</div>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400 mb-4">
<p class="text-sm font-medium">Account activated successfully! You can now log in with your username and password.</p>
</div>
<div class="text-center">
<a href="login.php" class="inline-flex items-center gap-2 bg-primary text-white hover:bg-blue-700 font-semibold transition-colors px-6 py-3 rounded-lg">
<span>Go to Login</span>
</a>
</div>
<?php elseif ($error && !isset($token_data)): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 mb-4">
<p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
</div>
<?php elseif (isset($token_data)): ?>
<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 mb-4">
<p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
</div>
<?php endif; ?>
<form method="POST" action="" class="space-y-4">
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Username</label>
<input type="text" value="<?php echo htmlspecialchars($token_data['username']); ?>" readonly class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm"/>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Password <span class="text-red-500">*</span></label>
<input type="password" name="password" required minlength="8" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter password (min. 8 characters)"/>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Confirm Password <span class="text-red-500">*</span></label>
<input type="password" name="confirm_password" required minlength="8" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Confirm password"/>
</div>
<button type="submit" class="w-full bg-primary text-white hover:bg-blue-700 font-semibold transition-colors px-4 py-3 rounded-lg">
Activate Account
</button>
</form>
<?php endif; ?>
</div>
</div>
</body>
</html>

