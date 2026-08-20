<?php
// Diagnostic script to check file locations and paths
echo "<h2>Volleyball Teams API Diagnostic</h2>";

echo "<h3>Current File Information:</h3>";
echo "<p><strong>Current file:</strong> " . __FILE__ . "</p>";
echo "<p><strong>Current directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Document root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";

echo "<h3>File Existence Check:</h3>";
$files_to_check = [
    'get_volleyball_teams.php',
    'admin/get_volleyball_teams.php',
    '../admin/get_volleyball_teams.php',
    './get_volleyball_teams.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ Found: $file</p>";
    } else {
        echo "<p style='color: red;'>❌ Not found: $file</p>";
    }
}

echo "<h3>Directory Listing:</h3>";
echo "<p><strong>Current directory contents:</strong></p>";
$files = scandir('.');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $type = is_dir($file) ? '[DIR]' : '[FILE]';
        echo "<p>$type $file</p>";
    }
}

echo "<h3>Test API Call:</h3>";
echo "<p>Testing different paths:</p>";

// Test 1: Relative path
echo "<p><strong>Test 1 - Relative path:</strong></p>";
echo "<p><a href='get_volleyball_teams.php?division_id=1' target='_blank'>get_volleyball_teams.php?division_id=1</a></p>";

// Test 2: Admin path
echo "<p><strong>Test 2 - Admin path:</strong></p>";
echo "<p><a href='admin/get_volleyball_teams.php?division_id=1' target='_blank'>admin/get_volleyball_teams.php?division_id=1</a></p>";

// Test 3: Direct execution
echo "<p><strong>Test 3 - Direct execution:</strong></p>";
if (file_exists('get_volleyball_teams.php')) {
    echo "<p>✅ File exists, testing direct execution...</p>";
    $_GET['division_id'] = 1;
    ob_start();
    include 'get_volleyball_teams.php';
    $output = ob_get_clean();
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ File not found for direct execution</p>";
}

echo "<h3>Web Server Configuration:</h3>";
echo "<p><strong>Server software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>Server name:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) ? 'Yes' : 'No') . "</p>";
?>
