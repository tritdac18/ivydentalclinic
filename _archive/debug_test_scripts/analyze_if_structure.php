<?php
// Find ALL if statements (both syntaxes)
$file = 'profile.php';
$lines = file($file);

echo "<h2>Complete IF/ENDIF Analysis</h2>";
echo "<style>body{font-family:monospace;font-size:11px;} .error{color:red;} .success{color:green;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:4px;text-align:left;font-size:11px;}</style>";

$structures = [];

foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    $trimmed = trim($line);
    
    // Find if with colon
    if (preg_match('/<\?php\s+if\s*\([^)]+\)\s*:\s*\?>/', $line)) {
        $structures[] = ['line' => $line_num, 'type' => 'if:', 'code' => $trimmed];
    }
    // Find if with brace
    elseif (preg_match('/<\?php\s+if\s*\([^)]+\)\s*\{/', $line)) {
        $structures[] = ['line' => $line_num, 'type' => 'if{', 'code' => $trimmed];
    }
    // Find else with colon
    elseif (preg_match('/<\?php\s+else\s*:\s*\?>/', $line)) {
        $structures[] = ['line' => $line_num, 'type' => 'else:', 'code' => $trimmed];
    }
    // Find elseif with colon
    elseif (preg_match('/<\?php\s+elseif\s*\([^)]+\)\s*:\s*\?>/', $line)) {
        $structures[] = ['line' => $line_num, 'type' => 'elseif:', 'code' => $trimmed];
    }
    // Find endif
    elseif (preg_match('/<\?php\s+endif;\s*\?>/', $line)) {
        $structures[] = ['line' => $line_num, 'type' => 'endif', 'code' => $trimmed];
    }
    // Find closing brace
    elseif (preg_match('/^\s*\}\s*$/', $line) || preg_match('/<\?php\s*\}\s*\?>/', $line)) {
        $structures[] = ['line' => $line_num, 'type' => '}', 'code' => $trimmed];
    }
}

echo "<table><tr><th>Line</th><th>Type</th><th>Code</th></tr>";
foreach ($structures as $s) {
    $color = ($s['type'] == 'if:' || $s['type'] == 'if{') ? '#e3f2fd' : (($s['type'] == 'endif' || $s['type'] == '}') ? '#f3e5f5' : '#fff3e0');
    echo "<tr style='background:{$color}'><td>{$s['line']}</td><td><strong>{$s['type']}</strong></td><td>" . htmlspecialchars(substr($s['code'], 0, 100)) . "</td></tr>";
}
echo "</table>";

// Stack analysis
echo "<br><h3>Stack Analysis</h3>";
$stack = [];
$errors = [];

foreach ($structures as $s) {
    if ($s['type'] == 'if:' || $s['type'] == 'if{') {
        $stack[] = $s;
    } elseif ($s['type'] == 'else:' || $s['type'] == 'elseif:') {
        // These don't change stack depth
    } elseif ($s['type'] == 'endif') {
        if (empty($stack)) {
            $errors[] = "Line {$s['line']}: endif without matching if";
        } else {
            $last = array_pop($stack);
            if ($last['type'] == 'if{') {
                $errors[] = "Line {$s['line']}: endif used to close if{ from line {$last['line']} (should use })";
            }
        }
    } elseif ($s['type'] == '}') {
        if (!empty($stack)) {
            $last = end($stack);
            if ($last['type'] == 'if{') {
                array_pop($stack);
            }
        }
    }
}

if (!empty($errors)) {
    echo "<span class='error'>Errors found:</span><br>";
    foreach ($errors as $err) {
        echo "- $err<br>";
    }
}

if (!empty($stack)) {
    echo "<br><span class='error'>❌ UNCLOSED structures at end of file:</span><br>";
    foreach ($stack as $s) {
        echo "Line {$s['line']}: {$s['type']} - " . htmlspecialchars(substr($s['code'], 0, 80)) . "<br>";
    }
} else {
    echo "<br><span class='success'>✅ All structures properly closed</span>";
}
?>
