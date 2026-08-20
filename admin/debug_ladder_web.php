<?php
// Web-based debug script to see what's happening with ladder calculations
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

echo "<h2>=== DEBUGGING LADDER CALCULATION ===</h2>";
echo "<p><strong>Season:</strong> " . htmlspecialchars($current_season['name']) . " (ID: " . $current_season['id'] . ")</p>";

// Check if ladder positions table exists and has data
echo "<h3>1. Checking pickleball_ladder_positions table:</h3>";
$result = $conn->query("SHOW TABLES LIKE 'pickleball_ladder_positions'");
if ($result->num_rows > 0) {
    echo "<p>✓ Table exists</p>";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_ladder_positions WHERE season_id = " . $current_season['id']);
    $row = $result->fetch_assoc();
    echo "<p>✓ Records in table for this season: " . $row['count'] . "</p>";
    
    if ($row['count'] > 0) {
        echo "<h4>Sample ladder positions data:</h4>";
        $result = $conn->query("
            SELECT lp.*, p.name 
            FROM pickleball_ladder_positions lp 
            JOIN pickleball_players p ON lp.player_id = p.id 
            WHERE lp.season_id = " . $current_season['id'] . " 
            ORDER BY lp.position 
            LIMIT 5
        ");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Position</th><th>Player</th><th>Wins</th><th>Losses</th><th>Win %</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['position']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['wins']}</td>";
            echo "<td>{$row['losses']}</td>";
            echo "<td>" . number_format($row['win_percentage'], 1) . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>✗ Table does not exist</p>";
}

echo "<h3>2. Checking completed games:</h3>";
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM pickleball_games 
    WHERE season_id = " . $current_season['id'] . " AND status = 'completed'
");
$row = $result->fetch_assoc();
echo "<p>✓ Completed games: " . $row['count'] . "</p>";

if ($row['count'] > 0) {
    echo "<h4>Sample completed games:</h4>";
    $result = $conn->query("
        SELECT id, date, player1_id, player2_id, player3_id, player4_id, player5_id,
               match1_team1_score, match1_team2_score,
               match2_team1_score, match2_team2_score,
               match3_team1_score, match3_team2_score,
               match4_team1_score, match4_team2_score,
               match5_team1_score, match5_team2_score
        FROM pickleball_games 
        WHERE season_id = " . $current_season['id'] . " AND status = 'completed'
        ORDER BY date DESC
        LIMIT 3
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<h5>Game {$row['id']} ({$row['date']})</h5>";
        echo "<p><strong>Players:</strong> {$row['player1_id']}, {$row['player2_id']}, {$row['player3_id']}, {$row['player4_id']}, {$row['player5_id']}</p>";
        echo "<p><strong>Scores:</strong></p>";
        echo "<ul>";
        echo "<li>Match 1: {$row['match1_team1_score']}-{$row['match1_team2_score']}</li>";
        echo "<li>Match 2: {$row['match2_team1_score']}-{$row['match2_team2_score']}</li>";
        echo "<li>Match 3: {$row['match3_team1_score']}-{$row['match3_team2_score']}</li>";
        if ($row['match4_team1_score'] > 0 || $row['match4_team2_score'] > 0) {
            echo "<li>Match 4: {$row['match4_team1_score']}-{$row['match4_team2_score']}</li>";
        }
        if ($row['match5_team1_score'] > 0 || $row['match5_team2_score'] > 0) {
            echo "<li>Match 5: {$row['match5_team1_score']}-{$row['match5_team2_score']}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
}

echo "<h3>3. Manual calculation for one player:</h3>";
// Get a sample player
$result = $conn->query("
    SELECT p.id, p.name 
    FROM pickleball_players p 
    WHERE p.season_id = " . $current_season['id'] . " AND p.is_active = 1 
    LIMIT 1
");
if ($result->num_rows > 0) {
    $player = $result->fetch_assoc();
    $player_id = $player['id'];
    echo "<p><strong>Testing player:</strong> {$player['name']} (ID: {$player_id})</p>";
    
    // Get all games this player participated in
    $stmt = $conn->prepare("
        SELECT 
            pg.id as game_id,
            pg.match1_team1_score, pg.match1_team2_score,
            pg.match2_team1_score, pg.match2_team2_score,
            pg.match3_team1_score, pg.match3_team2_score,
            pg.match4_team1_score, pg.match4_team2_score,
            pg.match5_team1_score, pg.match5_team2_score,
            pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id
        FROM pickleball_games pg
        WHERE pg.season_id = ? 
        AND pg.status = 'completed'
        AND (pg.player1_id = ? OR pg.player2_id = ? OR pg.player3_id = ? OR pg.player4_id = ? OR pg.player5_id = ?)
        ORDER BY pg.date
    ");
    $stmt->bind_param("iiiiii", $current_season['id'], $player_id, $player_id, $player_id, $player_id, $player_id);
    $stmt->execute();
    $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "<p><strong>Games participated in:</strong> " . count($games) . "</p>";
    
    $wins = 0;
    $losses = 0;
    $points_scored = 0;
    $points_against = 0;
    
    foreach ($games as $game) {
        echo "<div style='border: 1px solid #ddd; margin: 5px; padding: 5px;'>";
        echo "<h6>Game {$game['game_id']}:</h6>";
        
        // Check each match (1-5) for this game
        for ($match_num = 1; $match_num <= 5; $match_num++) {
            $team1_score = isset($game["match{$match_num}_team1_score"]) ? $game["match{$match_num}_team1_score"] : 0;
            $team2_score = isset($game["match{$match_num}_team2_score"]) ? $game["match{$match_num}_team2_score"] : 0;
            
            // Skip if no scores for this match
            if ($team1_score == 0 && $team2_score == 0) {
                continue;
            }
            
            echo "<p><strong>Match {$match_num}:</strong> {$team1_score}-{$team2_score}</p>";
            
            // Determine which team the player was on based on the original game setup
            $player_on_team1 = false;
            $player_on_team2 = false;
            
            // Use the original match assignments logic
            switch ($match_num) {
                case 1:
                    $player_on_team1 = ($game['player1_id'] == $player_id || $game['player2_id'] == $player_id);
                    $player_on_team2 = ($game['player3_id'] == $player_id || $game['player4_id'] == $player_id);
                    break;
                case 2:
                    $player_on_team1 = ($game['player1_id'] == $player_id || $game['player3_id'] == $player_id);
                    $player_on_team2 = ($game['player2_id'] == $player_id || $game['player4_id'] == $player_id);
                    break;
                case 3:
                    $player_on_team1 = ($game['player1_id'] == $player_id || $game['player4_id'] == $player_id);
                    $player_on_team2 = ($game['player2_id'] == $player_id || $game['player3_id'] == $player_id);
                    break;
                case 4:
                    // For 5-player games, match 4: P1&P5 vs P2&P3
                    $player_on_team1 = ($game['player1_id'] == $player_id || $game['player5_id'] == $player_id);
                    $player_on_team2 = ($game['player2_id'] == $player_id || $game['player3_id'] == $player_id);
                    break;
                case 5:
                    // For 5-player games, match 5: P2&P4 vs P3&P5
                    $player_on_team1 = ($game['player2_id'] == $player_id || $game['player4_id'] == $player_id);
                    $player_on_team2 = ($game['player3_id'] == $player_id || $game['player5_id'] == $player_id);
                    break;
            }
            
            if ($player_on_team1) {
                echo "<p style='color: blue;'>Player on Team 1</p>";
                $points_scored += $team1_score;
                $points_against += $team2_score;
                if ($team1_score > $team2_score) {
                    $wins++;
                    echo "<p style='color: green;'><strong>WIN!</strong></p>";
                } else {
                    $losses++;
                    echo "<p style='color: red;'><strong>LOSS</strong></p>";
                }
            } elseif ($player_on_team2) {
                echo "<p style='color: blue;'>Player on Team 2</p>";
                $points_scored += $team2_score;
                $points_against += $team1_score;
                if ($team2_score > $team1_score) {
                    $wins++;
                    echo "<p style='color: green;'><strong>WIN!</strong></p>";
                } else {
                    $losses++;
                    echo "<p style='color: red;'><strong>LOSS</strong></p>";
                }
            } else {
                echo "<p style='color: gray;'>Player not in this match</p>";
            }
        }
        echo "</div>";
    }
    
    echo "<h4>Final stats for {$player['name']}:</h4>";
    echo "<ul>";
    echo "<li><strong>Wins:</strong> {$wins}</li>";
    echo "<li><strong>Losses:</strong> {$losses}</li>";
    echo "<li><strong>Points Scored:</strong> {$points_scored}</li>";
    echo "<li><strong>Points Against:</strong> {$points_against}</li>";
    echo "<li><strong>Win %:</strong> " . (($wins + $losses) > 0 ? round(($wins / ($wins + $losses)) * 100, 1) : 0) . "%</li>";
    echo "</ul>";
}

echo "<h3>4. Quick Fix Button:</h3>";
echo "<p><a href='fix_ladder_calculation.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Recalculate All Ladder Positions</a></p>";

echo "<h3>=== END DEBUG ===</h3>";
?>
