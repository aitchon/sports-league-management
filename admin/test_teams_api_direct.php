<?php
// Test the volleyball teams API directly
session_start();
include 'db.php';

echo "<h2>Volleyball Teams API Test</h2>";

// Set session for testing
$_SESSION['selected_volleyball_season_id'] = 1;

echo "<h3>Testing API directly:</h3>";

// Test with division ID 2 (from your console log)
$division_id = 2;
$_GET['division_id'] = $division_id;

echo "<p>Testing division ID: $division_id</p>";

// Capture output
ob_start();
include 'get_volleyball_teams.php';
$output = ob_get_clean();

echo "<p><strong>API Response:</strong></p>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Parse JSON
$teams = json_decode($output, true);
if ($teams) {
    if (isset($teams['error'])) {
        echo "<p style='color: red;'>❌ API Error: " . htmlspecialchars($teams['error']) . "</p>";
    } else {
        echo "<p style='color: green;'>✅ API Success</p>";
        echo "<p><strong>Teams found:</strong> " . count($teams) . "</p>";
        foreach ($teams as $team) {
            echo "<p>• " . htmlspecialchars($team['name']) . " (ID: {$team['id']})</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Invalid JSON response</p>";
}

// Also check database directly
echo "<h3>Database Check:</h3>";
$result = $conn->query("SELECT * FROM volleyball_teams WHERE division_id = $division_id AND season_id = 1");
if ($result && $result->num_rows > 0) {
    echo "<p><strong>Teams in database for division $division_id:</strong></p>";
    while ($team = $result->fetch_assoc()) {
        echo "<p>• " . htmlspecialchars($team['name']) . " (ID: {$team['id']}, Season: {$team['season_id']})</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ No teams found in database for division $division_id</p>";
}
?>
