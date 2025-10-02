<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle ladder position updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ladder'])) {
    $player_id = (int)$_POST['player_id'];
    $new_position = (int)$_POST['new_position'];
    $reason = $_POST['reason'] ?: 'Admin adjustment';
    
    // Get current position
    $stmt = $conn->prepare("SELECT ladder_position FROM pickleball_players WHERE id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_position = $result->fetch_assoc()['ladder_position'];
    $stmt->close();
    
    // Update player position
    $stmt = $conn->prepare("UPDATE pickleball_players SET ladder_position = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_position, $player_id);
    
    if ($stmt->execute()) {
        // Log the change
        $stmt2 = $conn->prepare("INSERT INTO pickleball_ladder_history (player_id, old_position, new_position, reason, season_id) VALUES (?, ?, ?, ?, ?)");
        $current_season_id = null;
        $season_result = $conn->query("SELECT id FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
        if ($season_result && $season_result->num_rows > 0) {
            $current_season_id = $season_result->fetch_assoc()['id'];
        }
        $stmt2->bind_param("iiisi", $player_id, $current_position, $new_position, $reason, $current_season_id);
        $stmt2->execute();
        $stmt2->close();
        
        $message = '<div class="alert alert-success">Ladder position updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    
    $stmt->close();
}

// Handle auto-ranking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['auto_rank'])) {
    $season_id = (int)$_POST['season_id'];
    
    // Get all players with their stats
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.current_rating, p.ladder_position,
               COUNT(g.id) as total_games,
               SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score > s.player2_score) OR (g.player2_id = p.id AND s.player2_score > s.player1_score) THEN 1 ELSE 0 END) as wins,
               SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score < s.player2_score) OR (g.player2_id = p.id AND s.player2_score < s.player1_score) THEN 1 ELSE 0 END) as losses
        FROM pickleball_players p
        LEFT JOIN pickleball_games g ON (g.player1_id = p.id OR g.player2_id = p.id) AND g.season_id = ? AND g.status = 'completed'
        LEFT JOIN pickleball_scores s ON g.id = s.game_id AND s.game_number = 1
        WHERE p.season_id = ? AND p.is_active = 1
        GROUP BY p.id
        ORDER BY p.current_rating DESC, wins DESC, total_games DESC
    ");
    $stmt->bind_param("ii", $season_id, $season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    $stmt->close();
    
    // Update ladder positions
    $position = 1;
    foreach ($players as $player) {
        $old_position = $player['ladder_position'];
        if ($old_position != $position) {
            // Update position
            $update_stmt = $conn->prepare("UPDATE pickleball_players SET ladder_position = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $position, $player['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Log the change
            $log_stmt = $conn->prepare("INSERT INTO pickleball_ladder_history (player_id, old_position, new_position, reason, season_id) VALUES (?, ?, ?, 'Auto-ranking', ?)");
            $log_stmt->bind_param("iiii", $player['id'], $old_position, $position, $season_id);
            $log_stmt->execute();
            $log_stmt->close();
        }
        $position++;
    }
    
    $message = '<div class="alert alert-success">Ladder auto-ranked successfully!</div>';
}

// Get current season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get all seasons for dropdown
$seasons = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");

// Get ladder standings
$ladder_standings = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.current_rating, p.ladder_position, p.dupr_rating,
               d.name as division_name, d.skill_level,
               COUNT(g.id) as total_games,
               SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score > s.player2_score) OR (g.player2_id = p.id AND s.player2_score > s.player1_score) THEN 1 ELSE 0 END) as wins,
               SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score < s.player2_score) OR (g.player2_id = p.id AND s.player2_score < s.player1_score) THEN 1 ELSE 0 END) as losses
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON p.division_id = d.id
        LEFT JOIN pickleball_games g ON (g.player1_id = p.id OR g.player2_id = p.id) AND g.season_id = ? AND g.status = 'completed'
        LEFT JOIN pickleball_scores s ON g.id = s.game_id AND s.game_number = 1
        WHERE p.season_id = ? AND p.is_active = 1
        GROUP BY p.id
        ORDER BY p.ladder_position ASC, p.current_rating DESC
    ");
    $stmt->bind_param("ii", $current_season['id'], $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ladder_standings[] = $row;
    }
    $stmt->close();
}

