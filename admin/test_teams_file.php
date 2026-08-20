<?php
// Simple test to check if get_volleyball_teams.php exists and works
echo "<h2>Volleyball Teams API Test</h2>";

echo "<h3>File Check:</h3>";
if (file_exists('get_volleyball_teams.php')) {
    echo "<p style='color: green;'>✅ get_volleyball_teams.php exists</p>";
    
    echo "<h3>Direct Test:</h3>";
    $_GET['division_id'] = 1;
    ob_start();
    include 'get_volleyball_teams.php';
    $output = ob_get_clean();
    
    echo "<p><strong>Raw output:</strong></p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $teams = json_decode($output, true);
    if ($teams) {
        echo "<p style='color: green;'>✅ Valid JSON response</p>";
        echo "<p><strong>Teams found:</strong> " . count($teams) . "</p>";
        foreach ($teams as $team) {
            echo "<p>• " . htmlspecialchars($team['name']) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON</p>";
    }
} else {
    echo "<p style='color: red;'>❌ get_volleyball_teams.php does not exist</p>";
    echo "<p>You need to upload this file to production!</p>";
}

echo "<h3>URL Test:</h3>";
echo "<p><a href='get_volleyball_teams.php?division_id=1' target='_blank'>Test: get_volleyball_teams.php?division_id=1</a></p>";
?>
