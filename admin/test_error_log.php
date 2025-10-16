<?php
// Test script to check if error logging is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Error Log Test</h2>";

// Test error logging
error_log("TEST: This is a test error log message at " . date('Y-m-d H:i:s'));

echo "<p>Test error message sent to log at " . date('Y-m-d H:i:s') . "</p>";

// Check if we can write to a test file
$test_file = 'test_log.txt';
$test_message = "Test log message at " . date('Y-m-d H:i:s') . "\n";

if (file_put_contents($test_file, $test_message, FILE_APPEND)) {
    echo "<p>✓ Successfully wrote to test file: $test_file</p>";
    echo "<p>Check this file for the test message.</p>";
} else {
    echo "<p>✗ Could not write to test file</p>";
}

// Show current error log settings
echo "<h3>Current Error Log Settings:</h3>";
echo "<p><strong>error_log:</strong> " . ini_get('error_log') . "</p>";
echo "<p><strong>log_errors:</strong> " . (ini_get('log_errors') ? 'ON' : 'OFF') . "</p>";
echo "<p><strong>display_errors:</strong> " . (ini_get('display_errors') ? 'ON' : 'OFF') . "</p>";

// Try to read the error log if it exists
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    echo "<h3>Recent Error Log Entries:</h3>";
    $lines = file($error_log_path);
    $recent_lines = array_slice($lines, -10); // Last 10 lines
    echo "<pre>";
    foreach ($recent_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<p>Error log file not found at: $error_log_path</p>";
}

echo "<h3>Next Steps:</h3>";
echo "<p>1. Check if the test message appears in your error log</p>";
echo "<p>2. Look for files named 'error_log' or 'error.log' in your website directory</p>";
echo "<p>3. Check your hosting control panel for error logs</p>";
?>

