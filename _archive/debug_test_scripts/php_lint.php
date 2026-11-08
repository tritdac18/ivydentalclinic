<?php
$file = 'profile.php';
$content = file_get_contents($file);

// Try to actually parse the file
echo "<h2>PHP Syntax Check</h2>";
echo "<style>body{font-family:monospace;font-size:12px;} .error{color:red;font-weight:bold;} .success{color:green;font-weight:bold;}</style>";

// Use php -l to check syntax
$output = [];
$return_var = 0;
exec("php -l profile.php 2>&1", $output, $return_var);

echo "<h3>PHP Lint Output:</h3><pre>";
echo htmlspecialchars(implode("\n", $output));
echo "</pre>";

if ($return_var === 0) {
    echo "<span class='success'>✅ No syntax errors!</span>";
} else {
    echo "<span class='error'>❌ Syntax errors found</span>";
}

// Also check for common issues
echo "<br><br><h3>Additional Checks:</h3>";

// Count PHP open/close tags
$php_open = substr_count($content, '<?php');
$php_close = substr_count($content, '?>');
echo "PHP open tags: $php_open<br>";
echo "PHP close tags: $php_close<br>";

// Check for unclosed strings
$lines = explode("\n", $content);
$in_php = false;
$suspicious_lines = [];

foreach ($lines as $num => $line) {
    if (strpos($line, '<?php') !== false) $in_php = true;
    if (strpos($line, '?>') !== false) $in_php = false;
    
    // Check for odd number of quotes in PHP sections
    if ($in_php) {
        $single_quotes = substr_count($line, "'") - substr_count($line, "\\'");
        $double_quotes = substr_count($line, '"') - substr_count($line, '\\"');
        
        if ($single_quotes % 2 != 0 || $double_quotes % 2 != 0) {
            $suspicious_lines[] = ($num + 1) . ": " . trim($line);
        }
    }
}

if (!empty($suspicious_lines)) {
    echo "<br><strong>Lines with potentially unclosed strings:</strong><br>";
    foreach (array_slice($suspicious_lines, 0, 10) as $line) {
        echo htmlspecialchars($line) . "<br>";
    }
}
?>
