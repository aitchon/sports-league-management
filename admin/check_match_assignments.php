<?php
// Script to check what's in the pickleball_match_assignments table
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

echo "<h2>Match Assignments Debug</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . "</p>";

// Check if match assignments table exists
echo "<h3>1. Checking pickleball_match_assignments table:</h3>";
$result = $conn->query("SHOW TABLES LIKE 'pickleball_match_assignments'");
if ($result->num_rows > 0) {
    echo "<p>✓ Table exists</p>";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_match_assignments");
    $row = $result->fetch_assoc();
    echo "<p>✓ Total records in table: " . $row['count'] . "</p>";
    
    if ($row['count'] > 0) {
        echo "<h4>Sample match assignments:</h4>";
        $result = $conn->query("
            SELECT pma.*, pg.date, pg.status
            FROM pickleball_match_assignments pma
            JOIN pickleball_games pg ON pma.game_id = pg.id
            WHERE pg.season_id = " . $current_season['id'] . "
            ORDER BY pg.date DESC
            LIMIT 10
        ");
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Game ID</th><th>Date</th><th>Status</th><th>Match #</th><th>Team 1 Players</th><th>Team 2 Players</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['game_id']}</td>";
            echo "<td>{$row['date']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "<td>{$row['match_number']}</td>";
            echo "<td>{$row['team1_player1_id']}, {$row['team1_player2_id']}</td>";
            echo "<td>{$row['team2_player1_id']}, {$row['team2_player2_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>✗ Table does not exist</p>";
}

// Check completed games vs match assignments
echo "<h3>2. Completed games vs match assignments:</h3>";
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM pickleball_games 
    WHERE season_id = " . $current_season['id'] . " AND status = 'completed'
");
$row = $result->fetch_assoc();
echo "<p>✓ Completed games: " . $row['count'] . "</p>";

if ($row['count'] > 0) {
    // Check how many completed games have match assignments
    $result = $conn->query("
        SELECT COUNT(DISTINCT pg.id) as count
        FROM pickleball_games pg
        INNER JOIN pickleball_match_assignments pma ON pg.id = pma.game_id
        WHERE pg.season_id = " . $current_season['id'] . " AND pg.status = 'completed'
    ");
    $row = $result->fetch_assoc();
    echo "<p>✓ Completed games with match assignments: " . $row['count'] . "</p>";
    
    // Check completed games without match assignments
    $result = $conn->query("
        SELECT COUNT(*) as count
        FROM pickleball_games pg
        LEFT JOIN pickleball_match_assignments pma ON pg.id = pma.game_id
        WHERE pg.season_id = " . $current_season['id'] . " AND pg.status = 'completed' AND pma.game_id IS NULL
    ");
    $row = $result->fetch_assoc();
    echo "<p>⚠ Completed games WITHOUT match assignments: " . $row['count'] . "</p>";
    
    if ($row['count'] > 0) {
        echo "<h4>Games without match assignments:</h4>";
        $result = $conn->query("
            SELECT pg.id, pg.date, pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id
            FROM pickleball_games pg
            LEFT JOIN pickleball_match_assignments pma ON pg.id = pma.game_id
            WHERE pg.season_id = " . $current_season['id'] . " AND pg.status = 'completed' AND pma.game_id IS NULL
            ORDER BY pg.date DESC
            LIMIT 5
        ");
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Game ID</th><th>Date</th><th>Players</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['date']}</td>";
            echo "<td>{$row['player1_id']}, {$row['player2_id']}, {$row['player3_id']}, {$row['player4_id']}, {$row['player5_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check players and their participation
echo "<h3>3. Player participation analysis:</h3>";
$result = $conn->query("
    SELECT p.id, p.name, 
           COUNT(DISTINCT pg.id) as total_games,
           COUNT(DISTINCT CASE WHEN pma.game_id IS NOT NULL THEN pg.id END) as games_with_assignments
    FROM pickleball_players p
    LEFT JOIN pickleball_games pg ON (pg.player1_id = p.id OR pg.player2_id = p.id OR pg.player3_id = p.id OR pg.player4_id = p.id OR pg.player5_id = p.id) 
        AND pg.season_id = " . $current_season['id'] . " AND pg.status = 'completed'
    LEFT JOIN pickleball_match_assignments pma ON pg.id = pma.game_id
    WHERE p.season_id = " . $current_season['id'] . " AND p.is_active = 1
    GROUP BY p.id
    ORDER BY p.name
");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Player</th><th>Total Games</th><th>Games with Assignments</th><th>Missing Assignments</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $missing = $row['total_games'] - $row['games_with_assignments'];
        $color = $missing > 0 ? "style='color: red;'" : "style='color: green;'";
        echo "<tr>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['total_games']}</td>";
        echo "<td>{$row['games_with_assignments']}</td>";
        echo "<td $color>{$missing}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>4. Actions:</h3>";
echo "<p><a href='create_default_assignments.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Create Default Assignments for Missing Games</a>";
echo "<a href='fix_ladder_calculation.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Recalculate Ladder</a>";
echo "<a href='debug_ladder_web.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Debug Details</a></p>";
?>
