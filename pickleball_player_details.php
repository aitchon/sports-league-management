<?php
include 'admin/db.php';

// Fetch player ID from the query string
$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : null;

if (!$player_id) {
    echo "Player ID is missing.";
    exit();
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

// Fetch player details
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.division_id, d.name as division_name
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON p.division_id = d.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $player = $conn->query("
        SELECT p.id, p.name, p.division_id, d.name as division_name
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON p.division_id = d.id
        WHERE p.id = $player_id
    ")->fetch_assoc();
}

if (!$player) {
    echo "Player not found.";
    exit();
}

// Fetch all matches for this player
$matches = [];
if ($current_season) {
    // Get all games this player participated in - use pg.* to get all match columns dynamically
    $stmt = $conn->prepare("
        SELECT DISTINCT pg.*,
               p1.name AS player1_name, p2.name AS player2_name,
               p3.name AS player3_name, p4.name AS player4_name,
               p5.name AS player5_name, p6.name AS player6_name, p7.name AS player7_name,
               p1.id AS player1_id, p2.id AS player2_id,
               p3.id AS player3_id, p4.id AS player4_id,
               p5.id AS player5_id, p6.id AS player6_id, p7.id AS player7_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
        LEFT JOIN pickleball_players p6 ON pg.player6_id = p6.id
        LEFT JOIN pickleball_players p7 ON pg.player7_id = p7.id
        WHERE pg.season_id = ? 
        AND pg.status = 'completed'
        AND (pg.player1_id = ? OR pg.player2_id = ? OR pg.player3_id = ? 
             OR pg.player4_id = ? OR pg.player5_id = ? OR pg.player6_id = ? OR pg.player7_id = ?)
        ORDER BY pg.date DESC
    ");
    $stmt->bind_param("iiiiiiii", $current_season['id'], $player_id, $player_id, $player_id, 
                     $player_id, $player_id, $player_id, $player_id);
    $stmt->execute();
    $games_result = $stmt->get_result();
    $stmt->close();
} else {
    $games_result = $conn->query("
        SELECT DISTINCT pg.*,
               p1.name AS player1_name, p2.name AS player2_name,
               p3.name AS player3_name, p4.name AS player4_name,
               p5.name AS player5_name, p6.name AS player6_name, p7.name AS player7_name,
               p1.id AS player1_id, p2.id AS player2_id,
               p3.id AS player3_id, p4.id AS player4_id,
               p5.id AS player5_id, p6.id AS player6_id, p7.id AS player7_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
        LEFT JOIN pickleball_players p6 ON pg.player6_id = p6.id
        LEFT JOIN pickleball_players p7 ON pg.player7_id = p7.id
        WHERE pg.status = 'completed'
        AND (pg.player1_id = $player_id OR pg.player2_id = $player_id OR pg.player3_id = $player_id 
             OR pg.player4_id = $player_id OR pg.player5_id = $player_id OR pg.player6_id = $player_id OR pg.player7_id = $player_id)
        ORDER BY pg.date DESC
    ");
}

// Process each game and its matches
while ($game = $games_result->fetch_assoc()) {
    $game_id = $game['id'];
    
    // Get match assignments for this game
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
    
    // Only process matches where player was assigned
    $matches_found = [];
    
    // Check all assignments to find matches player was assigned to
    foreach ($assignments as $match_num => $assignment) {
        $player_in_match = false;
        $player_on_team1 = false;
        $player_on_team2 = false;
        
        if ($assignment['team1_player1_id'] == $player_id || $assignment['team1_player2_id'] == $player_id) {
            $player_on_team1 = true;
            $player_in_match = true;
        } elseif ($assignment['team2_player1_id'] == $player_id || $assignment['team2_player2_id'] == $player_id) {
            $player_on_team2 = true;
            $player_in_match = true;
        }
        
        // Only add matches where player was actually assigned
        if ($player_in_match) {
            $matches_found[$match_num] = [
                'assignment' => $assignment,
                'player_on_team1' => $player_on_team1,
                'player_on_team2' => $player_on_team2
            ];
        }
    }
    
    // Process all found matches (only those with assignments)
    foreach ($matches_found as $match_num => $match_info) {
        $team1_score = $game["match{$match_num}_team1_score"] ?? null;
        $team2_score = $game["match{$match_num}_team2_score"] ?? null;
        
        // Handle NULL values that might be 0 (legacy data issue)
        // If one score is NULL and the other is > 0, treat NULL as 0
        if ($team1_score === null && ($team2_score !== null && $team2_score > 0)) {
            $team1_score = 0;
        }
        if ($team2_score === null && ($team1_score !== null && $team1_score > 0)) {
            $team2_score = 0;
        }
        
        // Skip if no final scores - both must be set (or inferred as 0) and not both zero
        // Valid scores include: 11-0, 0-11, 11-9, etc. (one team can have 0)
        if ($team1_score === null || $team2_score === null) {
            continue; // Missing scores (both null or both can't be inferred)
        }
        if ($team1_score == 0 && $team2_score == 0) {
            continue; // Both zero means not entered
        }
        // If we get here, at least one score is > 0, which is valid
        
        $assignment = $match_info['assignment'];
        $player_on_team1 = $match_info['player_on_team1'];
        $player_on_team2 = $match_info['player_on_team2'];
        
        // Get teammate and opponents
        $teammate_id = null;
        $opponent1_id = null;
        $opponent2_id = null;
        
        if ($assignment && $player_on_team1) {
            $teammate_id = ($assignment['team1_player1_id'] == $player_id) 
                ? $assignment['team1_player2_id'] 
                : $assignment['team1_player1_id'];
            $opponent1_id = $assignment['team2_player1_id'];
            $opponent2_id = $assignment['team2_player2_id'];
            $player_score = $team1_score ?? 0;
            $opponent_score = $team2_score ?? 0;
        } elseif ($assignment && $player_on_team2) {
            $teammate_id = ($assignment['team2_player1_id'] == $player_id) 
                ? $assignment['team2_player2_id'] 
                : $assignment['team2_player1_id'];
            $opponent1_id = $assignment['team1_player1_id'];
            $opponent2_id = $assignment['team1_player2_id'];
            $player_score = $team2_score ?? 0;
            $opponent_score = $team1_score ?? 0;
        } else {
            // No assignment, but player was in game - can't determine team, show as unknown
            $player_score = ($team1_score ?? 0) + ($team2_score ?? 0); // Can't determine which team
            $opponent_score = 0;
        }
        
        // Get teammate and opponent names
        $teammate_name = '';
        if ($teammate_id) {
            $teammate_stmt = $conn->prepare("SELECT name FROM pickleball_players WHERE id = ?");
            $teammate_stmt->bind_param("i", $teammate_id);
            $teammate_stmt->execute();
            $teammate_result = $teammate_stmt->get_result();
            if ($teammate_row = $teammate_result->fetch_assoc()) {
                $teammate_name = $teammate_row['name'];
            }
            $teammate_stmt->close();
        }
        
        $opponent_names = [];
        foreach ([$opponent1_id, $opponent2_id] as $opp_id) {
            if ($opp_id) {
                $opp_stmt = $conn->prepare("SELECT name FROM pickleball_players WHERE id = ?");
                $opp_stmt->bind_param("i", $opp_id);
                $opp_stmt->execute();
                $opp_result = $opp_stmt->get_result();
                if ($opp_row = $opp_result->fetch_assoc()) {
                    $opponent_names[] = $opp_row['name'];
                }
                $opp_stmt->close();
            }
        }
        
        // Determine result (only if we have both scores and assignment)
        $won = false;
        $differential = 0;
        if ($assignment) {
            // Convert to integers to ensure proper comparison (0 is valid)
            $team1_score_int = (int)$team1_score;
            $team2_score_int = (int)$team2_score;
            
            if ($player_on_team1) {
                $won = ($team1_score_int > $team2_score_int);
                $differential = $team1_score_int - $team2_score_int;
            } elseif ($player_on_team2) {
                $won = ($team2_score_int > $team1_score_int);
                $differential = $team2_score_int - $team1_score_int;
            }
        }
        
        // Get other players in the game for display
        $other_players = [];
        foreach ([$game['player1_name'], $game['player2_name'], $game['player3_name'], 
                  $game['player4_name'], $game['player5_name'], $game['player6_name'], $game['player7_name']] as $name) {
            if ($name && $name != $player['name']) {
                $other_players[] = $name;
            }
        }
        
        $matches[] = [
            'date' => $game['date'],
            'location' => $game['location'],
            'match_number' => $match_num,
            'player_score' => $player_score,
            'opponent_score' => $opponent_score,
            'won' => $won,
            'differential' => $differential,
            'teammate_name' => $teammate_name,
            'opponent_names' => implode(' / ', $opponent_names),
            'other_players' => implode(', ', $other_players)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($player['name']); ?> - Match History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .match-table {
            margin-top: 2rem;
        }
        .match-table th {
            background-color: #e9ecef;
            font-weight: 600;
        }
        .win-badge {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: bold;
        }
        .loss-badge {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: bold;
        }
        .differential-positive {
            color: #28a745;
            font-weight: bold;
        }
        .differential-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .differential-neutral {
            color: #6c757d;
            font-weight: bold;
        }
        .stats-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2E8B57;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏓 <?php echo htmlspecialchars($player['name']); ?></h1>
                    <?php if ($player['division_name']): ?>
                        <p class="lead mb-0"><?php echo htmlspecialchars($player['division_name']); ?> Division</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="container mb-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <a href="pickleball_standings.php" class="btn btn-success">🏆 Standings</a>
                            <a href="pickleball_schedule.php" class="btn btn-info">📅 Schedule</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($matches)): ?>
            <div class="alert alert-info">
                <h4>No Matches Found</h4>
                <p>This player has not played any completed matches yet.</p>
            </div>
        <?php else: ?>
            <!-- Summary Statistics -->
            <?php
            $total_matches = count($matches);
            $wins = 0;
            $losses = 0;
            $total_differential = 0;
            
            foreach ($matches as $match) {
                if ($match['won']) {
                    $wins++;
                } else {
                    $losses++;
                }
                $total_differential += $match['differential'];
            }
            
            $win_percentage = $total_matches > 0 ? ($wins / $total_matches) * 100 : 0;
            ?>
            
            <div class="stats-summary">
                <div class="row">
                    <div class="col-md-3 stat-item">
                        <div class="stat-value"><?php echo $total_matches; ?></div>
                        <div class="stat-label">Total Matches</div>
                    </div>
                    <div class="col-md-3 stat-item">
                        <div class="stat-value text-success"><?php echo $wins; ?></div>
                        <div class="stat-label">Wins</div>
                    </div>
                    <div class="col-md-3 stat-item">
                        <div class="stat-value text-danger"><?php echo $losses; ?></div>
                        <div class="stat-label">Losses</div>
                    </div>
                    <div class="col-md-3 stat-item">
                        <div class="stat-value"><?php echo number_format($win_percentage, 1); ?>%</div>
                        <div class="stat-label">Win Percentage</div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12 stat-item">
                        <div class="stat-value <?php echo $total_differential > 0 ? 'text-success' : ($total_differential < 0 ? 'text-danger' : 'text-secondary'); ?>">
                            <?php echo $total_differential > 0 ? '+' : ''; ?><?php echo $total_differential; ?>
                        </div>
                        <div class="stat-label">Total Point Differential</div>
                    </div>
                </div>
            </div>

            <!-- Match History Table -->
            <div class="card match-table">
                <div class="card-header">
                    <h5 class="mb-0">Match History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Match #</th>
                                    <th>Teammate</th>
                                    <th>Opponents</th>
                                    <th>Score</th>
                                    <th>Result</th>
                                    <th>Differential</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matches as $match): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($match['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($match['location']); ?></td>
                                        <td><?php echo $match['match_number']; ?></td>
                                        <td><?php echo htmlspecialchars($match['teammate_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($match['opponent_names']); ?></td>
                                        <td>
                                            <strong><?php echo $match['player_score']; ?></strong> - 
                                            <?php echo $match['opponent_score']; ?>
                                        </td>
                                        <td>
                                            <?php if ($match['won']): ?>
                                                <span class="win-badge">W</span>
                                            <?php else: ?>
                                                <span class="loss-badge">L</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="<?php 
                                                echo $match['differential'] > 0 ? 'differential-positive' : 
                                                    ($match['differential'] < 0 ? 'differential-negative' : 'differential-neutral'); 
                                            ?>">
                                                <?php echo $match['differential'] > 0 ? '+' : ''; ?><?php echo $match['differential']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4 mb-4">
            <button class="btn btn-primary" onclick="window.location.href = document.referrer || 'pickleball_standings.php';">Back</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

