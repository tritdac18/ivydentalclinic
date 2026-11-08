<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Profile.php Error Diagnosis</h2>";
echo "<style>body{font-family:monospace;font-size:12px;} .error{color:red;} .success{color:green;} pre{background:#f5f5f5;padding:10px;border:1px solid #ccc;overflow-x:auto;}</style>";

// Try to include and run profile.php to catch the error
ob_start();
try {
    include 'profile.php';
    $output = ob_get_clean();
    echo "<span class='success'>✅ Profile.php loaded successfully!</span><br>";
    echo "<p>If you see this, the page should work. Try accessing profile.php directly.</p>";
} catch (Throwable $e) {
    ob_end_clean();
    echo "<span class='error'>❌ Fatal Error Caught:</span><br><br>";
    echo "<strong>Error Type:</strong> " . get_class($e) . "<br>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br><br>";
    
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    
    // Show the problematic line
    $file = file($e->getFile());
    $line_num = $e->getLine();
    $start = max(0, $line_num - 5);
    $end = min(count($file), $line_num + 5);
    
    echo "<br><strong>Code Context (lines " . ($start + 1) . "-" . ($end) . "):</strong><br>";
    echo "<pre>";
    for ($i = $start; $i < $end; $i++) {
        $marker = ($i + 1 == $line_num) ? ">>> " : "    ";
        echo htmlspecialchars(sprintf("%s%4d: %s", $marker, $i + 1, $file[$i]));
    }
    echo "</pre>";
}
?>
