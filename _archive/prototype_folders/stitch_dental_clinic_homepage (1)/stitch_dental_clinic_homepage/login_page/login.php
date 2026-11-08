<?php
require_once '../../config/db.php';

// Get error messages from session
$login_errors = $_SESSION['login_errors'] ?? [];
$success_message = $_SESSION['success_message'] ?? '';

// Clear error messages after displaying
unset($_SESSION['login_errors']);
unset($_SESSION['success_message']);

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
<title>Login to Ivy Dental Clinic</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
</head>
<body class="font-display antialiased text-slate-900 bg-background-light dark:bg-background-dark dark:text-white">
<div class="flex min-h-screen w-full flex-row overflow-hidden">
<div class="relative hidden lg:flex w-1/2 flex-col bg-slate-100 dark:bg-slate-900">
<div class="absolute inset-0 z-10 bg-primary/20 mix-blend-multiply"></div>
<div class="absolute inset-0 z-0 h-full w-full bg-cover bg-center" data-alt="Modern clean dental clinic office reception area" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAN0mFN1FdmAYI5fDd03YcDt9IcdrsWE4pLHyE6OZhoDTSMQFKxlQITjfdf1iQA6TnCPplrlWVfeXR_wLXkZ4NM2tHHNj1D98L-_PS6DAQapR2I55xkisDrpCIl68KZBzKQKHWswf6GZIdIY3KBhvTc64x747X_Gpc9XLtULaAnyBQHzqrNnJI2L8RD0yqsfVXrjQiSEa92YQQ7_emt6OXHQuzrWDaApWhTiv9j8YJ_YMgTnxH_u3IlSQtecyw5hZiXV7RGqpYx5rlT');">
</div>
<div class="relative z-20 flex h-full flex-col justify-between p-12 text-white">
<div class="flex items-center gap-3">
<div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20 backdrop-blur-md">
<span class="material-symbols-outlined text-white" style="font-size: 24px;">dentistry</span>
</div>
<span class="text-xl font-bold tracking-tight">Ivy Dental Clinic</span>
</div>
<div class="max-w-md">
<blockquote class="text-xl font-medium leading-relaxed">
                        "Dedicated to providing the highest quality dental care in a comfortable and relaxed atmosphere."
                    </blockquote>
</div>
</div>
</div>
<div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:px-20 xl:px-24 bg-background-light dark:bg-background-dark">
<div class="mx-auto w-full max-w-sm lg:w-96">
<div class="lg:hidden flex justify-center mb-8">
<div class="flex items-center gap-2 text-primary">
<span class="material-symbols-outlined" style="font-size: 32px;">dentistry</span>
<h2 class="text-2xl font-bold text-slate-900 dark:text-white">Ivy Dental</h2>
</div>
</div>
<div class="mb-8">
<h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Welcome Back</h1>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                        Please enter your details to access your dashboard.
                    </p>
</div>

<?php if (!empty($success_message)): ?>
<div class="mb-4 p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
<p class="text-sm text-emerald-700 dark:text-emerald-300"><?php echo htmlspecialchars($success_message); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($login_errors)): ?>
<div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
<?php foreach ($login_errors as $error): ?>
<p class="text-sm text-red-700 dark:text-red-300"><?php echo htmlspecialchars($error); ?></p>
<?php endforeach; ?>
</div>
<?php endif; ?>

<form action="../../php/login.php" class="space-y-6" method="POST">
<div>
<label class="block text-sm font-medium leading-6 text-slate-900 dark:text-white" for="email">Email address</label>
<div class="mt-2 relative rounded-lg shadow-sm">
<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
<span class="material-symbols-outlined text-slate-400" style="font-size: 20px;">mail</span>
</div>
<input class="block w-full rounded-lg border-0 py-3 pl-10 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-6 dark:bg-slate-800 dark:text-white dark:ring-slate-700 dark:placeholder:text-slate-500" id="email" name="email" placeholder="name@example.com" type="email" value="" required/>
</div>
</div>
<div>
<div class="flex items-center justify-between">
<label class="block text-sm font-medium leading-6 text-slate-900 dark:text-white" for="password">Password</label>
<div class="text-sm">
<a class="font-medium text-primary hover:text-primary/80" href="#">Forgot password?</a>
</div>
</div>
<div class="mt-2 relative rounded-lg shadow-sm">
<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
<span class="material-symbols-outlined text-slate-400" style="font-size: 20px;">lock</span>
</div>
<input class="block w-full rounded-lg border-0 py-3 pl-10 pr-10 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-6 dark:bg-slate-800 dark:text-white dark:ring-slate-700 dark:placeholder:text-slate-500" id="password" name="password" placeholder="••••••••" type="password" required/>
<div class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer" onclick="togglePassword()">
<span class="material-symbols-outlined text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" style="font-size: 20px;" id="password-toggle">visibility</span>
</div>
</div>
</div>
<div>
<button class="flex w-full justify-center rounded-lg bg-primary px-3 py-3 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition-colors" type="submit">
                            Sign in
                        </button>
</div>
</form>
<p class="mt-10 text-center text-sm text-slate-500 dark:text-slate-400">
                    New patient?
                    <a class="font-semibold leading-6 text-primary hover:text-primary/80 transition-colors" href="../registration_page/register.php">Register here</a>
                </p>
</div>
</div>
</div>
<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('password-toggle');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordToggle.textContent = 'visibility_off';
    } else {
        passwordInput.type = 'password';
        passwordToggle.textContent = 'visibility';
    }
}
</script>
</body></html>

