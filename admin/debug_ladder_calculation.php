<?php
// Debug script to see what's happening with ladder calculations
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

echo "=== DEBUGGING LADDER CALCULATION ===\n";
echo "Season: " . $current_season['name'] . " (ID: " . $current_season['id'] . ")\n\n";

// Check if ladder positions table exists and has data
echo "1. Checking pickleball_ladder_positions table:\n";
$result = $conn->query("SHOW TABLES LIKE 'pickleball_ladder_positions'");
if ($result->num_rows > 0) {
    echo "✓ Table exists\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_ladder_positions WHERE season_id = " . $current_season['id']);
    $row = $result->fetch_assoc();
    echo "✓ Records in table for this season: " . $row['count'] . "\n";
    
    if ($row['count'] > 0) {
        echo "\nSample ladder positions data:\n";
        $result = $conn->query("
            SELECT lp.*, p.name 
            FROM pickleball_ladder_positions lp 
            JOIN pickleball_players p ON lp.player_id = p.id 
            WHERE lp.season_id = " . $current_season['id'] . " 
            ORDER BY lp.position 
            LIMIT 5
        ");
        while ($row = $result->fetch_assoc()) {
            echo "  Position {$row['position']}: {$row['name']} - {$row['wins']}W/{$row['losses']}L\n";
        }
    }
} else {
    echo "✗ Table does not exist\n";
}

echo "\n2. Checking completed games:\n";
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM pickleball_games 
    WHERE season_id = " . $current_season['id'] . " AND status = 'completed'
");
$row = $result->fetch_assoc();
echo "✓ Completed games: " . $row['count'] . "\n";

if ($row['count'] > 0) {
    echo "\nSample completed games:\n";
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
        echo "  Game {$row['id']} ({$row['date']}):\n";
        echo "    Players: {$row['player1_id']}, {$row['player2_id']}, {$row['player3_id']}, {$row['player4_id']}, {$row['player5_id']}\n";
        echo "    Match 1: {$row['match1_team1_score']}-{$row['match1_team2_score']}\n";
        echo "    Match 2: {$row['match2_team1_score']}-{$row['match2_team2_score']}\n";
        echo "    Match 3: {$row['match3_team1_score']}-{$row['match3_team2_score']}\n";
        if ($row['match4_team1_score'] > 0 || $row['match4_team2_score'] > 0) {
            echo "    Match 4: {$row['match4_team1_score']}-{$row['match4_team2_score']}\n";
        }
        if ($row['match5_team1_score'] > 0 || $row['match5_team2_score'] > 0) {
            echo "    Match 5: {$row['match5_team1_score']}-{$row['match5_team2_score']}\n";
        }
        echo "\n";
    }
}

echo "\n3. Manual calculation for one player:\n";
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
    echo "Testing player: {$player['name']} (ID: {$player_id})\n";
    
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
    
    echo "Games participated in: " . count($games) . "\n";
    
    $wins = 0;
    $losses = 0;
    $points_scored = 0;
    $points_against = 0;
    
    foreach ($games as $game) {
        echo "  Game {$game['game_id']}:\n";
        
        // Check each match (1-5) for this game
        for ($match_num = 1; $match_num <= 5; $match_num++) {
            $team1_score = isset($game["match{$match_num}_team1_score"]) ? $game["match{$match_num}_team1_score"] : 0;
            $team2_score = isset($game["match{$match_num}_team2_score"]) ? $game["match{$match_num}_team2_score"] : 0;
            
            // Skip if no scores for this match
            if ($team1_score == 0 && $team2_score == 0) {
                continue;
            }
            
            echo "    Match {$match_num}: {$team1_score}-{$team2_score}\n";
            
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
                echo "      Player on Team 1\n";
                $points_scored += $team1_score;
                $points_against += $team2_score;
                if ($team1_score > $team2_score) {
                    $wins++;
                    echo "      WIN!\n";
                } else {
                    $losses++;
                    echo "      LOSS\n";
                }
            } elseif ($player_on_team2) {
                echo "      Player on Team 2\n";
                $points_scored += $team2_score;
                $points_against += $team1_score;
                if ($team2_score > $team1_score) {
                    $wins++;
                    echo "      WIN!\n";
                } else {
                    $losses++;
                    echo "      LOSS\n";
                }
            } else {
                echo "      Player not in this match\n";
            }
        }
    }
    
    echo "\nFinal stats for {$player['name']}:\n";
    echo "  Wins: {$wins}\n";
    echo "  Losses: {$losses}\n";
    echo "  Points Scored: {$points_scored}\n";
    echo "  Points Against: {$points_against}\n";
    echo "  Win %: " . (($wins + $losses) > 0 ? round(($wins / ($wins + $losses)) * 100, 1) : 0) . "%\n";
}

echo "\n=== END DEBUG ===\n";
?>
