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
        // Get all players for the season
        $stmt = $conn->prepare("
            SELECT p.id, p.name
            FROM pickleball_players p
            WHERE p.season_id = ? AND p.is_active = 1
            ORDER BY p.name
        ");
        $stmt->bind_param("i", $season_id);
        $stmt->execute();
        $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($players)) {
            return "No players found for season $season_id";
        }
        
        // Calculate stats for each player
        $player_stats = [];
        foreach ($players as $player) {
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
                'wins' => $wins,
                'losses' => $losses,
                'points_scored' => $points_scored,
                'points_against' => $points_against,
                'win_percentage' => $win_percentage,
                'point_differential' => $point_differential
            ];
        }
        
        // Sort by win percentage, then by point differential, then by points scored
        usort($player_stats, function($a, $b) {
            if ($a['win_percentage'] != $b['win_percentage']) {
                return $b['win_percentage'] <=> $a['win_percentage']; // Higher win % first
            }
            if ($a['point_differential'] != $b['point_differential']) {
                return $b['point_differential'] <=> $a['point_differential']; // Higher diff first
            }
            return $b['points_scored'] <=> $a['points_scored']; // Higher points first
        });
        
        // Update ladder positions
        foreach ($player_stats as $index => $stats) {
            $position = $index + 1;
            
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
                $stats['player_id'], $season_id, $position, $stats['wins'], $stats['losses'],
                $stats['points_scored'], $stats['points_against'], $stats['win_percentage'], $stats['point_differential']
            );
            $stmt->execute();
            $stmt->close();
        }
        
        return "Ladder positions updated successfully for " . count($player_stats) . " players";
        
    } catch (Exception $e) {
        return "Error updating ladder positions: " . $e->getMessage();
    }
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ladder'])) {
    $season_id = (int)$_POST['season_id'];
    $message = updateLadderPositions($conn, $season_id);
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

// Get all seasons for dropdown
$seasons = [];
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $seasons[] = $row;
}
$stmt->close();

// Get current ladder positions
$ladder_positions = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT lp.*, p.name
        FROM pickleball_ladder_positions lp
        JOIN pickleball_players p ON lp.player_id = p.id
        WHERE lp.season_id = ?
        ORDER BY lp.position
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ladder_positions[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Ladder Positions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .ladder-position {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        .position-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2E8B57;
        }
        .gold { color: #FFD700; }
        .silver { color: #C0C0C0; }
        .bronze { color: #CD7F32; }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏆 Update Ladder Positions</h1>
                    <p class="lead mb-0">Calculate and update player rankings</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Update Ladder Positions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="season_id" class="form-label">Select Season</label>
                                <select class="form-select" id="season_id" name="season_id" required>
                                    <?php foreach ($seasons as $season): ?>
                                        <option value="<?php echo $season['id']; ?>" 
                                                <?php echo ($current_season && $current_season['id'] == $season['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($season['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="update_ladder" class="btn btn-primary">
                                Update Ladder Positions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Current Ladder</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ladder_positions)): ?>
                            <p class="text-muted">No ladder positions calculated yet.</p>
                        <?php else: ?>
                            <?php foreach ($ladder_positions as $position): ?>
                                <div class="ladder-position">
                                    <div class="row align-items-center">
                                        <div class="col-2">
                                            <div class="position-number 
                                                <?php 
                                                if ($position['position'] == 1) echo 'gold';
                                                elseif ($position['position'] == 2) echo 'silver';
                                                elseif ($position['position'] == 3) echo 'bronze';
                                                ?>">
                                                #<?php echo $position['position']; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <strong><?php echo htmlspecialchars($position['name']); ?></strong>
                                        </div>
                                        <div class="col-4 text-end">
                                            <small class="text-muted">
                                                <?php echo $position['wins']; ?>W-<?php echo $position['losses']; ?>L<br>
                                                <?php echo number_format($position['win_percentage'], 1); ?>%
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <a href="enter_pickleball_scores.php" class="btn btn-success">Enter Scores</a>
                <a href="pickleball_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



