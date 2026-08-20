<?php
// Script to check what's actually in the match assignments table
include 'db.php';

// Get current season
$current_season = null;
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}
$stmt->close();

if (!$current_season) {
    die("No active season found!");
}

echo "<h2>Actual Match Assignments Data</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . "</p>";

// Check what's actually in the match assignments table
echo "<h3>Raw match assignments data:</h3>";
$result = $conn->query("
    SELECT pma.*, pg.date, pg.status,
           pg.player1_id as game_p1, pg.player2_id as game_p2, pg.player3_id as game_p3, pg.player4_id as game_p4, pg.player5_id as game_p5
    FROM pickleball_match_assignments pma
    JOIN pickleball_games pg ON pma.game_id = pg.id
    WHERE pg.season_id = " . $current_season['id'] . "
    ORDER BY pg.date DESC, pma.game_id, pma.match_number
    LIMIT 20
");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Game ID</th><th>Date</th><th>Status</th><th>Match #</th><th>Game Players</th><th>Team 1 Players</th><th>Team 2 Players</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['game_id']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['match_number']}</td>";
        echo "<td>{$row['game_p1']}, {$row['game_p2']}, {$row['game_p3']}, {$row['game_p4']}, {$row['game_p5']}</td>";
        echo "<td>{$row['team1_player1_id']}, {$row['team1_player2_id']}</td>";
        echo "<td>{$row['team2_player1_id']}, {$row['team2_player2_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No match assignments found!</p>";
}

// Check for any hardcoded player IDs (1, 2, 3, 4, 5)
echo "<h3>Checking for hardcoded player IDs:</h3>";
$result = $conn->query("
    SELECT COUNT(*) as count
    FROM pickleball_match_assignments pma
    JOIN pickleball_games pg ON pma.game_id = pg.id
    WHERE pg.season_id = " . $current_season['id'] . "
    AND (pma.team1_player1_id IN (1,2,3,4,5) 
         OR pma.team1_player2_id IN (1,2,3,4,5)
         OR pma.team2_player1_id IN (1,2,3,4,5)
         OR pma.team2_player2_id IN (1,2,3,4,5))
");
$row = $result->fetch_assoc();
echo "<p>Assignments with hardcoded IDs (1,2,3,4,5): {$row['count']}</p>";

// Check what player IDs actually exist in the season
echo "<h3>Actual player IDs in this season:</h3>";
$result = $conn->query("
    SELECT id, name
    FROM pickleball_players
    WHERE season_id = " . $current_season['id'] . " AND is_active = 1
    ORDER BY id
");
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Player ID</th><th>Player Name</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check if there are any assignments that don't match the game players
echo "<h3>Assignments that don't match game players:</h3>";
$result = $conn->query("
    SELECT pma.*, pg.date,
           pg.player1_id as game_p1, pg.player2_id as game_p2, pg.player3_id as game_p3, pg.player4_id as game_p4, pg.player5_id as game_p5
    FROM pickleball_match_assignments pma
    JOIN pickleball_games pg ON pma.game_id = pg.id
    WHERE pg.season_id = " . $current_season['id'] . "
    AND (pma.team1_player1_id NOT IN (pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id)
         OR pma.team1_player2_id NOT IN (pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id)
         OR pma.team2_player1_id NOT IN (pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id)
         OR pma.team2_player2_id NOT IN (pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id))
    LIMIT 10
");

if ($result->num_rows > 0) {
    echo "<p style='color: red;'><strong>Found assignments that don't match game players!</strong></p>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Game ID</th><th>Date</th><th>Match #</th><th>Game Players</th><th>Team 1</th><th>Team 2</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['game_id']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$row['match_number']}</td>";
        echo "<td>{$row['game_p1']}, {$row['game_p2']}, {$row['game_p3']}, {$row['game_p4']}, {$row['game_p5']}</td>";
        echo "<td>{$row['team1_player1_id']}, {$row['team1_player2_id']}</td>";
        echo "<td>{$row['team2_player1_id']}, {$row['team2_player2_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✓ All assignments match their game players</p>";
}

echo "<h3>Actions:</h3>";
echo "<p><a href='fix_assignments.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Fix Incorrect Assignments</a>";
echo "<a href='debug_match_processing.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Debug Processing</a>";
echo "<a href='fix_ladder_calculation.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Recalculate Ladder</a></p>";
?>
