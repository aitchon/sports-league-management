<?php
// Script to manually update all ladder positions
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

echo "Updating ladder positions for season: " . $current_season['name'] . "\n";

// Function to update ladder positions (copied from enter_pickleball_scores.php)
function updateLadderPositions($conn, $season_id) {
    try {
        // Get all players for the season with their divisions
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.division_id
            FROM pickleball_players p
            WHERE p.season_id = ? AND p.is_active = 1
            ORDER BY p.division_id, p.name
        ");
        $stmt->bind_param("i", $season_id);
        $stmt->execute();
        $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($players)) {
            return "No players found for season $season_id";
        }
        
        // Group players by division
        $players_by_division = [];
        foreach ($players as $player) {
            $division_id = $player['division_id'] ?: 0; // Use 0 for players without division
            if (!isset($players_by_division[$division_id])) {
                $players_by_division[$division_id] = [];
            }
            $players_by_division[$division_id][] = $player;
        }
        
        // Calculate stats for each player within their division
        $all_player_stats = [];
        foreach ($players_by_division as $division_id => $division_players) {
            $player_stats = [];
            foreach ($division_players as $player) {
                $player_id = $player['id'];
                
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
                $stmt->bind_param("iiiiii", $season_id, $player_id, $player_id, $player_id, $player_id, $player_id);
                $stmt->execute();
                $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $wins = 0;
                $losses = 0;
                $points_scored = 0;
                $points_against = 0;
                
                // Process each game and match
                $processed_games = [];
                foreach ($games as $game) {
                    $game_id = $game['game_id'];
                    $match_num = $game['match_number'];
                    
                    // Skip if we've already processed this match
                    if (isset($processed_games[$game_id . '_' . $match_num])) {
                        continue;
                    }
                    $processed_games[$game_id . '_' . $match_num] = true;
                    
                    // Skip match 5 if the columns don't exist
                    if ($match_num == 5 && !isset($game['match5_team1_score'])) {
                        continue;
                    }
                    
                    $team1_score = isset($game["match{$match_num}_team1_score"]) ? $game["match{$match_num}_team1_score"] : 0;
                    $team2_score = isset($game["match{$match_num}_team2_score"]) ? $game["match{$match_num}_team2_score"] : 0;
                    
                    if ($team1_score > 0 || $team2_score > 0) {
                        // Determine which team the player was on using match assignments
                        $player_on_team1 = false;
                        $player_on_team2 = false;
                        
                        if ($game['team1_player1_id'] == $player_id || $game['team1_player2_id'] == $player_id) {
                            $player_on_team1 = true;
                        } elseif ($game['team2_player1_id'] == $player_id || $game['team2_player2_id'] == $player_id) {
                            $player_on_team2 = true;
                        }
                        
                        if ($player_on_team1) {
                            $points_scored += $team1_score;
                            $points_against += $team2_score;
                            if ($team1_score > $team2_score) {
                                $wins++;
                            } else {
                                $losses++;
                            }
                        } elseif ($player_on_team2) {
                            $points_scored += $team2_score;
                            $points_against += $team1_score;
                            if ($team2_score > $team1_score) {
                                $wins++;
                            } else {
                                $losses++;
                            }
                        }
                    }
                }
                
                $total_matches = $wins + $losses;
                $win_percentage = $total_matches > 0 ? ($wins / $total_matches) * 100 : 0;
                $point_differential = $points_scored - $points_against;
                
                $player_stats[] = [
                    'player_id' => $player_id,
                    'name' => $player['name'],
                    'division_id' => $division_id,
                    'wins' => $wins,
                    'losses' => $losses,
                    'points_scored' => $points_scored,
                    'points_against' => $points_against,
                    'win_percentage' => $win_percentage,
                    'point_differential' => $point_differential
                ];
            }
            
            // Sort players within this division
            usort($player_stats, function($a, $b) {
                if ($a['win_percentage'] != $b['win_percentage']) {
                    return $b['win_percentage'] <=> $a['win_percentage'];
                }
                if ($a['point_differential'] != $b['point_differential']) {
                    return $b['point_differential'] <=> $a['point_differential'];
                }
                return $b['points_scored'] <=> $a['points_scored'];
            });
            
            // Assign positions within this division
            foreach ($player_stats as $index => $stats) {
                $stats['position'] = $index + 1;
                $all_player_stats[] = $stats;
            }
        }
        
        // Update ladder positions for all players
        foreach ($all_player_stats as $stats) {
            $stmt = $conn->prepare("
                INSERT INTO pickleball_ladder_positions 
                (player_id, season_id, position, wins, losses, points_scored, points_against, win_percentage, point_differential)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                position = VALUES(position),
                wins = VALUES(wins),
                losses = VALUES(losses),
                points_scored = VALUES(points_scored),
                points_against = VALUES(points_against),
                win_percentage = VALUES(win_percentage),
                point_differential = VALUES(point_differential),
                last_updated = NOW()
            ");
            $stmt->bind_param("iiiiiiidd", 
                $stats['player_id'], $season_id, $stats['position'], $stats['wins'], $stats['losses'],
                $stats['points_scored'], $stats['points_against'], $stats['win_percentage'], $stats['point_differential']
            );
            $stmt->execute();
            $stmt->close();
        }
        
        return "Ladder positions updated successfully for " . count($all_player_stats) . " players across " . count($players_by_division) . " divisions";
        
    } catch (Exception $e) {
        return "Error updating ladder positions: " . $e->getMessage();
    }
}

// Run the update
$result = updateLadderPositions($conn, $current_season['id']);
echo $result . "\n";

// Show some sample data
echo "\nSample ladder positions:\n";
$stmt = $conn->prepare("
    SELECT lp.*, p.name, d.name as division_name
    FROM pickleball_ladder_positions lp
    JOIN pickleball_players p ON lp.player_id = p.id
    LEFT JOIN pickleball_divisions d ON p.division_id = d.id
    WHERE lp.season_id = ?
    ORDER BY lp.position
    LIMIT 10
");
$stmt->bind_param("i", $current_season['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "Position {$row['position']}: {$row['name']} ({$row['division_name']}) - {$row['wins']}W/{$row['losses']}L\n";
}
$stmt->close();
?>

