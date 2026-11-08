<?php
// Advanced Profile Debug Script
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<h2>Advanced Profile.php Debug Report</h2>";
echo "<style>body{font-family:monospace;} .error{color:red;} .success{color:green;} .warning{color:orange;}</style>";

// 1. Check if profile.php exists and is readable
echo "<h3>1. File Check</h3>";
$profile_path = __DIR__ . '/profile.php';
if (file_exists($profile_path)) {
    echo "<span class='success'>✅ profile.php exists</span><br>";
    echo "File size: " . filesize($profile_path) . " bytes<br>";
    echo "Last modified: " . date("Y-m-d H:i:s", filemtime($profile_path)) . "<br>";
} else {
    echo "<span class='error'>❌ profile.php NOT FOUND</span><br>";
}

// 2. PHP Syntax Check (simulate)
echo "<h3>2. PHP Syntax Analysis</h3>";
$content = file_get_contents($profile_path);

// Count PHP tags
$php_open = substr_count($content, '<?php');
$php_close = substr_count($content, '?>');
echo "PHP opening tags (<?php): <strong>$php_open</strong><br>";
echo "PHP closing tags (?>): <strong>$php_close</strong><br>";

// Count control structures
$if_count = preg_match_all('/\bif\s*\(/i', $content);
$endif_count = substr_count($content, 'endif');
$else_count = substr_count($content, 'else:');
$elseif_count = substr_count($content, 'elseif');

echo "if statements: <strong>$if_count</strong><br>";
echo "endif statements: <strong>$endif_count</strong><br>";
echo "else: statements: <strong>$else_count</strong><br>";
echo "elseif statements: <strong>$elseif_count</strong><br>";

// 3. Check for common duplications
echo "<h3>3. Duplication Check</h3>";

// Check for duplicate function definitions
preg_match_all('/function\s+(\w+)\s*\(/i', $content, $functions);
$func_names = $functions[1];
$func_counts = array_count_values($func_names);
$duplicates = array_filter($func_counts, function($count) { return $count > 1; });

if (empty($duplicates)) {
    echo "<span class='success'>✅ No duplicate function definitions found</span><br>";
} else {
    echo "<span class='error'>❌ Duplicate functions found:</span><br>";
    foreach ($duplicates as $func => $count) {
        echo "&nbsp;&nbsp;- $func (appears $count times)<br>";
    }
}

// Check for duplicate form tags
$form_open = substr_count($content, '<form');
$form_close = substr_count($content, '</form>');
echo "Form opening tags: <strong>$form_open</strong><br>";
echo "Form closing tags: <strong>$form_close</strong><br>";
if ($form_open !== $form_close) {
    echo "<span class='error'>❌ Mismatched form tags!</span><br>";
} else {
    echo "<span class='success'>✅ Form tags balanced</span><br>";
}

// 4. Check for specific problematic patterns
echo "<h3>4. Pattern Analysis</h3>";

// Check for nested PHP tags
if (preg_match('/<\?php[^?]*<\?php/s', $content)) {
    echo "<span class='error'>❌ Nested PHP tags detected!</span><br>";
} else {
    echo "<span class='success'>✅ No nested PHP tags</span><br>";
}

// Check for if/else/endif structure
$lines = explode("\n", $content);
$php_blocks = [];
$in_php = false;
$line_num = 0;

foreach ($lines as $line) {
    $line_num++;
    if (preg_match('/<\?php\s+if\s*\(\$edit_mode\):/i', $line)) {
        echo "Found 'if (\$edit_mode):' at line <strong>$line_num</strong><br>";
    }
    if (preg_match('/<\?php\s+else:\s*\?>/i', $line)) {
        echo "Found 'else:' at line <strong>$line_num</strong><br>";
    }
    if (preg_match('/<\?php\s+endif;\s*\?>/i', $line)) {
        echo "Found 'endif;' at line <strong>$line_num</strong><br>";
    }
}

// 5. Check div balance in key sections
echo "<h3>5. HTML Structure Check</h3>";
$div_open = substr_count($content, '<div');
$div_close = substr_count($content, '</div>');
echo "Total &lt;div&gt; tags: <strong>$div_open</strong><br>";
echo "Total &lt;/div&gt; tags: <strong>$div_close</strong><br>";

if ($div_open !== $div_close) {
    echo "<span class='error'>❌ Mismatched div tags! Difference: " . abs($div_open - $div_close) . "</span><br>";
} else {
    echo "<span class='success'>✅ Div tags balanced</span><br>";
}

// 6. Database connection test
echo "<h3>6. Database Connection</h3>";
try {
    require_once 'config/db.php';
    $conn = getDBConnection();
    if ($conn->connect_error) {
        echo "<span class='error'>❌ Connection failed: " . $conn->connect_error . "</span><br>";
    } else {
        echo "<span class='success'>✅ Database connected successfully</span><br>";
        $conn->close();
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Exception: " . $e->getMessage() . "</span><br>";
}

// 7. Session check
echo "<h3>7. Session Check</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    echo "<span class='success'>✅ Session active - User ID: " . $_SESSION['user_id'] . "</span><br>";
    echo "User type: " . ($_SESSION['user_type'] ?? 'not set') . "<br>";
} else {
    echo "<span class='warning'>⚠️ No active session (this is expected if not logged in)</span><br>";
}

// 8. Check for specific line ranges that were edited
echo "<h3>8. Critical Section Line Numbers</h3>";
$line_num = 0;
foreach ($lines as $line) {
    $line_num++;
    
    // Check around line 473 (if edit_mode)
    if ($line_num >= 470 && $line_num <= 476) {
        if (preg_match('/<\?php\s+if\s*\(\$edit_mode\):/i', $line)) {
            echo "Line $line_num: <span class='success'>✅ Edit mode check found</span><br>";
        }
    }
    
    // Check around line 599-602 (form closing and else)
    if ($line_num >= 595 && $line_num <= 605) {
        $trimmed = trim($line);
        if (!empty($trimmed)) {
            echo "Line $line_num: " . htmlspecialchars(substr($trimmed, 0, 80)) . "<br>";
        }
    }
}

echo "<hr><h3>Summary</h3>";
echo "If you see errors above, the profile.php file has structural issues.<br>";
echo "If all checks pass, the issue might be in the PHP logic or database queries.<br>";
echo "<br><strong>Next step:</strong> Check your web server error logs for the exact error message.";
?>
