<?php
require_once '../../config/db.php';

// Get error messages and preserved form data from session
$registration_errors = $_SESSION['registration_errors'] ?? [];
$registration_data = $_SESSION['registration_data'] ?? [];

// Clear error messages after displaying
unset($_SESSION['registration_errors']);
unset($_SESSION['registration_data']);

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../../php/landing.php");
    exit();
}
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Patient Registration - Dental Care Clinic</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
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
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;display=swap" rel="stylesheet"/>
<style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #e2e8f0;
            border-radius: 20px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d131b] dark:text-white h-screen overflow-hidden flex flex-col font-display">
<div class="flex flex-1 h-full w-full">
<div class="hidden lg:flex lg:w-[45%] xl:w-[50%] relative bg-blue-600 overflow-hidden">
<img alt="Dental Clinic Interior" class="absolute inset-0 h-full w-full object-cover mix-blend-overlay opacity-50" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAFu41GrkM5xj6g7_fcvaurtCUCojdaRP9JiiZIX1nYUI9yfI3xxMDwqJfdkbBpFdO1UoaM-yMQZp1mrm2HrjpQOylBSw51owerIVXdMPWjg8_lNn4s7XAkRyqQR-y4HKJsbn3g9ACanCD2qGQltxY91cA8riad5wkuWU3YD-PMkAZThiIkIjNMWboWplWMdD32YNMFBKJBR4dIRlyPG2ure1r5PDvw9dsehjLtphsRYpL6AfbB6ZQiJMQp92tosevwiZJtmnbkz1qD"/>
<div class="absolute inset-0 bg-gradient-to-br from-primary/90 to-blue-900/90 mix-blend-multiply"></div>
<div class="relative z-10 flex flex-col justify-between p-16 h-full text-white">
<div>
<div class="flex items-center gap-3 mb-12">
<div class="size-10 bg-white rounded-lg flex items-center justify-center text-primary shadow-lg">
<svg class="w-6 h-6" fill="currentColor" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path d="M44 11.2727C44 14.0109 39.8386 16.3957 33.69 17.6364C39.8386 18.877 44 21.2618 44 24C44 26.7382 39.8386 29.123 33.69 30.3636C39.8386 31.6043 44 33.9891 44 36.7273C44 40.7439 35.0457 44 24 44C12.9543 44 4 40.7439 4 36.7273C4 33.9891 8.16144 31.6043 14.31 30.3636C8.16144 29.123 4 26.7382 4 24C4 21.2618 8.16144 18.877 14.31 17.6364C8.16144 16.3957 4 14.0109 4 11.2727C4 7.25611 12.9543 4 24 4C35.0457 4 44 7.25611 44 11.2727Z"></path>
</svg>
</div>
<span class="text-2xl font-bold tracking-tight">Dental Care Clinic</span>
</div>
<h2 class="text-4xl md:text-5xl font-bold leading-tight mb-6">Start your journey to a perfect smile.</h2>
<p class="text-blue-100 text-lg max-w-md leading-relaxed">Join over 15,000 patients who trust us with their dental health. Comprehensive care for the whole family.</p>
</div>
<div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/10">
<div class="flex items-center gap-1 text-yellow-400 mb-3">
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="text-white text-sm font-medium ml-2">5.0 from 200+ reviews</span>
</div>
<p class="text-white/90 italic">"The registration process was seamless, and the staff made me feel comfortable immediately. Highly recommended!"</p>
<div class="flex items-center gap-3 mt-4">
<img alt="User" class="w-8 h-8 rounded-full object-cover border-2 border-white/50" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBWAcCQP0ePoWdQMvHC-tbgjc7QyXJ5f_TveAPbXb2Ncjv9XjPskeeB_uFJqdG9CUPS31feoMGN5Z3Up8v93w8UKsfgcVpWlyXCOOWBmn4QW6i1z6Xe6ncFwdcrVNKPj5W4KN6BYnRHRC22TXd9DQHewlu-oZaVVjF5o62luXRoC9ry5AShMC_LMlyBP_rXNTYOMOexaN2hfFcjPChTwLZw0GEOL0YLj4e7DlO44oLsVtVDkgM27z5P_RSUxHhEL73BuSX9lpjV1GK6"/>
<span class="text-sm font-medium">Sarah Jenkins, Patient</span>
</div>
</div>
</div>
</div>
<div class="flex-1 flex flex-col h-full bg-background-light dark:bg-background-dark relative">
<header class="lg:hidden flex items-center justify-between px-6 py-4 bg-white dark:bg-[#1a2634] border-b border-gray-200 dark:border-gray-800 shrink-0">
<div class="flex items-center gap-2 text-primary">
<div class="size-6">
<svg fill="currentColor" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path d="M44 11.2727C44 14.0109 39.8386 16.3957 33.69 17.6364C39.8386 18.877 44 21.2618 44 24C44 26.7382 39.8386 29.123 33.69 30.3636C39.8386 31.6043 44 33.9891 44 36.7273C44 40.7439 35.0457 44 24 44C12.9543 44 4 40.7439 4 36.7273C4 33.9891 8.16144 31.6043 14.31 30.3636C8.16144 29.123 4 26.7382 4 24C4 21.2618 8.16144 18.877 14.31 17.6364C8.16144 16.3957 4 14.0109 4 11.2727C4 7.25611 12.9543 4 24 4C35.0457 4 44 7.25611 44 11.2727Z"></path>
</svg>
</div>
<span class="text-lg font-bold text-gray-900 dark:text-white">Dental Care</span>
</div>
</header>
<div class="hidden lg:flex justify-end items-center p-8 absolute top-0 right-0 z-20 w-full">
<div class="flex items-center gap-6">
<span class="text-sm text-gray-500 dark:text-gray-400">Already a patient?</span>
<a class="text-primary bg-primary/10 hover:bg-primary/20 px-4 py-2 rounded-lg text-sm font-bold transition-colors" href="../login_page/login.php">
                        Log In
                    </a>
