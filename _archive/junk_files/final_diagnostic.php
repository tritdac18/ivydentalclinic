<?php
// Simple test: does the file even parse?
$file = 'profile.php';
$output = shell_exec("php -l $file 2>&1");
echo "<h2>PHP Syntax Check</h2>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Count structures
$content = file_get_contents($file);
$lines = file($file);

echo "<h3>Quick Stats:</h3>";
echo "Total lines: " . count($lines) . "<br>";
echo "File size: " . strlen($content) . " bytes<br>";

// Count tags
$div_open = 0;
$div_close = 0;
$if_colon = 0;
$endif = 0;

foreach ($lines as $line) {
    $div_open += substr_count($line, '<div');
    $div_close += substr_count($line, '</div>');
    if (preg_match('/<\?php\s+if.*:\s*\?>/', $line)) $if_colon++;
    if (preg_match('/<\?php\s+endif/', $line)) $endif++;
}

echo "<br><strong>DIV tags:</strong> $div_open open, $div_close close (diff: " . ($div_open - $div_close) . ")<br>";
echo "<strong>IF/ENDIF:</strong> $if_colon if statements, $endif endifs (diff: " . ($if_colon - $endif) . ")<br>";

if ($div_open != $div_close || $if_colon != $endif) {
    echo "<br><span style='color:red;font-weight:bold;'>❌ STRUCTURAL IMBALANCE DETECTED</span><br>";
    echo "<p>Recommendation: <strong>Restore from a working backup or rebuild the file</strong></p>";
} else {
    echo "<br><span style='color:green;font-weight:bold;'>✅ Tag counts balanced</span>";
}
?>
