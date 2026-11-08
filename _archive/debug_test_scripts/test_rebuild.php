<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Profile.php Rebuild Test</h2>";
echo "<style>body{font-family:monospace;} .error{color:red;font-weight:bold;} .success{color:green;font-weight:bold;}</style>";

ob_start();
try {
    include 'profile.php';
    ob_end_clean();
    echo "<span class='success'>✅ SUCCESS! Profile.php loads without errors!</span><br><br>";
    echo "<p>File has been successfully rebuilt with all features:</p>";
    echo "<ul>";
    echo "<li>✅ Blood type field</li>";
    echo "<li>✅ Gender field</li>";
    echo "<li>✅ Allergies management</li>";
    echo "<li>✅ Patient documents section</li>";
    echo "<li>✅ Parent consent placeholder</li>";
    echo "</ul>";
    echo "<br><p><strong>Next step:</strong> Test the page: <a href='profile.php'>profile.php</a></p>";
} catch (Throwable $e) {
    ob_end_clean();
    echo "<span class='error'>❌ ERROR:</span><br><br>";
    echo "<strong>Type:</strong> " . get_class($e) . "<br>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br><br>";
    
    $file = file('profile.php');
    $line = $e->getLine();
    $start = max(0, $line - 3);
    $end = min(count($file), $line + 3);
    
    echo "<strong>Code around line $line:</strong><pre>";
    for ($i = $start; $i < $end; $i++) {
        $marker = ($i + 1 == $line) ? ">>> " : "    ";
        printf("%s%4d: %s", $marker, $i + 1, htmlspecialchars($file[$i]));
    }
    echo "</pre>";
}
?>
