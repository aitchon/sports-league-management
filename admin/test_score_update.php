<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Test Score Update</h2>";

// Check if columns exist
echo "<h3>1. Check if match columns exist:</h3>";
$result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match%'");
if ($result->num_rows > 0) {
    echo "<p>✅ Match columns found:</p><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " (" . $row['Type'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>❌ No match columns found</p>";
}

// Check if we have any games
echo "<h3>2. Check for games:</h3>";
$result = $conn->query("SELECT id, status, player1_id, player2_id, player3_id, player4_id FROM pickleball_games ORDER BY id DESC LIMIT 5");
if ($result->num_rows > 0) {
    echo "<p>✅ Games found:</p><table border='1'><tr><th>ID</th><th>Status</th><th>P1</th><th>P2</th><th>P3</th><th>P4</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . ($row['player1_id'] ?: 'NULL') . "</td>";
        echo "<td>" . ($row['player2_id'] ?: 'NULL') . "</td>";
        echo "<td>" . ($row['player3_id'] ?: 'NULL') . "</td>";
        echo "<td>" . ($row['player4_id'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No games found</p>";
}

// Test the UPDATE query
echo "<h3>3. Test UPDATE query:</h3>";
$test_sql = "
    UPDATE pickleball_games 
    SET match1_team1_score = ?, match1_team2_score = ?,
        match2_team1_score = ?, match2_team2_score = ?,
        match3_team1_score = ?, match3_team2_score = ?,
        status = 'completed', updated_at = NOW()
    WHERE id = ?
";

$stmt = $conn->prepare($test_sql);
if ($stmt) {
    echo "<p>✅ UPDATE query prepared successfully</p>";
    
    // Test with dummy values
    $test_values = [21, 18, 19, 21, 21, 15, 1]; // Assuming game ID 1 exists
    $stmt->bind_param("iiiiiii", ...$test_values);
    
    if ($stmt->execute()) {
        echo "<p>✅ UPDATE query executed successfully</p>";
    } else {
        echo "<p>❌ UPDATE query failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<p>❌ Failed to prepare UPDATE query: " . $conn->error . "</p>";
}

// Check PHP error log
echo "<h3>4. Recent PHP errors:</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $errors = file_get_contents($error_log);
    $recent_errors = array_slice(explode("\n", $errors), -10);
    echo "<pre>" . htmlspecialchars(implode("\n", $recent_errors)) . "</pre>";
} else {
    echo "<p>No error log found or accessible</p>";
}

echo "<p><a href='enter_pickleball_scores.php'>Back to Score Entry</a></p>";
?>




