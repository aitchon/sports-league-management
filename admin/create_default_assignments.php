<?php
// Script to create default match assignments for games that don't have them
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

echo "<h2>Creating Default Match Assignments</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . "</p>";

// Find games without match assignments
$result = $conn->query("
    SELECT pg.id, pg.date, pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id
    FROM pickleball_games pg
    LEFT JOIN pickleball_match_assignments pma ON pg.id = pma.game_id
    WHERE pg.season_id = " . $current_season['id'] . " AND pg.status = 'completed' AND pma.game_id IS NULL
    ORDER BY pg.date
");

$games_processed = 0;
$assignments_created = 0;

if ($result->num_rows > 0) {
    echo "<h3>Creating assignments for " . $result->num_rows . " games:</h3>";
    
    while ($game = $result->fetch_assoc()) {
        $game_id = $game['id'];
        $player1_id = $game['player1_id'];
        $player2_id = $game['player2_id'];
        $player3_id = $game['player3_id'];
        $player4_id = $game['player4_id'];
        $player5_id = $game['player5_id'];
        
        echo "<p><strong>Game {$game_id}</strong> ({$game['date']}): Players {$player1_id}, {$player2_id}, {$player3_id}, {$player4_id}, {$player5_id}</p>";
        
        // Determine number of players and matches
        $player_count = 0;
        if ($player1_id) $player_count++;
        if ($player2_id) $player_count++;
        if ($player3_id) $player_count++;
        if ($player4_id) $player_count++;
        if ($player5_id) $player_count++;
        
        $num_matches = ($player_count == 4) ? 3 : 5;
        echo "<p>Players: {$player_count}, Matches: {$num_matches}</p>";
        
        // Create default match assignments
        $assignments = [];
        
        if ($player_count == 4) {
            // 4 players = 3 matches
            $assignments = [
                1 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player2_id,
                    'team2_player1_id' => $player3_id,
                    'team2_player2_id' => $player4_id
                ],
                2 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player3_id,
                    'team2_player1_id' => $player2_id,
                    'team2_player2_id' => $player4_id
                ],
                3 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player4_id,
                    'team2_player1_id' => $player2_id,
                    'team2_player2_id' => $player3_id
                ]
            ];
        } else {
            // 5 players = 5 matches
            $assignments = [
                1 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player2_id,
                    'team2_player1_id' => $player3_id,
                    'team2_player2_id' => $player4_id
                ],
                2 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player3_id,
                    'team2_player1_id' => $player2_id,
                    'team2_player2_id' => $player4_id
                ],
                3 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player4_id,
                    'team2_player1_id' => $player2_id,
                    'team2_player2_id' => $player3_id
                ],
                4 => [
                    'team1_player1_id' => $player1_id,
                    'team1_player2_id' => $player5_id,
                    'team2_player1_id' => $player2_id,
                    'team2_player2_id' => $player3_id
                ],
                5 => [
                    'team1_player1_id' => $player2_id,
                    'team1_player2_id' => $player4_id,
                    'team2_player1_id' => $player3_id,
                    'team2_player2_id' => $player5_id
                ]
            ];
        }
        
        // Insert assignments into database
        foreach ($assignments as $match_num => $assignment) {
            $stmt = $conn->prepare("
                INSERT INTO pickleball_match_assignments 
                (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiiiii", 
                $game_id, $match_num, 
                $assignment['team1_player1_id'], $assignment['team1_player2_id'],
                $assignment['team2_player1_id'], $assignment['team2_player2_id']
            );
            
            if ($stmt->execute()) {
                $assignments_created++;
                echo "<p style='color: green;'>✓ Created assignment for Match {$match_num}</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to create assignment for Match {$match_num}: " . $stmt->error . "</p>";
            }
            $stmt->close();
        }
        
        $games_processed++;
        echo "<hr>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p><strong>Games processed:</strong> {$games_processed}</p>";
    echo "<p><strong>Assignments created:</strong> {$assignments_created}</p>";
    
} else {
    echo "<p>No games found without match assignments.</p>";
}

echo "<h3>Next Steps:</h3>";
echo "<p><a href='fix_ladder_calculation.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Recalculate Ladder Positions</a>";
echo "<a href='check_match_assignments.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check Assignments</a></p>";
?>

