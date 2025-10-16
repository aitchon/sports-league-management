<?php
// Script to fix incorrect match assignments
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

echo "<h2>Fix Incorrect Match Assignments</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . "</p>";

// Find assignments that don't match the game players
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
");

$assignments_fixed = 0;
$assignments_deleted = 0;

if ($result->num_rows > 0) {
    echo "<h3>Found " . $result->num_rows . " incorrect assignments. Fixing...</h3>";
    
    while ($row = $result->fetch_assoc()) {
        $game_id = $row['game_id'];
        $match_num = $row['match_number'];
        $game_p1 = $row['game_p1'];
        $game_p2 = $row['game_p2'];
        $game_p3 = $row['game_p3'];
        $game_p4 = $row['game_p4'];
        $game_p5 = $row['game_p5'];
        
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<h4>Game {$game_id}, Match {$match_num} ({$row['date']})</h4>";
        echo "<p><strong>Game Players:</strong> {$game_p1}, {$game_p2}, {$game_p3}, {$game_p4}, {$game_p5}</p>";
        echo "<p><strong>Current Assignment:</strong> Team 1: {$row['team1_player1_id']}, {$row['team1_player2_id']} | Team 2: {$row['team2_player1_id']}, {$row['team2_player2_id']}</p>";
        
        // Count players
        $player_count = 0;
        if ($game_p1) $player_count++;
        if ($game_p2) $player_count++;
        if ($game_p3) $player_count++;
        if ($game_p4) $player_count++;
        if ($game_p5) $player_count++;
        
        // Delete the incorrect assignment
        $stmt = $conn->prepare("DELETE FROM pickleball_match_assignments WHERE game_id = ? AND match_number = ?");
        $stmt->bind_param("ii", $game_id, $match_num);
        if ($stmt->execute()) {
            $assignments_deleted++;
            echo "<p style='color: orange;'>✓ Deleted incorrect assignment</p>";
        }
        $stmt->close();
        
        // Create correct assignment based on match number
        $correct_assignment = null;
        
        if ($player_count == 4) {
            // 4 players = 3 matches
            switch ($match_num) {
                case 1:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p2,
                        'team2_player1_id' => $game_p3,
                        'team2_player2_id' => $game_p4
                    ];
                    break;
                case 2:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p3,
                        'team2_player1_id' => $game_p2,
                        'team2_player2_id' => $game_p4
                    ];
                    break;
                case 3:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p4,
                        'team2_player1_id' => $game_p2,
                        'team2_player2_id' => $game_p3
                    ];
                    break;
            }
        } else {
            // 5 players = 5 matches
            switch ($match_num) {
                case 1:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p2,
                        'team2_player1_id' => $game_p3,
                        'team2_player2_id' => $game_p4
                    ];
                    break;
                case 2:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p3,
                        'team2_player1_id' => $game_p2,
                        'team2_player2_id' => $game_p4
                    ];
                    break;
                case 3:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p4,
                        'team2_player1_id' => $game_p2,
                        'team2_player2_id' => $game_p3
                    ];
                    break;
                case 4:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p1,
                        'team1_player2_id' => $game_p5,
                        'team2_player1_id' => $game_p2,
                        'team2_player2_id' => $game_p3
                    ];
                    break;
                case 5:
                    $correct_assignment = [
                        'team1_player1_id' => $game_p2,
                        'team1_player2_id' => $game_p4,
                        'team2_player1_id' => $game_p3,
                        'team2_player2_id' => $game_p5
                    ];
                    break;
            }
        }
        
        if ($correct_assignment) {
            // Insert the correct assignment
            $stmt = $conn->prepare("
                INSERT INTO pickleball_match_assignments 
                (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiiiii", 
                $game_id, $match_num,
                $correct_assignment['team1_player1_id'], $correct_assignment['team1_player2_id'],
                $correct_assignment['team2_player1_id'], $correct_assignment['team2_player2_id']
            );
            
            if ($stmt->execute()) {
                $assignments_fixed++;
                echo "<p style='color: green;'>✓ Created correct assignment: Team 1: {$correct_assignment['team1_player1_id']}, {$correct_assignment['team1_player2_id']} | Team 2: {$correct_assignment['team2_player1_id']}, {$correct_assignment['team2_player2_id']}</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to create correct assignment: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: red;'>✗ Could not determine correct assignment for match {$match_num} with {$player_count} players</p>";
        }
        
        echo "</div>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p><strong>Assignments deleted:</strong> {$assignments_deleted}</p>";
    echo "<p><strong>Assignments fixed:</strong> {$assignments_fixed}</p>";
    
} else {
    echo "<p>No incorrect assignments found!</p>";
}

echo "<h3>Next Steps:</h3>";
echo "<p><a href='check_actual_assignments.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Check Assignments</a>";
echo "<a href='fix_ladder_calculation.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Recalculate Ladder</a></p>";
?>

