<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = '';
$errors = [];

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
    $errors[] = "No active pickleball season found.";
}

// Debug mode - set to true to see detailed output
$debug_mode = isset($_GET['debug']) || isset($_POST['debug']);
$debug_player_id = isset($_GET['debug_player']) ? (int)$_GET['debug_player'] : (isset($_POST['debug_player']) ? (int)$_POST['debug_player'] : null);
$debug_game_id = isset($_GET['debug_game']) ? (int)$_GET['debug_game'] : (isset($_POST['debug_game']) ? (int)$_POST['debug_game'] : null);

// Recalculate standings if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['recalculate']) && empty($errors)) {
    $debug_output = [];
    
    // Get all players grouped by division
    $players_by_division = [];
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.division_id
        FROM pickleball_players p
        WHERE p.is_active = 1 AND p.division_id IS NOT NULL
        ORDER BY p.division_id, p.name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $division_id = $row['division_id'];
        if (!isset($players_by_division[$division_id])) {
            $players_by_division[$division_id] = [];
        }
        $players_by_division[$division_id][] = $row;
    }
    $stmt->close();
    
    $season_id = $current_season['id'];
    $all_player_stats = [];
    $total_processed = 0;
    
    foreach ($players_by_division as $division_id => $division_players) {
        $player_stats = [];
        foreach ($division_players as $player) {
            $player_id = $player['id'];
            
            // Get all games this player participated in - use pg.* to get all match columns
            $stmt = $conn->prepare("
                SELECT pg.*
                FROM pickleball_games pg
                WHERE pg.season_id = ? 
                AND pg.status = 'completed'
                AND (pg.player1_id = ? OR pg.player2_id = ? OR pg.player3_id = ? OR pg.player4_id = ? 
                     OR pg.player5_id = ? OR pg.player6_id = ? OR pg.player7_id = ?)
                ORDER BY pg.date
            ");
            $stmt->bind_param("iiiiiiii", $season_id, $player_id, $player_id, $player_id, $player_id, $player_id, $player_id, $player_id);
            $stmt->execute();
            $games_result = $stmt->get_result();
            $games = [];
            while ($row = $games_result->fetch_assoc()) {
                $games[] = $row;
            }
            $stmt->close();
            
            $wins = 0;
            $losses = 0;
            $points_scored = 0;
            $points_against = 0;
            
            // Process each game and all its matches
            foreach ($games as $game) {
                $game_id = $game['id'];
                
                // Get all match assignments for this game
                $assignments_stmt = $conn->prepare("
                    SELECT match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id
                    FROM pickleball_match_assignments
                    WHERE game_id = ?
                    ORDER BY match_number
                ");
                $assignments_stmt->bind_param("i", $game_id);
                $assignments_stmt->execute();
                $assignments_result = $assignments_stmt->get_result();
                $assignments = [];
                while ($assignment = $assignments_result->fetch_assoc()) {
                    $assignments[$assignment['match_number']] = $assignment;
                }
                $assignments_stmt->close();
                
                // Find all matches with scores (check all possible match columns dynamically)
                $matches_with_scores = [];
                foreach ($game as $key => $value) {
                    if (preg_match('/^match(\d+)_team[12]_score$/', $key, $matches)) {
                        $match_num = (int)$matches[1];
                        if (!isset($matches_with_scores[$match_num])) {
                            $matches_with_scores[$match_num] = [
                                'team1_score' => $game["match{$match_num}_team1_score"] ?? null,
                                'team2_score' => $game["match{$match_num}_team2_score"] ?? null
                            ];
                        }
                    }
                }
                
                // Process matches where player was assigned AND has scores
                foreach ($assignments as $match_num => $assignment) {
                    // Get scores for this match number
                    $team1_score = null;
                    $team2_score = null;
                    
                    if (isset($matches_with_scores[$match_num])) {
                        $team1_score = $matches_with_scores[$match_num]['team1_score'];
                        $team2_score = $matches_with_scores[$match_num]['team2_score'];
                    } else {
                        // Fallback: try direct access
                        $team1_score = $game["match{$match_num}_team1_score"] ?? null;
                        $team2_score = $game["match{$match_num}_team2_score"] ?? null;
                    }
                    
                    if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                        $debug_output[] = "Game {$game_id}, Match {$match_num}: T1={$team1_score}, T2={$team2_score}, Player {$player_id} ({$player['name']})";
                    }
                    
                    // Handle NULL values that might be 0 (legacy data issue)
                    // If one score is NULL and the other is > 0, treat NULL as 0
                    if ($team1_score === null && ($team2_score !== null && $team2_score > 0)) {
                        $team1_score = 0;
                        if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                            $debug_output[] = "  → Converted NULL to 0 for team1 (team2 has {$team2_score})";
                        }
                    }
                    if ($team2_score === null && ($team1_score !== null && $team1_score > 0)) {
                        $team2_score = 0;
                        if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                            $debug_output[] = "  → Converted NULL to 0 for team2 (team1 has {$team1_score})";
                        }
                    }
                    
                    // Skip if no final scores - both must be set (or inferred as 0) and not both zero
                    // Valid scores include: 11-0, 0-11, 11-9, etc. (one team can have 0)
                    if ($team1_score === null || $team2_score === null) {
                        if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                            $debug_output[] = "  → SKIPPED: Missing scores (T1={$team1_score}, T2={$team2_score})";
                        }
                        continue; // Missing scores (both null or both can't be inferred)
                    }
                    if ($team1_score == 0 && $team2_score == 0) {
                        if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                            $debug_output[] = "  → SKIPPED: Both scores are 0 (not entered)";
                        }
                        continue; // Both zero means not entered
                    }
                    // If we get here, at least one score is > 0, which is valid
                    
                    // Determine which team the player was on using match assignments
                    $player_on_team1 = false;
                    $player_on_team2 = false;
                    
                    if ($assignment['team1_player1_id'] == $player_id || $assignment['team1_player2_id'] == $player_id) {
                        $player_on_team1 = true;
                    } elseif ($assignment['team2_player1_id'] == $player_id || $assignment['team2_player2_id'] == $player_id) {
                        $player_on_team2 = true;
                    }
                    
                    if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                        $debug_output[] = "  → Player on Team1: " . ($player_on_team1 ? 'YES' : 'NO') . ", Team2: " . ($player_on_team2 ? 'YES' : 'NO');
                    }
                    
                    // Only count if player was assigned to this match
                    if ($player_on_team1 || $player_on_team2) {
                        // Convert to integers to ensure proper comparison (0 is valid)
                        $team1_score_int = (int)$team1_score;
                        $team2_score_int = (int)$team2_score;
                        
                        if ($player_on_team1) {
                            $points_scored += $team1_score_int;
                            $points_against += $team2_score_int;
                            if ($team1_score_int > $team2_score_int) {
                                $wins++;
                                if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                                    $debug_output[] = "  → COUNTED: WIN ({$team1_score_int}-{$team2_score_int})";
                                }
                            } elseif ($team2_score_int > $team1_score_int) {
                                $losses++;
                                if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                                    $debug_output[] = "  → COUNTED: LOSS ({$team1_score_int}-{$team2_score_int})";
                                }
                            }
                        } elseif ($player_on_team2) {
                            $points_scored += $team2_score_int;
                            $points_against += $team1_score_int;
                            if ($team2_score_int > $team1_score_int) {
                                $wins++;
                                if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                                    $debug_output[] = "  → COUNTED: WIN ({$team2_score_int}-{$team1_score_int})";
                                }
                            } elseif ($team1_score_int > $team2_score_int) {
                                $losses++;
                                if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                                    $debug_output[] = "  → COUNTED: LOSS ({$team2_score_int}-{$team1_score_int})";
                                }
                            }
                        }
                    } else {
                        if ($debug_mode && (!$debug_player_id || $debug_player_id == $player_id) && (!$debug_game_id || $debug_game_id == $game_id)) {
                            $debug_output[] = "  → SKIPPED: Player not assigned to this match";
                        }
                    }
                }
                
                if ($debug_mode && !empty($assignments)) {
                    $debug_output[] = "Found " . count($assignments) . " assignments, " . count($matches_with_scores) . " matches with scores";
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
                'total_matches' => $total_matches,
                'points_scored' => $points_scored,
                'points_against' => $points_against,
                'win_percentage' => $win_percentage,
                'point_differential' => $point_differential
            ];
            
            $total_processed++;
        }
        
        // Filter players with minimum 8 games played
        $MIN_GAMES_REQUIRED = 8;
        $player_stats = array_filter($player_stats, function($stats) use ($MIN_GAMES_REQUIRED) {
            return $stats['total_matches'] >= $MIN_GAMES_REQUIRED;
        });
        
        usort($player_stats, 'pickleballRankCompare');
        
        // Assign positions within this division
        foreach ($player_stats as $index => $stats) {
            $stats['position'] = $index + 1;
            $all_player_stats[] = $stats;
        }
    }
    
    // Update ladder positions for all players
    $updated_count = 0;
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
        if ($stmt->execute()) {
            $updated_count++;
        }
        $stmt->close();
    }
    
    $message = '<div class="alert alert-success">Standings recalculated successfully!<br>
                Updated ' . $updated_count . ' players across ' . count($players_by_division) . ' divisions.<br>
                Processed ' . $total_processed . ' players total.</div>';
    
    if ($debug_mode && !empty($debug_output)) {
        $message .= '<div class="alert alert-info mt-3"><h6>Debug Output:</h6><pre style="max-height: 400px; overflow-y: auto; font-size: 0.85rem;">' . 
                    htmlspecialchars(implode("\n", $debug_output)) . '</pre></div>';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recalculate Pickleball Standings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">🏓 Recalculate Pickleball Standings</h1>
                            <p class="lead mb-0">Recalculate wins, losses, and point differentials for all players</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light btn-lg">
                                ← Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php echo $message; ?>
        
        <?php if ($current_season): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Current Season: <?php echo htmlspecialchars($current_season['name']); ?></h5>
                </div>
                <div class="card-body">
                    <p><strong>Season ID:</strong> <?php echo $current_season['id']; ?></p>
                    <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($current_season['start_date'])); ?></p>
                    <p><strong>End Date:</strong> <?php echo date('M j, Y', strtotime($current_season['end_date'])); ?></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">⚠️ Recalculate Standings</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        This will recalculate wins, losses, point differentials, and ladder positions for all players in the current season.
                        The calculation will:
                    </p>
                    <ul>
                        <li>Only count matches where players are assigned (from match assignments table)</li>
                        <li>Only count matches with final scores (both scores set and at least one > 0)</li>
                        <li>Process all match numbers dynamically (not just matches 1-5)</li>
                        <li>Include players 1-7 in calculations</li>
                        <li>Update ladder positions based on win percentage, then point differential</li>
                    </ul>
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to recalculate all standings? This will update all player statistics and ladder positions.');">
                        <input type="hidden" name="recalculate" value="1">
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="debug" id="debugMode" value="1" <?php echo $debug_mode ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="debugMode">
                                    Enable Debug Mode (shows detailed match processing information)
                                </label>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="debugPlayer" class="form-label">Debug Player ID (optional):</label>
                                    <input type="number" name="debug_player" id="debugPlayer" class="form-control" value="<?php echo $debug_player_id ?: ''; ?>" placeholder="Leave empty for all players">
                                </div>
                                <div class="col-md-6">
                                    <label for="debugGame" class="form-label">Debug Game ID (optional):</label>
                                    <input type="number" name="debug_game" id="debugGame" class="form-control" value="<?php echo $debug_game_id ?: ''; ?>" placeholder="Leave empty for all games">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning btn-lg">
                            🔄 Recalculate All Standings
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <h4>No Active Season</h4>
                <p>Please activate a pickleball season before recalculating standings.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

