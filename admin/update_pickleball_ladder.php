<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = '';

// Function to update ladder positions for a season
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
                
                // Get all games this player participated in
                $stmt = $conn->prepare("
                    SELECT 
                        pg.id as game_id,
                        pg.match1_team1_score, pg.match1_team2_score,
                        pg.match2_team1_score, pg.match2_team2_score,
                        pg.match3_team1_score, pg.match3_team2_score,
                        pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id
                    FROM pickleball_games pg
                    WHERE pg.season_id = ? 
                    AND pg.status = 'completed'
                    AND (pg.player1_id = ? OR pg.player2_id = ? OR pg.player3_id = ? OR pg.player4_id = ?)
                    ORDER BY pg.date
                ");
                $stmt->bind_param("iiiii", $season_id, $player_id, $player_id, $player_id, $player_id);
                $stmt->execute();
                $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $wins = 0;
                $losses = 0;
                $points_scored = 0;
                $points_against = 0;
                
                // Process each game
                foreach ($games as $game) {
                    // Check each of the 3 matches
                    for ($match_num = 1; $match_num <= 3; $match_num++) {
                        $team1_score = $game["match{$match_num}_team1_score"];
                        $team2_score = $game["match{$match_num}_team2_score"];
                        
                        if ($team1_score > 0 || $team2_score > 0) {
                            // Determine which team the player was on
                            $player_on_team1 = false;
                            $player_on_team2 = false;
                            
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

// Handle manual ladder update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ladder'])) {
    $season_id = (int)$_POST['season_id'];
    
    try {
        $result = updateLadderPositions($conn, $season_id);
        $message = '<div class="alert alert-success">' . $result . '</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Get all seasons
$seasons = [];
$result = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
if ($result) {
    $seasons = $result->fetch_all(MYSQLI_ASSOC);
}

// Get current season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Pickleball Ladder Positions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏓 Update Pickleball Ladder</h1>
                    <p class="lead mb-0">Recalculate ladder positions and standings</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Update Ladder Positions</h5>
                    </div>
                    <div class="card-body">
                        <p>This will recalculate all ladder positions based on current game results. Use this when:</p>
                        <ul>
                            <li>Games have been deleted</li>
                            <li>Game scores have been edited</li>
                            <li>You want to refresh the standings</li>
                        </ul>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="season_id" class="form-label">Select Season</label>
                                <select class="form-select" id="season_id" name="season_id" required>
                                    <?php foreach ($seasons as $season): ?>
                                        <option value="<?php echo $season['id']; ?>" 
                                                <?php echo ($current_season && $season['id'] == $current_season['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($season['name']); ?>
                                            <?php if ($season['is_active']): ?>
                                                <span class="text-success">(Current)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="update_ladder" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Update Ladder Positions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h4>Current Season</h4>
                    <?php if ($current_season): ?>
                        <h5><?php echo htmlspecialchars($current_season['name']); ?></h5>
                        <p class="mb-0"><?php echo $current_season['ladder_type']; ?> Ladder</p>
                    <?php else: ?>
                        <p class="mb-0">No active season</p>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Links</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="pickleball_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
                            <a href="manage_pickleball_games.php" class="btn btn-outline-secondary btn-sm">Manage Games</a>
                            <a href="enter_pickleball_scores.php" class="btn btn-outline-success btn-sm">Enter Scores</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




