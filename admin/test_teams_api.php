<?php
// Test the volleyball teams API
session_start();

// Set session for testing
$_SESSION['selected_volleyball_season_id'] = 1;

echo "<h2>Testing Volleyball Teams API</h2>";

// Test with division ID 1
$division_id = 1;
$url = "get_volleyball_teams.php?division_id=$division_id";

echo "<p><strong>Testing URL:</strong> $url</p>";

// Simulate the AJAX call
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/$url");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo "<p style='color: red;'>❌ Failed to connect to API</p>";
    echo "<p>This is expected if no web server is running.</p>";
} else {
    echo "<p style='color: green;'>✅ API Response (HTTP $http_code):</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}

// Also test direct file execution
echo "<h3>Direct File Execution Test:</h3>";
echo "<p>Running get_volleyball_teams.php directly...</p>";

// Capture output
ob_start();
$_GET['division_id'] = $division_id;
include 'get_volleyball_teams.php';
$output = ob_get_clean();

echo "<p style='color: green;'>✅ Direct execution result:</p>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Parse JSON to see what teams are returned
$teams = json_decode($output, true);
if ($teams) {
    echo "<h3>Teams Found:</h3>";
    if (count($teams) > 0) {
        foreach ($teams as $team) {
            echo "<p>• " . htmlspecialchars($team['name']) . " (ID: {$team['id']})</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No teams found for division $division_id</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Invalid JSON response</p>";
}
?>
