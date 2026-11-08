<?php
/**
 * views/layout/header.php
 *
 * Shared HTML <head> + opening <body> tag for ALL authenticated pages
 * (staff and patient portals).
 *
 * Required variables (set by including page before this include):
 *   $page_title  — string, e.g. 'Dashboard' or 'Appointments'
 *
 * Optional:
 *   $extra_head  — string of extra <link> / <script> tags to inject in <head>
 *
 * Usage at the top of any page, after all PHP logic:
 *
 *   $page_title = 'Appointments';
 *   include 'views/layout/header.php';
 */

$page_title = $page_title ?? 'Ivy Dental Clinic';
$extra_head = $extra_head ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<?php outputCachePreventionMeta(); ?>
<title><?php echo e($page_title); ?> – Ivy Dental Clinic</title>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

<!-- Tailwind CSS (CDN — swap for compiled CSS in production) -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary":           "#136dec",
                    "primary-dark":      "#0b4cb4",
                    "accent":            "#10b981",
                    "background-light":  "#f6f7f8",
                    "background-dark":   "#101822",
                    "surface-light":     "#ffffff",
                    "surface-dark":      "#1e293b",
                    "border-light":      "#e2e8f0",
                    "border-dark":       "#334155",
                    "text-main":         "#0f172a",
                    "text-muted":        "#64748b",
                },
                fontFamily: { "display": ["Inter", "sans-serif"] },
                borderRadius: {
                    "DEFAULT": "0.5rem", "lg": "0.75rem",
                    "xl": "1rem", "full": "9999px"
                },
            },
        },
    }
</script>

<?php echo $extra_head; ?>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#0d131b] dark:text-slate-100 antialiased min-h-screen flex">

