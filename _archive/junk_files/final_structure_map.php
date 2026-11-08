<?php
// Map every single if/else/endif with line numbers
$lines = file('profile.php');
echo "<h2>Complete Control Structure Map</h2>";
echo "<style>body{font-family:monospace;font-size:11px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:3px;} .if{background:#e3f2fd;} .else{background:#fff3e0;} .endif{background:#f3e5f5;} .error{background:#ffebee;color:red;font-weight:bold;}</style>";

$structures = [];
foreach ($lines as $num => $line) {
    $ln = $num + 1;
    if (preg_match('/<\?php\s+if\s*\([^)]+\)\s*:\s*\?>/', $line)) {
        $structures[] = ['line' => $ln, 'type' => 'IF', 'code' => trim($line)];
    } elseif (preg_match('/<\?php\s+else:\s*\?>/', $line)) {
        $structures[] = ['line' => $ln, 'type' => 'ELSE', 'code' => trim($line)];
    } elseif (preg_match('/<\?php\s+endif;\s*\?>/', $line)) {
        $structures[] = ['line' => $ln, 'type' => 'ENDIF', 'code' => trim($line)];
    }
}

echo "<table><tr><th>#</th><th>Line</th><th>Type</th><th>Stack</th><th>Code</th></tr>";
$stack = [];
$idx = 0;
foreach ($structures as $s) {
    $idx++;
    $class = strtolower($s['type']);
    $error = '';
    
    if ($s['type'] == 'IF') {
        $stack[] = $s['line'];
        $depth = count($stack);
    } elseif ($s['type'] == 'ELSE') {
        $depth = count($stack);
    } elseif ($s['type'] == 'ENDIF') {
        if (empty($stack)) {
            $error = ' error';
            $depth = "ERROR!";
        } else {
            array_pop($stack);
            $depth = count($stack) + 1;
        }
    }
    
    echo "<tr class='$class$error'><td>$idx</td><td>{$s['line']}</td><td><strong>{$s['type']}</strong></td><td>$depth</td><td>" . htmlspecialchars(substr($s['code'], 0, 80)) . "</td></tr>";
}
echo "</table>";

if (!empty($stack)) {
    echo "<br><span style='color:red;font-weight:bold;'>❌ UNCLOSED IF at lines: " . implode(', ', $stack) . "</span>";
} else {
    echo "<br><span style='color:green;font-weight:bold;'>✅ All IF statements closed</span>";
}
?>
