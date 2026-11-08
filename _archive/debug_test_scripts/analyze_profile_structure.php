<?php
// Deep Structure Analysis for profile.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Profile.php Deep Structure Analysis</h2>";
echo "<style>
body{font-family:monospace;font-size:12px;} 
.error{color:red;font-weight:bold;} 
.success{color:green;} 
.warning{color:orange;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #ccc;padding:8px;text-align:left;}
th{background:#f0f0f0;}
</style>";

$file = __DIR__ . '/profile.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "<h3>1. PHP Tag Analysis</h3>";
$php_opens = [];
$php_closes = [];
foreach ($lines as $num => $line) {
    if (preg_match('/<\?php/', $line)) {
        $php_opens[] = $num + 1;
    }
    if (preg_match('/\?>/', $line)) {
        $php_closes[] = $num + 1;
    }
}
echo "PHP opening tags: " . count($php_opens) . "<br>";
echo "PHP closing tags: " . count($php_closes) . "<br>";

echo "<h3>2. Control Structure Balance</h3>";
$if_stack = [];
$control_errors = [];

foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    
    // Track if statements
    if (preg_match('/<\?php\s+if\s*\([^:]*\):\s*\?>/', $line)) {
        $if_stack[] = ['type' => 'if', 'line' => $line_num, 'content' => trim($line)];
    }
    
    // Track else
    if (preg_match('/<\?php\s+else:\s*\?>/', $line)) {
        if (empty($if_stack)) {
            $control_errors[] = "Line $line_num: 'else' without matching 'if'";
        }
    }
    
    // Track endif
    if (preg_match('/<\?php\s+endif;\s*\?>/', $line)) {
        if (empty($if_stack)) {
            $control_errors[] = "Line $line_num: 'endif' without matching 'if'";
        } else {
            array_pop($if_stack);
        }
    }
}

if (!empty($if_stack)) {
    echo "<span class='error'>❌ Unclosed if statements:</span><br>";
    foreach ($if_stack as $item) {
        echo "&nbsp;&nbsp;Line {$item['line']}: {$item['content']}<br>";
    }
} else {
    echo "<span class='success'>✅ All if/endif balanced</span><br>";
}

if (!empty($control_errors)) {
    echo "<span class='error'>❌ Control structure errors:</span><br>";
    foreach ($control_errors as $error) {
        echo "&nbsp;&nbsp;$error<br>";
    }
}

echo "<h3>3. Div Tag Balance by Section</h3>";
$div_balance = 0;
$sections = [
    'Header' => [1, 410],
    'Main Grid' => [411, 438],
    'Personal Info Card' => [439, 675],
    'Medical History' => [676, 697],
    'Documents Section' => [698, 770],
    'Footer/Scripts' => [771, count($lines)]
];

echo "<table><tr><th>Section</th><th>Lines</th><th>&lt;div&gt;</th><th>&lt;/div&gt;</th><th>Balance</th></tr>";

foreach ($sections as $name => $range) {
    $opens = 0;
    $closes = 0;
    
    for ($i = $range[0] - 1; $i < min($range[1], count($lines)); $i++) {
        $opens += substr_count($lines[$i], '<div');
        $closes += substr_count($lines[$i], '</div>');
    }
    
    $balance = $opens - $closes;
    $status = $balance == 0 ? 'success' : 'error';
    
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>{$range[0]}-{$range[1]}</td>";
    echo "<td>$opens</td>";
    echo "<td>$closes</td>";
    echo "<td class='$status'>" . ($balance >= 0 ? "+$balance" : "$balance") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>4. Form Tag Balance</h3>";
$form_open = substr_count($content, '<form');
$form_close = substr_count($content, '</form>');
echo "Form opens: $form_open<br>";
echo "Form closes: $form_close<br>";
if ($form_open == $form_close) {
    echo "<span class='success'>✅ Forms balanced</span><br>";
} else {
    echo "<span class='error'>❌ Form tags mismatched!</span><br>";
}

echo "<h3>5. Script Tag Issues</h3>";
$in_script = false;
$script_issues = [];

foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    
    if (preg_match('/<script[^>]*>/', $line)) {
        $in_script = true;
    }
    
    if ($in_script && preg_match('/<\?php/', $line)) {
        $script_issues[] = "Line $line_num: PHP tag inside script block";
    }
    
    if (preg_match('/<\/script>/', $line)) {
        $in_script = false;
    }
}

if (empty($script_issues)) {
    echo "<span class='success'>✅ No PHP/JS mixing detected</span><br>";
} else {
    echo "<span class='error'>❌ Script tag issues found:</span><br>";
    foreach ($script_issues as $issue) {
        echo "&nbsp;&nbsp;$issue<br>";
    }
}

echo "<h3>6. Critical Lines to Review</h3>";
$critical_lines = [473, 599, 600, 601, 602, 672, 673, 674, 809, 810, 811, 817];
echo "<table><tr><th>Line</th><th>Content</th></tr>";
foreach ($critical_lines as $line_num) {
    if (isset($lines[$line_num - 1])) {
        $content = htmlspecialchars(trim($lines[$line_num - 1]));
        echo "<tr><td>$line_num</td><td>$content</td></tr>";
    }
}
echo "</table>";

echo "<h3>Summary</h3>";
echo "Total lines: " . count($lines) . "<br>";
echo "File size: " . filesize($file) . " bytes<br>";
echo "<br><strong>Run this analysis to identify specific structural issues before making fixes.</strong>";
?>
