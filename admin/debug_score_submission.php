<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Debug Score Submission</h2>";

// Check if the new columns exist
$result = $conn->query("DESCRIBE pickleball_games");
echo "<h3>Current pickleball_games table structure:</h3>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Check if we have any games
$result = $conn->query("SELECT COUNT(*) as count FROM pickleball_games");
$count = $result->fetch_assoc()['count'];
echo "<p>Total games in database: $count</p>";

// Check if we have any games with 4 players
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM pickleball_games 
    WHERE player1_id IS NOT NULL 
    AND player2_id IS NOT NULL 
    AND player3_id IS NOT NULL 
    AND player4_id IS NOT NULL
");
$count = $result->fetch_assoc()['count'];
echo "<p>Games with 4 players: $count</p>";

// Test the SQL query that's failing
echo "<h3>Testing the UPDATE query:</h3>";
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
    echo "<p>✅ SQL query prepared successfully</p>";
    $stmt->close();
} else {
    echo "<p>❌ SQL query failed: " . $conn->error . "</p>";
}

// Check what columns are missing
$required_columns = [
    'match1_team1_score', 'match1_team2_score',
    'match2_team1_score', 'match2_team2_score', 
    'match3_team1_score', 'match3_team2_score'
];

$existing_columns = [];
$result = $conn->query("DESCRIBE pickleball_games");
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

echo "<h3>Missing columns:</h3>";
$missing_columns = array_diff($required_columns, $existing_columns);
if (empty($missing_columns)) {
    echo "<p>✅ All required columns exist</p>";
} else {
    echo "<p>❌ Missing columns:</p><ul>";
    foreach ($missing_columns as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";
}
?>



