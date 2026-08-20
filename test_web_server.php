<?php
// Simple test to verify web server is working
echo "<h1>Web Server Test</h1>";
echo "<p>✅ PHP is working!</p>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Document root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Test the volleyball teams API
echo "<h2>Testing Volleyball Teams API</h2>";
echo "<p>Testing: <a href='admin/get_volleyball_teams.php?division_id=1'>admin/get_volleyball_teams.php?division_id=1</a></p>";
?>

