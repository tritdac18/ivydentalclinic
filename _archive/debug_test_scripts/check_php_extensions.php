<?php
/**
 * Check PHP Extensions
 * Run this to see what PHP extensions are available
 */

echo "PHP Extensions Check\n";
echo "====================\n\n";

$required = ['zip', 'curl', 'openssl', 'json', 'mbstring'];
$optional = ['gd', 'xml', 'dom'];

echo "Required Extensions:\n";
foreach ($required as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? "✓ ENABLED" : "✗ MISSING";
    echo "  $ext: $status\n";
}

echo "\nOptional Extensions:\n";
foreach ($optional as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? "✓ ENABLED" : "⚠ MISSING (optional)";
    echo "  $ext: $status\n";
}

echo "\nPHP Version: " . phpversion() . "\n";
echo "PHP INI File: " . php_ini_loaded_file() . "\n";

echo "\nAll Loaded Extensions:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo "  - $ext\n";
}

