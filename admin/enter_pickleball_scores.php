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
                
                // Get all games this player participated in with actual match assignments
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
                        // Determine which team the player was on using actual match assignments
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
        
        // Process match assignments if provided
        error_log("All POST data: " . print_r($_POST, true));
        if (isset($_POST['match_assignments'])) {
            $match_assignments = $_POST['match_assignments'];
            error_log("Match assignments from POST: " . print_r($match_assignments, true));
            
            // Clear existing match assignments for this game
            $stmt = $conn->prepare("DELETE FROM pickleball_match_assignments WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new match assignments
            foreach ($match_assignments as $match_num => $assignment) {
                if (!empty($assignment['team1_player1_id']) && !empty($assignment['team1_player2_id']) && 
                    !empty($assignment['team2_player1_id']) && !empty($assignment['team2_player2_id'])) {
                    
                    error_log("Inserting match assignment for match $match_num: " . print_r($assignment, true));
                    
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
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        // Process the match scores - store in the main games table
        $match1_team1_score = (int)$_POST['match1_team1_score'];
        $match1_team2_score = (int)$_POST['match1_team2_score'];
        $match2_team1_score = (int)$_POST['match2_team1_score'];
        $match2_team2_score = (int)$_POST['match2_team2_score'];
        $match3_team1_score = (int)$_POST['match3_team1_score'];
        $match3_team2_score = (int)$_POST['match3_team2_score'];
        $match4_team1_score = isset($_POST['match4_team1_score']) ? (int)$_POST['match4_team1_score'] : 0;
        $match4_team2_score = isset($_POST['match4_team2_score']) ? (int)$_POST['match4_team2_score'] : 0;
        $match5_team1_score = isset($_POST['match5_team1_score']) ? (int)$_POST['match5_team1_score'] : 0;
        $match5_team2_score = isset($_POST['match5_team2_score']) ? (int)$_POST['match5_team2_score'] : 0;
        
        error_log("Scores: M1T1=$match1_team1_score, M1T2=$match1_team2_score, M2T1=$match2_team1_score, M2T2=$match2_team2_score, M3T1=$match3_team1_score, M3T2=$match3_team2_score, M4T1=$match4_team1_score, M4T2=$match4_team2_score, M5T1=$match5_team1_score, M5T2=$match5_team2_score");
        
        // Check if the new columns exist first
        $result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match1_team1_score'");
        if ($result->num_rows == 0) {
            throw new Exception("Required columns don't exist. Please run admin/add_doubles_match_columns.sql first.");
        }
        
        // Check if match5 columns exist
        $result5 = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match5_team1_score'");
        $has_match5_columns = $result5->num_rows > 0;
        error_log("Match5 columns exist: " . ($has_match5_columns ? 'YES' : 'NO'));
        
        // Update the game with all match scores
        error_log("Preparing UPDATE statement...");
        
        if ($has_match5_columns) {
            $stmt = $conn->prepare("
                UPDATE pickleball_games 
                SET match1_team1_score = ?, match1_team2_score = ?,
                    match2_team1_score = ?, match2_team2_score = ?,
                    match3_team1_score = ?, match3_team2_score = ?,
                    match4_team1_score = ?, match4_team2_score = ?,
                    match5_team1_score = ?, match5_team2_score = ?,
                    status = 'completed', updated_at = NOW()
                WHERE id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare UPDATE statement: " . $conn->error);
            }
            
            error_log("Binding parameters for 5 matches...");
            $stmt->bind_param("iiiiiiiiiii", 
                $match1_team1_score, $match1_team2_score,
                $match2_team1_score, $match2_team2_score,
                $match3_team1_score, $match3_team2_score,
                $match4_team1_score, $match4_team2_score,
                $match5_team1_score, $match5_team2_score,
                $game_id
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE pickleball_games 
                SET match1_team1_score = ?, match1_team2_score = ?,
                    match2_team1_score = ?, match2_team2_score = ?,
                    match3_team1_score = ?, match3_team2_score = ?,
                    match4_team1_score = ?, match4_team2_score = ?,
                    status = 'completed', updated_at = NOW()
                WHERE id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare UPDATE statement: " . $conn->error);
            }
            
            error_log("Binding parameters for 4 matches...");
            $stmt->bind_param("iiiiiiii", 
                $match1_team1_score, $match1_team2_score,
                $match2_team1_score, $match2_team2_score,
                $match3_team1_score, $match3_team2_score,
                $match4_team1_score, $match4_team2_score,
                $game_id
            );
        }
        
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
            $num_matches = ($game['player5_id'] ? 5 : 3);
            $message = '<div class="alert alert-success">Scores ' . $action . ' successfully for all ' . $num_matches . ' matches!<br><small>Ladder positions updated.</small></div>';
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

// Get games that need scores or can be edited (games with 4-5 players)
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
               p3.name as player3_name, p4.name as player4_name, p5.name as player5_name,
               d.name as division_name
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
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

// Get match assignments for each game
$game_assignments = [];
foreach ($games as $game) {
    $stmt = $conn->prepare("
        SELECT 
            pma.match_number,
            pma.team1_player1_id, pma.team1_player2_id,
            pma.team2_player1_id, pma.team2_player2_id,
            p1.name as team1_p1_name, p2.name as team1_p2_name,
            p3.name as team2_p1_name, p4.name as team2_p2_name
        FROM pickleball_match_assignments pma
        LEFT JOIN pickleball_players p1 ON pma.team1_player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pma.team1_player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pma.team2_player1_id = p3.id
        LEFT JOIN pickleball_players p4 ON pma.team2_player2_id = p4.id
        WHERE pma.game_id = ?
        ORDER BY pma.match_number
    ");
    $stmt->bind_param("i", $game['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $game_assignments[$game['id']] = $assignments;
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
                <p>There are currently no games with 4-5 players (scheduled, in-progress, or completed).</p>
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
                <?php 
                // Calculate player count and match count at the start
                $player_count = 0;
                if ($game['player1_id']) $player_count++;
                if ($game['player2_id']) $player_count++;
                if ($game['player3_id']) $player_count++;
                if ($game['player4_id']) $player_count++;
                if ($game['player5_id']) $player_count++;
                
                $num_matches = $game['player5_id'] ? 5 : 3;
                ?>
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
                            
                            <h6 class="text-primary mb-3">Players (<?php echo $player_count; ?> players = <?php echo $num_matches; ?> matches):</h6>
                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player1_name'] ?: 'Player 1'); ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player2_name'] ?: 'Player 2'); ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player3_name'] ?: 'Player 3'); ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player4_name'] ?: 'Player 4'); ?></div>
                                </div>
                                <?php if ($game['player5_name']): ?>
                                <div class="col-md-2">
                                    <div class="player-name"><?php echo htmlspecialchars($game['player5_name']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            // Debug: Show player count and match count
                            echo "<!-- Debug: Player count: $player_count, Player5 ID: " . ($game['player5_id'] ?: 'NULL') . ", Matches: $num_matches -->";
                            $players = [
                                $game['player1_id'] => ['id' => $game['player1_id'], 'name' => $game['player1_name']],
                                $game['player2_id'] => ['id' => $game['player2_id'], 'name' => $game['player2_name']],
                                $game['player3_id'] => ['id' => $game['player3_id'], 'name' => $game['player3_name']],
                                $game['player4_id'] => ['id' => $game['player4_id'], 'name' => $game['player4_name']]
                            ];
                            if ($game['player5_id']) {
                                $players[$game['player5_id']] = ['id' => $game['player5_id'], 'name' => $game['player5_name']];
                            }
                            ?>
                            
                            <?php for ($match_num = 1; $match_num <= $num_matches; $match_num++): ?>
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
                                        case 4:
                                            $team1_score = $game['match4_team1_score'] ?: 0;
                                            $team2_score = $game['match4_team2_score'] ?: 0;
                                            break;
                                        case 5:
                                            $team1_score = isset($game['match5_team1_score']) ? ($game['match5_team1_score'] ?: 0) : 0;
                                            $team2_score = isset($game['match5_team2_score']) ? ($game['match5_team2_score'] ?: 0) : 0;
                                            break;
                                    }
                                    
                                    $has_existing_scores = $team1_score > 0 || $team2_score > 0;
                                    $team1_won = $team1_score > $team2_score;
                                    
                                    // Get existing match assignment or use default
                                    $assignment = null;
                                    foreach ($game_assignments[$game['id']] as $existing_assignment) {
                                        if ($existing_assignment['match_number'] == $match_num) {
                                            $assignment = $existing_assignment;
                                            break;
                                        }
                                    }
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
                                    
                                    <!-- Match Assignment Selection -->
                                    <div class="row mb-2">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDefaultAssignments(<?php echo $match_num; ?>)">
                                                Set Default Assignments for Match <?php echo $match_num; ?>
                                            </button>
                                            <!-- Hidden inputs with actual player IDs -->
                                            <input type="hidden" id="player1_id_<?php echo $match_num; ?>" value="<?php echo $game['player1_id'] ?: ''; ?>">
                                            <input type="hidden" id="player2_id_<?php echo $match_num; ?>" value="<?php echo $game['player2_id'] ?: ''; ?>">
                                            <input type="hidden" id="player3_id_<?php echo $match_num; ?>" value="<?php echo $game['player3_id'] ?: ''; ?>">
                                            <input type="hidden" id="player4_id_<?php echo $match_num; ?>" value="<?php echo $game['player4_id'] ?: ''; ?>">
                                            <input type="hidden" id="player5_id_<?php echo $match_num; ?>" value="<?php echo $game['player5_id'] ?: ''; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><strong>Team 1:</strong></label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <select class="form-select" name="match_assignments[<?php echo $match_num; ?>][team1_player1_id]" required>
                                                        <option value="">Select Player</option>
                                                        <?php foreach ($players as $player_id => $player): ?>
                                                            <option value="<?php echo $player_id; ?>" 
                                                                <?php echo ($assignment && $assignment['team1_player1_id'] == $player_id) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($player['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-select" name="match_assignments[<?php echo $match_num; ?>][team1_player2_id]" required>
                                                        <option value="">Select Player</option>
                                                        <?php foreach ($players as $player_id => $player): ?>
                                                            <option value="<?php echo $player_id; ?>" 
                                                                <?php echo ($assignment && $assignment['team1_player2_id'] == $player_id) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($player['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><strong>Team 2:</strong></label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <select class="form-select" name="match_assignments[<?php echo $match_num; ?>][team2_player1_id]" required>
                                                        <option value="">Select Player</option>
                                                        <?php foreach ($players as $player_id => $player): ?>
                                                            <option value="<?php echo $player_id; ?>" 
                                                                <?php echo ($assignment && $assignment['team2_player1_id'] == $player_id) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($player['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-select" name="match_assignments[<?php echo $match_num; ?>][team2_player2_id]" required>
                                                        <option value="">Select Player</option>
                                                        <?php foreach ($players as $player_id => $player): ?>
                                                            <option value="<?php echo $player_id; ?>" 
                                                                <?php echo ($assignment && $assignment['team2_player2_id'] == $player_id) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($player['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Score Input -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="team-score">
                                                <h6 class="mb-2">Team 1 Score:</h6>
                                                <input type="number" class="form-control score-input" 
                                                       name="match<?php echo $match_num; ?>_team1_score" 
                                                       value="<?php echo $has_existing_scores ? $team1_score : ''; ?>" 
                                                       min="0" max="21" placeholder="Score">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="team-score">
                                                <h6 class="mb-2">Team 2 Score:</h6>
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
    <script>
        // Form validation and user experience enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add validation to prevent duplicate player selections within a match
            const forms = document.querySelectorAll('.score-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const matchSections = form.querySelectorAll('.match-section');
                    let isValid = true;
                    
                    matchSections.forEach(section => {
                        const selects = section.querySelectorAll('select[name*="match_assignments"]');
                        const selectedPlayers = new Set();
                        
                        selects.forEach(select => {
                            if (select.value) {
                                if (selectedPlayers.has(select.value)) {
                                    alert('Error: Each player can only be selected once per match. Please check your team assignments.');
                                    isValid = false;
                                    return false;
                                }
                                selectedPlayers.add(select.value);
                            }
                        });
                        
                        // Check if all 4 players are selected for each match
                        if (selectedPlayers.size > 0 && selectedPlayers.size < 4) {
                            alert('Error: Please select all 4 players for each match.');
                            isValid = false;
                            return false;
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
                
                // Real-time validation for player selection
                const selects = form.querySelectorAll('select[name*="match_assignments"]');
                selects.forEach(select => {
                    select.addEventListener('change', function() {
                        const matchSection = this.closest('.match-section');
                        const matchSelects = matchSection.querySelectorAll('select[name*="match_assignments"]');
                        const selectedValues = Array.from(matchSelects).map(s => s.value).filter(v => v);
                        
                        // Check for duplicates
                        const duplicates = selectedValues.filter((item, index) => selectedValues.indexOf(item) !== index);
                        if (duplicates.length > 0) {
                            this.style.borderColor = 'red';
                            this.title = 'Duplicate player selection';
                        } else {
                            this.style.borderColor = '';
                            this.title = '';
                        }
                    });
                });
            });
            
            // Auto-populate default match assignments if none exist
            forms.forEach(form => {
                const matchSections = form.querySelectorAll('.match-section');
                matchSections.forEach((section, index) => {
                    const selects = section.querySelectorAll('select[name*="match_assignments"]');
                    const hasSelections = Array.from(selects).some(select => select.value);
                    
                    if (!hasSelections) {
                        // Set default assignments based on match number
                        const matchNum = index + 1;
                        const defaultAssignments = getDefaultAssignments(matchNum);
                        
                        selects.forEach((select, selectIndex) => {
                            if (defaultAssignments[selectIndex]) {
                                select.value = defaultAssignments[selectIndex];
                            }
                        });
                    }
                });
            });
        });
        
        function getDefaultAssignments(matchNum) {
            // Get the actual player IDs from hidden inputs
            const player1Id = document.getElementById('player1_id_' + matchNum)?.value;
            const player2Id = document.getElementById('player2_id_' + matchNum)?.value;
            const player3Id = document.getElementById('player3_id_' + matchNum)?.value;
            const player4Id = document.getElementById('player4_id_' + matchNum)?.value;
            const player5Id = document.getElementById('player5_id_' + matchNum)?.value;
            
            console.log('Player IDs from hidden inputs:', {player1Id, player2Id, player3Id, player4Id, player5Id});
            
            // Default assignments using actual player IDs
            const defaults = {
                1: [player1Id, player2Id, player3Id, player4Id], // P1&P2 vs P3&P4
                2: [player1Id, player3Id, player2Id, player4Id], // P1&P3 vs P2&P4
                3: [player1Id, player4Id, player2Id, player3Id], // P1&P4 vs P2&P3
                4: [player1Id, player5Id, player2Id, player3Id], // P1&P5 vs P2&P3 (for 5-player games)
                5: [player2Id, player4Id, player3Id, player5Id]  // P2&P4 vs P3&P5 (for 5-player games)
            };
            
            const result = defaults[matchNum] || [];
            console.log('Default assignments for match', matchNum, ':', result);
            console.log('Individual values:', result[0], result[1], result[2], result[3]);
            return result;
        }
        
        function setDefaultAssignments(matchNum) {
            console.log('Setting default assignments for match', matchNum);
            const defaultAssignments = getDefaultAssignments(matchNum);
            
            // Find the match section for this match number
            const matchSections = document.querySelectorAll('.match-section');
            const targetSection = matchSections[matchNum - 1]; // matchNum is 1-based, array is 0-based
            
            if (targetSection) {
                const selects = targetSection.querySelectorAll('select[name*="match_assignments"]');
                console.log('Found', selects.length, 'selects in match', matchNum);
                console.log('Default assignments array:', defaultAssignments);
                
                selects.forEach((select, selectIndex) => {
                    console.log('Processing select', selectIndex, 'with value', defaultAssignments[selectIndex]);
                    console.log('Select options:', Array.from(select.options).map(opt => opt.value + ':' + opt.text));
                    
                    if (defaultAssignments[selectIndex] && defaultAssignments[selectIndex] !== 'null' && defaultAssignments[selectIndex] !== '') {
                        // Try to set the value
                        select.value = defaultAssignments[selectIndex];
                        
                        // Check if it was actually set
                        if (select.value === defaultAssignments[selectIndex]) {
                            console.log('✓ Set select', selectIndex, 'to value', defaultAssignments[selectIndex]);
                        } else {
                            console.log('✗ Failed to set select', selectIndex, 'to value', defaultAssignments[selectIndex], 'current value is:', select.value);
                            
                            // Try to find the option manually
                            const option = Array.from(select.options).find(opt => opt.value === defaultAssignments[selectIndex]);
                            if (option) {
                                option.selected = true;
                                console.log('✓ Manually selected option:', option.text);
                            } else {
                                console.log('✗ Option with value', defaultAssignments[selectIndex], 'not found in dropdown');
                            }
                        }
                    } else {
                        console.log('✗ Skipped select', selectIndex, 'because value is:', defaultAssignments[selectIndex]);
                    }
                });
            } else {
                console.error('Could not find match section for match', matchNum);
            }
        }
    </script>
</body>
</html>