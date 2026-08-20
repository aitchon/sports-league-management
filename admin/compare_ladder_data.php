<?php
// Script to compare current ladder data with what it should be
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

echo "<h2>Ladder Data Comparison</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . "</p>";

// Check what's currently in the ladder positions table
echo "<h3>Current Ladder Positions Table:</h3>";
$result = $conn->query("
    SELECT lp.*, p.name, d.name as division_name
    FROM pickleball_ladder_positions lp
    JOIN pickleball_players p ON lp.player_id = p.id
    LEFT JOIN pickleball_divisions d ON p.division_id = d.id
    WHERE lp.season_id = " . $current_season['id'] . "
    ORDER BY lp.position
    LIMIT 10
");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Position</th><th>Player</th><th>Division</th><th>Wins</th><th>Losses</th><th>Win %</th><th>Points For</th><th>Points Against</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['position']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['division_name']}</td>";
        echo "<td>{$row['wins']}</td>";
        echo "<td>{$row['losses']}</td>";
        echo "<td>" . number_format($row['win_percentage'], 1) . "%</td>";
        echo "<td>{$row['points_scored']}</td>";
        echo "<td>{$row['points_against']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data in ladder positions table!</p>";
}

// Check what the old system was showing
echo "<h3>Old System Calculation (from manage_pickleball_ladder.php):</h3>";
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.current_rating, p.ladder_position, p.dupr_rating,
           d.name as division_name, d.skill_level,
           COUNT(g.id) as total_games,
           SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score > s.player2_score) OR (g.player2_id = p.id AND s.player2_score > s.player1_score) THEN 1 ELSE 0 END) as wins,
           SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score < s.player2_score) OR (g.player2_id = p.id AND s.player2_score < s.player1_score) THEN 1 ELSE 0 END) as losses
    FROM pickleball_players p
    LEFT JOIN pickleball_divisions d ON p.division_id = d.id
    LEFT JOIN pickleball_games g ON (g.player1_id = p.id OR g.player2_id = p.id) AND g.season_id = ? AND g.status = 'completed'
    LEFT JOIN pickleball_scores s ON g.id = s.game_id AND s.game_number = 1
    WHERE p.season_id = ? AND p.is_active = 1
    GROUP BY p.id
    ORDER BY p.ladder_position ASC, p.current_rating DESC
    LIMIT 10
");
$stmt->bind_param("ii", $current_season['id'], $current_season['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Position</th><th>Player</th><th>Division</th><th>Wins</th><th>Losses</th><th>Games</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['ladder_position']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['division_name']}</td>";
        echo "<td>{$row['wins']}</td>";
        echo "<td>{$row['losses']}</td>";
        echo "<td>{$row['total_games']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data from old system!</p>";
}
$stmt->close();

echo "<h3>Actions:</h3>";
echo "<p><a href='fix_ladder_calculation.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Recalculate Ladder</a>";
echo "<a href='debug_ladder_web.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Debug Details</a></p>";
?>
