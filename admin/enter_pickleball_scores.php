<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

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

$message = '';

// Handle score submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_scores'])) {
    $game_id = (int)$_POST['game_id'];
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    try {
        // Get the game details (allow all statuses for editing)
        error_log("Looking for game ID: $game_id");
        $stmt = $conn->prepare("
            SELECT * FROM pickleball_games 
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare game query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $game_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute game query: " . $stmt->error);
        }
        
        $game = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$game) {
            throw new Exception("Game not found with ID: $game_id");
        }
        
        error_log("Found game: " . print_r($game, true));
        
        // Process the 3 doubles matches - store in the main games table
        $match1_team1_score = (int)$_POST['match1_team1_score'];
        $match1_team2_score = (int)$_POST['match1_team2_score'];
        $match2_team1_score = (int)$_POST['match2_team1_score'];
        $match2_team2_score = (int)$_POST['match2_team2_score'];
        $match3_team1_score = (int)$_POST['match3_team1_score'];
        $match3_team2_score = (int)$_POST['match3_team2_score'];
        
        error_log("Scores: M1T1=$match1_team1_score, M1T2=$match1_team2_score, M2T1=$match2_team1_score, M2T2=$match2_team2_score, M3T1=$match3_team1_score, M3T2=$match3_team2_score");
        
        // Check if the new columns exist first
        $result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match1_team1_score'");
        if ($result->num_rows == 0) {
            throw new Exception("Required columns don't exist. Please run admin/add_doubles_match_columns.sql first.");
        }
        
        // Update the game with all match scores
        error_log("Preparing UPDATE statement...");
        $stmt = $conn->prepare("
            UPDATE pickleball_games 
            SET match1_team1_score = ?, match1_team2_score = ?,
                match2_team1_score = ?, match2_team2_score = ?,
                match3_team1_score = ?, match3_team2_score = ?,
                status = 'completed', updated_at = NOW()
            WHERE id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare UPDATE statement: " . $conn->error);
        }
        
        error_log("Binding parameters...");
        $stmt->bind_param("iiiiiii", 
            $match1_team1_score, $match1_team2_score,
            $match2_team1_score, $match2_team2_score,
            $match3_team1_score, $match3_team2_score,
            $game_id
        );
        
        error_log("Executing UPDATE...");
        if ($stmt->execute()) {
            // Update ladder positions after scores are submitted
            try {
                $ladder_message = updateLadderPositions($conn, $game['season_id']);
                error_log("Ladder positions updated: " . $ladder_message);
            } catch (Exception $e) {
                error_log("Ladder update failed: " . $e->getMessage());
                // Don't fail the whole operation if ladder update fails
            }
            
            // Determine if this was an update or new submission
            $was_completed = $game['status'] === 'completed';
            $action = $was_completed ? 'updated' : 'submitted';
            $message = '<div class="alert alert-success">Scores ' . $action . ' successfully for all 3 matches!<br><small>Ladder positions updated.</small></div>';
            error_log("UPDATE successful!");
        } else {
            throw new Exception("Failed to execute UPDATE statement: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Exception in score submission: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Get current season
$current_season = null;
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}
$stmt->close();

// Get games that need scores or can be edited (doubles games with 4 players)
$games = [];
if ($current_season) {
    // Determine status filter
    $status_filter = "('scheduled', 'in_progress', 'completed')";
    if (isset($_GET['filter'])) {
        switch ($_GET['filter']) {
            case 'scheduled':
                $status_filter = "('scheduled', 'in_progress')";
                break;
            case 'completed':
                $status_filter = "('completed')";
                break;
            case 'all':
            default:
                $status_filter = "('scheduled', 'in_progress', 'completed')";
                break;
        }
    }
    
    $stmt = $conn->prepare("
        SELECT pg.*, p1.name as player1_name, p2.name as player2_name,
               p3.name as player3_name, p4.name as player4_name,
               d.name as division_name
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
        WHERE pg.season_id = ? AND pg.status IN $status_filter
        AND pg.player1_id IS NOT NULL AND pg.player2_id IS NOT NULL 
        AND pg.player3_id IS NOT NULL AND pg.player4_id IS NOT NULL
        ORDER BY pg.date ASC
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $games[] = $row;
    }
    $stmt->close();
}

// No need for separate stats table - scores are stored in the main games table
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter/Edit Pickleball Scores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .game-card {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        .match-section {
            background-color: white;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            margin-bottom: 1rem;
        }
        .team-score {
            background-color: #e9ecef;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .player-name {
            font-weight: bold;
            color: #495057;
        }
        .score-input {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
        }
        .existing-scores {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
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
                            <h1 class="display-4 mb-0">🏓 Enter/Edit Pickleball Scores</h1>
                            <p class="lead mb-0">Track wins and points for 3 doubles matches per court</p>
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
        <?php echo $message; ?>
        
        <!-- Filter Options -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6>Filter Games:</h6>
                        <div class="btn-group" role="group">
                            <a href="?filter=all" class="btn btn-outline-primary <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'active' : ''; ?>">All Games</a>
                            <a href="?filter=scheduled" class="btn btn-outline-warning <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'scheduled') ? 'active' : ''; ?>">Need Scores</a>
                            <a href="?filter=completed" class="btn btn-outline-success <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'completed') ? 'active' : ''; ?>">Completed</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($games)): ?>
            <div class="alert alert-info">
                <h4>No Games Found</h4>
                <p>There are currently no doubles games with 4 players (scheduled, in-progress, or completed).</p>
                <?php if ($current_season): ?>
                    <p><strong>Debug Info:</strong></p>
                    <ul>
                        <li>Current Season ID: <?php echo $current_season['id']; ?></li>
                        <li>Season Name: <?php echo htmlspecialchars($current_season['name']); ?></li>
                    </ul>
                <?php else: ?>
                    <p><strong>No active season found.</strong></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($games as $game): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <?php echo date('l, M j, Y', strtotime($game['date'])); ?> at 
                                    <?php echo date('g:i A', strtotime($game['date'])); ?>
                                </h5>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($game['location']); ?> • 
                                    <?php echo $game['division_name'] ? htmlspecialchars($game['division_name']) : 'No Division'; ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($game['status'] === 'completed'): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php elseif ($game['status'] === 'in_progress'): ?>
                                    <span class="badge bg-warning">In Progress</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Scheduled</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="score-form">
                            <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                            
                            <h6 class="text-primary mb-3">Players:</h6>
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player1_name'] ?: 'Player 1'); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player2_name'] ?: 'Player 2'); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player3_name'] ?: 'Player 3'); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player4_name'] ?: 'Player 4'); ?></div>
                                </div>
                            </div>
                            
                            <?php for ($match_num = 1; $match_num <= 3; $match_num++): ?>
                                <div class="match-section">
                                    <h6 class="text-primary mb-3">Match <?php echo $match_num; ?>:</h6>
                                    
                                    <?php
                                    // Get existing scores from the main games table
                                    $team1_score = 0;
                                    $team2_score = 0;
                                    $has_existing_scores = false;
                                    
                                    switch ($match_num) {
                                        case 1:
                                            $team1_score = $game['match1_team1_score'] ?: 0;
                                            $team2_score = $game['match1_team2_score'] ?: 0;
                                            break;
                                        case 2:
                                            $team1_score = $game['match2_team1_score'] ?: 0;
                                            $team2_score = $game['match2_team2_score'] ?: 0;
                                            break;
                                        case 3:
                                            $team1_score = $game['match3_team1_score'] ?: 0;
                                            $team2_score = $game['match3_team2_score'] ?: 0;
                                            break;
                                    }
                                    
                                    $has_existing_scores = $team1_score > 0 || $team2_score > 0;
                                    $team1_won = $team1_score > $team2_score;
                                    ?>
                                    
                                    <?php if ($has_existing_scores): ?>
                                        <div class="existing-scores">
                                            <strong>Current Scores:</strong> 
                                            Team 1: <span class="badge bg-primary"><?php echo $team1_score; ?></span> | 
                                            Team 2: <span class="badge bg-primary"><?php echo $team2_score; ?></span>
                                            <?php if ($team1_won): ?>
                                                <span class="badge bg-success ms-2">Team 1 Won</span>
                                            <?php else: ?>
                                                <span class="badge bg-success ms-2">Team 2 Won</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="team-score">
                                                <h6 class="mb-2">
                                                    <?php
                                                    switch ($match_num) {
                                                        case 1: echo htmlspecialchars($game['player1_name']) . ' & ' . htmlspecialchars($game['player2_name']); break;
                                                        case 2: echo htmlspecialchars($game['player1_name']) . ' & ' . htmlspecialchars($game['player3_name']); break;
                                                        case 3: echo htmlspecialchars($game['player1_name']) . ' & ' . htmlspecialchars($game['player4_name']); break;
                                                    }
                                                    ?>
                                                </h6>
                                                <input type="number" class="form-control score-input" 
                                                       name="match<?php echo $match_num; ?>_team1_score" 
                                                       value="<?php echo $has_existing_scores ? $team1_score : ''; ?>" 
                                                       min="0" max="21" placeholder="Score">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="team-score">
                                                <h6 class="mb-2">
                                                    <?php
                                                    switch ($match_num) {
                                                        case 1: echo htmlspecialchars($game['player3_name']) . ' & ' . htmlspecialchars($game['player4_name']); break;
                                                        case 2: echo htmlspecialchars($game['player2_name']) . ' & ' . htmlspecialchars($game['player4_name']); break;
                                                        case 3: echo htmlspecialchars($game['player2_name']) . ' & ' . htmlspecialchars($game['player3_name']); break;
                                                    }
                                                    ?>
                                                </h6>
                                                <input type="number" class="form-control score-input" 
                                                       name="match<?php echo $match_num; ?>_team2_score" 
                                                       value="<?php echo $has_existing_scores ? $team2_score : ''; ?>" 
                                                       min="0" max="21" placeholder="Score">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" name="submit_scores" class="btn btn-success btn-lg">
                                        <?php echo $game['status'] === 'completed' ? 'Update Scores' : 'Submit All Scores'; ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Navigation Links -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="pickleball_dashboard.php" class="btn btn-primary me-2">← Back to Dashboard</a>
                <a href="view_player_stats.php" class="btn btn-info me-2">View Player Stats</a>
                <a href="update_ladder_positions.php" class="btn btn-warning">Update Ladder Positions</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>