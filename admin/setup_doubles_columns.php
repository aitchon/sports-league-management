<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Setup Doubles Match Columns</h2>";

// Check if columns already exist
$result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match1_team1_score'");
if ($result->num_rows > 0) {
    echo "<p>✅ Doubles match columns already exist</p>";
} else {
    echo "<p>❌ Doubles match columns missing. Adding them...</p>";
    
    // Add the columns
    $sql = "
        ALTER TABLE `pickleball_games` 
        ADD COLUMN `match1_team1_score` int(11) DEFAULT NULL,
        ADD COLUMN `match1_team2_score` int(11) DEFAULT NULL,
        ADD COLUMN `match2_team1_score` int(11) DEFAULT NULL,
        ADD COLUMN `match2_team2_score` int(11) DEFAULT NULL,
        ADD COLUMN `match3_team1_score` int(11) DEFAULT NULL,
        ADD COLUMN `match3_team2_score` int(11) DEFAULT NULL
    ";
    
    if ($conn->query($sql)) {
        echo "<p>✅ Successfully added doubles match columns</p>";
    } else {
        echo "<p>❌ Error adding columns: " . $conn->error . "</p>";
    }
}

// Verify the columns exist now
$result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match%'");
echo "<h3>Current match columns:</h3>";
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . " (" . $row['Type'] . ")</li>";
}
echo "</ul>";

echo "<p><a href='enter_pickleball_scores.php'>Go to Score Entry</a></p>";
?>

