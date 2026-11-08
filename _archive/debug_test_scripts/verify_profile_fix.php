<?php
// Quick verification script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Profile.php Quick Verification</h2>";
echo "<style>body{font-family:monospace;} .error{color:red;} .success{color:green;}</style>";

$file = __DIR__ . '/profile.php';
$content = file_get_contents($file);

// Count tags
$div_open = substr_count($content, '<div');
$div_close = substr_count($content, '</div>');
$form_open = substr_count($content, '<form');
$form_close = substr_count($content, '</form>');

echo "<h3>Tag Balance Check</h3>";
echo "Div tags: $div_open opening, $div_close closing ";
if ($div_open == $div_close) {
    echo "<span class='success'>✅ BALANCED</span><br>";
} else {
    echo "<span class='error'>❌ IMBALANCED (diff: " . ($div_open - $div_close) . ")</span><br>";
}

echo "Form tags: $form_open opening, $form_close closing ";
if ($form_open == $form_close) {
    echo "<span class='success'>✅ BALANCED</span><br>";
} else {
    echo "<span class='error'>❌ IMBALANCED</span><br>";
}

// Check for PHP in script
$lines = explode("\n", $content);
$in_script = false;
$php_in_script = false;

foreach ($lines as $num => $line) {
    if (preg_match('/<script[^>]*>/', $line) && !preg_match('/src=/', $line)) {
        $in_script = true;
    }
    if ($in_script && preg_match('/<\?php/', $line)) {
        $php_in_script = true;
        break;
    }
    if (preg_match('/<\/script>/', $line)) {
        $in_script = false;
    }
}

echo "<h3>Script Tag Check</h3>";
if (!$php_in_script) {
    echo "<span class='success'>✅ No PHP inside script blocks</span><br>";
} else {
    echo "<span class='error'>❌ PHP found inside script block</span><br>";
}

echo "<h3>Next Step</h3>";
echo "Try loading <a href='profile.php'>profile.php</a> now!";
?>