</div>
</div>
<div class="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-12 lg:p-16 flex flex-col items-center justify-center">
<div class="w-full max-w-[520px] mx-auto space-y-8">
<div class="text-center lg:text-left">
<h1 class="text-3xl font-black tracking-tight text-[#0d131b] dark:text-white mb-3">Patient Registration</h1>
<p class="text-[#4c6c9a] dark:text-gray-400 text-base">Please enter your details below to create a new patient account.</p>
</div>

<?php if (!empty($registration_errors)): ?>
<div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
<?php foreach ($registration_errors as $error): ?>
<p class="text-sm text-red-700 dark:text-red-300 mb-1"><?php echo htmlspecialchars($error); ?></p>
<?php endforeach; ?>
</div>
<?php endif; ?>

<form action="../../php/register.php" class="space-y-6" method="POST">
<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">First Name</span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="Jane" type="text" name="first_name" value="<?php echo htmlspecialchars($registration_data['first_name'] ?? ''); ?>" required/>
</label>
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Last Name</span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="Doe" type="text" name="last_name" value="<?php echo htmlspecialchars($registration_data['last_name'] ?? ''); ?>" required/>
</label>
</div>
<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1 relative">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Date of Birth</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="MM/DD/YYYY" type="text" name="dob" value="<?php echo htmlspecialchars($registration_data['dob'] ?? ''); ?>" required/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">calendar_today</span>
</div>
</label>
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Phone Number</span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="(555) 123-4567" type="tel" name="phone" value="<?php echo htmlspecialchars($registration_data['phone'] ?? ''); ?>" required/>
</label>
</div>
<label class="block">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Email Address</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="jane@example.com" type="email" name="email" value="<?php echo htmlspecialchars($registration_data['email'] ?? ''); ?>" required/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">mail</span>
</div>
</label>
<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1 relative">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Password</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="Create password" type="password" name="password" id="password" required/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] hover:text-primary transition-colors" type="button" onclick="togglePassword('password', 'password-toggle')">
<span class="material-symbols-outlined text-[20px]" id="password-toggle">visibility_off</span>
</button>
</div>
</label>
<label class="flex-1 relative">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Confirm Password</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="Confirm password" type="password" name="confirm_password" id="confirm_password" required/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] hover:text-primary transition-colors" type="button" onclick="togglePassword('confirm_password', 'confirm_password-toggle')">
<span class="material-symbols-outlined text-[20px]" id="confirm_password-toggle">visibility_off</span>
</button>
</div>
</label>
</div>
<div class="flex items-start gap-3 pt-2">
<div class="flex h-6 items-center">
<input class="h-5 w-5 rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-[#1a2634]" id="terms" name="terms" type="checkbox" required/>
</div>
<div class="text-sm leading-tight">
<label class="font-medium text-gray-500 dark:text-gray-400" for="terms">By creating an account, you agree to our <a class="text-primary hover:text-blue-700" href="#">Terms of Service</a> and <a class="text-primary hover:text-blue-700" href="#">Privacy Policy</a>.</label>
</div>
</div>
<div class="pt-4 space-y-4">
<button class="group relative w-full flex justify-center py-3.5 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-primary hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors shadow-lg shadow-blue-500/30" type="submit">
                                Create Account
                                <span class="material-symbols-outlined absolute right-4 group-hover:translate-x-1 transition-transform">arrow_forward</span>
</button>
</div>
<div class="mt-6 text-center lg:hidden">
<p class="text-sm text-gray-600 dark:text-gray-400">
                                Already have an account? 
                                <a class="font-bold text-primary hover:underline" href="../login_page/login.php">Log in here</a>
</p>
</div>
</form>
<div class="pt-8 text-center border-t border-gray-100 dark:border-gray-800">
<p class="text-xs text-gray-400">
                            © 2024 Dental Care Clinic. All rights reserved. <br/>
<a class="hover:text-primary" href="#">HIPAA Compliant</a> • <a class="hover:text-primary" href="#">Secure Connection</a>
</p>
</div>
</div>
</div>
</div>
</div>
<script>
function togglePassword(inputId, toggleId) {
    const passwordInput = document.getElementById(inputId);
    const passwordToggle = document.getElementById(toggleId);
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordToggle.textContent = 'visibility';
    } else {
        passwordInput.type = 'password';
        passwordToggle.textContent = 'visibility_off';
    }
}
</script>
</body></html>

