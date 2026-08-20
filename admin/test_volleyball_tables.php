<?php
// Test script to check volleyball database tables
include 'db.php';

echo "<h2>Volleyball Database Table Check</h2>";

// Check if volleyball tables exist
$tables = ['volleyball_seasons', 'volleyball_divisions', 'volleyball_teams', 'volleyball_players', 'volleyball_games'];

foreach ($tables as $table) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check->num_rows == 0) {
        echo "<p style='color: red;'>❌ $table table does NOT exist</p>";
    } else {
        echo "<p style='color: green;'>✅ $table table exists</p>";
        
        // Check table structure
        $structure = $conn->query("DESCRIBE $table");
        echo "<h3>$table Structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check data count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result->fetch_assoc()['count'];
        echo "<p>Records in $table: <strong>$count</strong></p>";
        echo "<hr>";
    }
}

// Test session functionality
echo "<h3>Session Test:</h3>";
session_start();
if (isset($_SESSION['selected_volleyball_season_id'])) {
    echo "<p style='color: green;'>✅ Session variable 'selected_volleyball_season_id' is set: " . $_SESSION['selected_volleyball_season_id'] . "</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Session variable 'selected_volleyball_season_id' is NOT set</p>";
}

// Test AJAX endpoints
echo "<h3>AJAX Endpoint Test:</h3>";
echo "<p>Testing get_volleyball_divisions.php...</p>";
$test_url = "get_volleyball_divisions.php";
if (file_exists($test_url)) {
    echo "<p style='color: green;'>✅ $test_url exists</p>";
} else {
    echo "<p style='color: red;'>❌ $test_url does NOT exist</p>";
}

echo "<p>Testing get_volleyball_teams.php...</p>";
$test_url = "get_volleyball_teams.php";
if (file_exists($test_url)) {
    echo "<p style='color: green;'>✅ $test_url exists</p>";
} else {
    echo "<p style='color: red;'>❌ $test_url does NOT exist</p>";
}

echo "<p>Testing get_volleyball_players.php...</p>";
$test_url = "get_volleyball_players.php";
if (file_exists($test_url)) {
    echo "<p style='color: green;'>✅ $test_url exists</p>";
} else {
    echo "<p style='color: red;'>❌ $test_url does NOT exist</p>";
}

echo "<p>Testing add_volleyball_player.php...</p>";
$test_url = "add_volleyball_player.php";
if (file_exists($test_url)) {
    echo "<p style='color: green;'>✅ $test_url exists</p>";
} else {
    echo "<p style='color: red;'>❌ $test_url does NOT exist</p>";
}

echo "<p>Testing edit_volleyball_player.php...</p>";
$test_url = "edit_volleyball_player.php";
if (file_exists($test_url)) {
    echo "<p style='color: green;'>✅ $test_url exists</p>";
} else {
    echo "<p style='color: red;'>❌ $test_url does NOT exist</p>";
}

echo "<p>Testing delete_volleyball_player.php...</p>";
$test_url = "delete_volleyball_player.php";
if (file_exists($test_url)) {
    echo "<p style='color: green;'>✅ $test_url exists</p>";
} else {
    echo "<p style='color: red;'>❌ $test_url does NOT exist</p>";
}

?>
