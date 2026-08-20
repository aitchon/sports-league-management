<?php
// Script to debug why only Match 1 is being counted
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

echo "<h2>Debug Match Processing</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . "</p>";

// Get all players to debug
$result = $conn->query("
    SELECT p.id, p.name 
    FROM pickleball_players p 
    WHERE p.season_id = " . $current_season['id'] . " AND p.is_active = 1 
    ORDER BY p.name
");

if ($result->num_rows > 0) {
    echo "<h3>Debugging all players:</h3>";
    
    while ($player = $result->fetch_assoc()) {
        $player_id = $player['id'];
        echo "<div style='border: 2px solid #007cba; margin: 20px 0; padding: 15px;'>";
        echo "<h4>Player: {$player['name']} (ID: {$player_id})</h4>";
    
    // Get all games this player participated in with match assignments
    $stmt = $conn->prepare("
        SELECT 
            pg.id as game_id,
            pg.match1_team1_score, pg.match1_team2_score,
            pg.match2_team1_score, pg.match2_team2_score,
            pg.match3_team1_score, pg.match3_team2_score,
            pg.match4_team1_score, pg.match4_team2_score,
            pg.match5_team1_score, pg.match5_team2_score,
            pma.match_number,
            pma.team1_player1_id, pma.team1_player2_id,
            pma.team2_player1_id, pma.team2_player2_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_match_assignments pma ON pg.id = pma.game_id
        WHERE pg.season_id = ? 
        AND pg.status = 'completed'
        AND (pg.player1_id = ? OR pg.player2_id = ? OR pg.player3_id = ? OR pg.player4_id = ? OR pg.player5_id = ?)
        ORDER BY pg.date, pma.match_number
    ");
    $stmt->bind_param("iiiiii", $current_season['id'], $player_id, $player_id, $player_id, $player_id, $player_id);
    $stmt->execute();
    $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "<h4>Raw data from database:</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Game ID</th><th>Match #</th><th>Team 1 Players</th><th>Team 2 Players</th><th>Match 1 Score</th><th>Match 2 Score</th><th>Match 3 Score</th><th>Match 4 Score</th><th>Match 5 Score</th></tr>";
    
    foreach ($games as $game) {
        echo "<tr>";
        echo "<td>{$game['game_id']}</td>";
        echo "<td>{$game['match_number']}</td>";
        echo "<td>{$game['team1_player1_id']}, {$game['team1_player2_id']}</td>";
        echo "<td>{$game['team2_player1_id']}, {$game['team2_player2_id']}</td>";
        echo "<td>{$game['match1_team1_score']}-{$game['match1_team2_score']}</td>";
        echo "<td>{$game['match2_team1_score']}-{$game['match2_team2_score']}</td>";
        echo "<td>{$game['match3_team1_score']}-{$game['match3_team2_score']}</td>";
        echo "<td>{$game['match4_team1_score']}-{$game['match4_team2_score']}</td>";
        echo "<td>{$game['match5_team1_score']}-{$game['match5_team2_score']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h4>Processing logic simulation:</h4>";
    
    $wins = 0;
    $losses = 0;
    $points_scored = 0;
    $points_against = 0;
    
    // Process each game and match
    $processed_games = [];
    foreach ($games as $game) {
        $game_id = $game['game_id'];
        $match_num = $game['match_number'];
        
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<h5>Processing Game {$game_id}, Match {$match_num}</h5>";
        
        // Skip if we've already processed this match
        if (isset($processed_games[$game_id . '_' . $match_num])) {
            echo "<p style='color: orange;'>⚠ Already processed this match, skipping</p>";
            echo "</div>";
            continue;
        }
        $processed_games[$game_id . '_' . $match_num] = true;
        
        // Skip match 5 if the columns don't exist
        if ($match_num == 5 && !isset($game['match5_team1_score'])) {
            echo "<p style='color: orange;'>⚠ Match 5 columns don't exist, skipping</p>";
            echo "</div>";
            continue;
        }
        
        $team1_score = isset($game["match{$match_num}_team1_score"]) ? $game["match{$match_num}_team1_score"] : 0;
        $team2_score = isset($game["match{$match_num}_team2_score"]) ? $game["match{$match_num}_team2_score"] : 0;
        
        echo "<p><strong>Scores:</strong> Team 1: {$team1_score}, Team 2: {$team2_score}</p>";
        
        if ($team1_score > 0 || $team2_score > 0) {
            // Determine which team the player was on using actual match assignments
            $player_on_team1 = false;
            $player_on_team2 = false;
            
            if ($game['team1_player1_id'] == $player_id || $game['team1_player2_id'] == $player_id) {
                $player_on_team1 = true;
                echo "<p style='color: blue;'>✓ Player is on Team 1</p>";
            } elseif ($game['team2_player1_id'] == $player_id || $game['team2_player2_id'] == $player_id) {
                $player_on_team2 = true;
                echo "<p style='color: blue;'>✓ Player is on Team 2</p>";
            } else {
                echo "<p style='color: red;'>✗ Player not found in either team!</p>";
                echo "<p>Team 1: {$game['team1_player1_id']}, {$game['team1_player2_id']}</p>";
                echo "<p>Team 2: {$game['team2_player1_id']}, {$game['team2_player2_id']}</p>";
            }
            
            if ($player_on_team1) {
                $points_scored += $team1_score;
                $points_against += $team2_score;
                if ($team1_score > $team2_score) {
                    $wins++;
                    echo "<p style='color: green;'><strong>WIN!</strong> ({$team1_score} > {$team2_score})</p>";
                } else {
                    $losses++;
                    echo "<p style='color: red;'><strong>LOSS</strong> ({$team1_score} < {$team2_score})</p>";
                }
            } elseif ($player_on_team2) {
                $points_scored += $team2_score;
                $points_against += $team1_score;
                if ($team2_score > $team1_score) {
                    $wins++;
                    echo "<p style='color: green;'><strong>WIN!</strong> ({$team2_score} > {$team1_score})</p>";
                } else {
                    $losses++;
                    echo "<p style='color: red;'><strong>LOSS</strong> ({$team2_score} < {$team1_score})</p>";
                }
            }
        } else {
            echo "<p style='color: gray;'>No scores for this match</p>";
        }
        echo "</div>";
    }
    
        echo "<h5>Final Results for {$player['name']}:</h5>";
        echo "<p><strong>Wins:</strong> {$wins}</p>";
        echo "<p><strong>Losses:</strong> {$losses}</p>";
        echo "<p><strong>Points Scored:</strong> {$points_scored}</p>";
        echo "<p><strong>Points Against:</strong> {$points_against}</p>";
        echo "<p><strong>Total Matches:</strong> " . ($wins + $losses) . "</p>";
        echo "</div>";
    }
}

echo "<h3>Summary:</h3>";
echo "<p>This debug shows the match processing for all players. Look for patterns like:</p>";
echo "<ul>";
echo "<li><strong>Only Match 1 data:</strong> If you only see Match 1 assignments, that's why only 1 game is counted</li>";
echo "<li><strong>Missing assignments:</strong> If games show no match assignments, those need to be created</li>";
echo "<li><strong>Player not found:</strong> If a player isn't found in team assignments, there's a data issue</li>";
echo "</ul>";

echo "<h3>Actions:</h3>";
echo "<p><a href='check_match_assignments.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Check Match Assignments</a>";
echo "<a href='create_default_assignments.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Create Missing Assignments</a>";
echo "<a href='fix_ladder_calculation.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Recalculate Ladder</a></p>";
?>
