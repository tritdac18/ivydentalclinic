<?php
// Count all if/endif pairs
$file = 'profile.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "<h2>PHP Control Structure Analysis</h2>";
echo "<style>body{font-family:monospace;font-size:12px;} .error{color:red;} .success{color:green;} table{border-collapse:collapse;} th,td{border:1px solid #ccc;padding:5px;text-align:left;}</style>";

$if_stack = [];
$all_ifs = [];
$all_endifs = [];

foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    
    // Find if statements with colon syntax
    if (preg_match('/<\?php\s+if\s*\([^)]+\)\s*:\s*\?>/', $line, $matches)) {
        $if_stack[] = ['line' => $line_num, 'content' => trim($line)];
        $all_ifs[] = $line_num . ": " . trim($line);
    }
    
    // Find endif statements
    if (preg_match('/<\?php\s+endif;\s*\?>/', $line)) {
        $all_endifs[] = $line_num . ": " . trim($line);
        if (!empty($if_stack)) {
            array_pop($if_stack);
        }
    }
}

echo "<h3>Summary</h3>";
echo "Total 'if' statements (colon syntax): <strong>" . count($all_ifs) . "</strong><br>";
echo "Total 'endif' statements: <strong>" . count($all_endifs) . "</strong><br>";
echo "Difference: <strong>" . (count($all_ifs) - count($all_endifs)) . "</strong><br><br>";

if (!empty($if_stack)) {
    echo "<span class='error'>❌ UNCLOSED IF STATEMENTS:</span><br><br>";
    echo "<table><tr><th>Line</th><th>Code</th></tr>";
    foreach ($if_stack as $item) {
        echo "<tr><td>{$item['line']}</td><td>" . htmlspecialchars($item['content']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<span class='success'>✅ All if statements are closed</span>";
}

echo "<br><br><h3>All IF statements:</h3><pre>";
foreach ($all_ifs as $if) {
    echo htmlspecialchars($if) . "\n";
}
echo "</pre>";

echo "<h3>All ENDIF statements:</h3><pre>";
foreach ($all_endifs as $endif) {
    echo htmlspecialchars($endif) . "\n";
}
echo "</pre>";
?>