// Get ladder history
$ladder_history = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT lh.*, p.name as player_name
        FROM pickleball_ladder_history lh
        JOIN pickleball_players p ON lh.player_id = p.id
        WHERE lh.season_id = ?
        ORDER BY lh.change_date DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ladder_history[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pickleball Ladder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .ladder-position {
            font-weight: bold;
            font-size: 1.2em;
        }
        .rating-badge {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
            font-weight: bold;
        }
        .ladder-card {
            transition: transform 0.2s;
        }
        .ladder-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">🏓 Manage Pickleball Ladder</h1>
                            <p class="mb-0">View and manage ladder rankings</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">← Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo $message; ?>

            <!-- Season Selector -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🏓 Ladder Management</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label for="season_id" class="form-label">Select Season:</label>
                            <select name="season_id" id="season_id" class="form-select" onchange="this.form.submit()">
                                <?php 
                                $seasons->data_seek(0); // Reset result pointer
                                while ($season = $seasons->fetch_assoc()): ?>
                                    <option value="<?php echo $season['id']; ?>" <?php echo ($current_season && $current_season['id'] == $season['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($season['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" name="auto_rank" class="btn btn-warning">Auto-Rank Ladder</button>
                                <a href="../pickleball_standings.php" class="btn btn-info" target="_blank">View Public Ladder</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($current_season): ?>
                <!-- Ladder Standings -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🏆 Current Ladder Standings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($ladder_standings)): ?>
                            <div class="row">
                                <?php foreach ($ladder_standings as $index => $player): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card ladder-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?php if ($player['ladder_position']): ?>
                                                                <span class="ladder-position">#<?php echo $player['ladder_position']; ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Unranked</span>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($player['name']); ?>
                                                        </h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($player['division_name'] ?? 'Unassigned'); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge rating-badge"><?php echo $player['current_rating']; ?></span>
                                                        <?php if ($player['dupr_rating']): ?>
                                                            <br><small class="text-muted">DUPR: <?php echo $player['dupr_rating']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <small class="text-muted">Games</small><br>
                                                        <strong><?php echo $player['total_games']; ?></strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">Wins</small><br>
                                                        <strong class="text-success"><?php echo $player['wins']; ?></strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">Losses</small><br>
                                                        <strong class="text-danger"><?php echo $player['losses']; ?></strong>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#positionModal<?php echo $player['id']; ?>">
                                                        Adjust Position
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No players found in the ladder.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ladder History -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">📈 Recent Ladder Changes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($ladder_history)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Player</th>
                                            <th>Change</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ladder_history as $change): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y g:i A', strtotime($change['change_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($change['player_name']); ?></td>
                                                <td>
                                                    <?php if ($change['old_position'] && $change['new_position']): ?>
                                                        <span class="badge bg-secondary">#<?php echo $change['old_position']; ?></span>
                                                        <span class="text-muted">→</span>
                                                        <span class="badge bg-primary">#<?php echo $change['new_position']; ?></span>
                                                    <?php elseif ($change['new_position']): ?>
                                                        <span class="text-muted">Unranked</span>
                                                        <span class="text-muted">→</span>
                                                        <span class="badge bg-primary">#<?php echo $change['new_position']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">#<?php echo $change['old_position']; ?></span>
                                                        <span class="text-muted">→</span>
                                                        <span class="text-muted">Unranked</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($change['reason']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No ladder changes recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Active Pickleball Season</h4>
                    <p>Please select a season to view the ladder.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Position Adjustment Modals -->
    <?php foreach ($ladder_standings as $player): ?>
        <div class="modal fade" id="positionModal<?php echo $player['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Adjust Ladder Position</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Player:</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($player['name']); ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_position_<?php echo $player['id']; ?>" class="form-label">New Position</label>
                                <input type="number" class="form-control" id="new_position_<?php echo $player['id']; ?>" name="new_position" value="<?php echo $player['ladder_position'] ?? ''; ?>" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reason_<?php echo $player['id']; ?>" class="form-label">Reason for Change</label>
                                <input type="text" class="form-control" id="reason_<?php echo $player['id']; ?>" name="reason" placeholder="e.g., Admin adjustment, performance review">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_ladder" class="btn btn-success">Update Position</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

