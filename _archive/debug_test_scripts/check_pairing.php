<?php
$file = 'profile.php';
$lines = file($file);

echo "<h2>Current IF/ENDIF Count</h2>";
echo "<style>body{font-family:monospace;} .error{color:red;} .success{color:green;}</style>";

$if_count = 0;
$endif_count = 0;
$if_lines = [];
$endif_lines = [];

foreach ($lines as $num => $line) {
    if (preg_match('/<\?php\s+if\s*\([^)]+\)\s*:\s*\?>/', $line)) {
        $if_count++;
        $if_lines[] = ($num + 1) . ": " . trim($line);
    }
    if (preg_match('/<\?php\s+endif;\s*\?>/', $line)) {
        $endif_count++;
        $endif_lines[] = ($num + 1) . ": " . trim($line);
    }
}

echo "<strong>IF statements (colon syntax):</strong> $if_count<br>";
echo "<strong>ENDIF statements:</strong> $endif_count<br>";
echo "<strong>Difference:</strong> " . ($if_count - $endif_count) . "<br><br>";

if ($if_count > $endif_count) {
    echo "<span class='error'>❌ MISSING " . ($if_count - $endif_count) . " ENDIF(S)</span><br><br>";
} elseif ($endif_count > $if_count) {
    echo "<span class='error'>❌ EXTRA " . ($endif_count - $if_count) . " ENDIF(S)</span><br><br>";
} else {
    echo "<span class='success'>✅ IF and ENDIF counts match</span><br><br>";
}

echo "<h3>All IF statements:</h3><pre>";
foreach ($if_lines as $line) echo htmlspecialchars($line) . "\n";
echo "</pre>";

echo "<h3>All ENDIF statements:</h3><pre>";
foreach ($endif_lines as $line) echo htmlspecialchars($line) . "\n";
echo "</pre>";

// Manual pairing
echo "<h3>Pairing Analysis:</h3>";
$stack = [];
foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    if (preg_match('/<\?php\s+if\s*\([^)]+\)\s*:\s*\?>/', $line)) {
        $stack[] = $line_num;
        echo "Line $line_num: OPEN IF (stack depth: " . count($stack) . ")<br>";
    }
    if (preg_match('/<\?php\s+endif;\s*\?>/', $line)) {
        if (empty($stack)) {
            echo "<span class='error'>Line $line_num: ENDIF without matching IF!</span><br>";
        } else {
            $matched = array_pop($stack);
            echo "Line $line_num: CLOSE IF from line $matched (stack depth: " . count($stack) . ")<br>";
        }
    }
}

if (!empty($stack)) {
    echo "<br><span class='error'>❌ UNCLOSED IF statements:</span><br>";
    foreach ($stack as $line) {
        echo "Line $line<br>";
    }
}
?>
