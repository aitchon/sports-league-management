<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle new player addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_player'])) {
    $name = $_POST['name'];
    $email = $_POST['email'] ?: null;
    $phone = $_POST['phone'] ?: null;
    $dupr_rating = $_POST['dupr_rating'] ?: null;
    $current_rating = (float)$_POST['current_rating'];
    $division_id = $_POST['division_id'] ?: null;
    $season_id = (int)$_POST['season_id'];
    $bio = $_POST['bio'] ?: null;

    $stmt = $conn->prepare("INSERT INTO pickleball_players (name, email, phone, dupr_rating, current_rating, division_id, season_id, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssddis", $name, $email, $phone, $dupr_rating, $current_rating, $division_id, $season_id, $bio);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Pickleball player added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle player editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_player'])) {
    $player_id = $_POST['player_id'];
    $name = $_POST['name'];
    $email = $_POST['email'] ?: null;
    $phone = $_POST['phone'] ?: null;
    $dupr_rating = $_POST['dupr_rating'] ?: null;
    $current_rating = (float)$_POST['current_rating'];
    $ladder_position = $_POST['ladder_position'] ?: null;
    $division_id = $_POST['division_id'] ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $bio = $_POST['bio'] ?: null;

    $stmt = $conn->prepare("UPDATE pickleball_players SET name = ?, email = ?, phone = ?, dupr_rating = ?, current_rating = ?, ladder_position = ?, division_id = ?, is_active = ?, bio = ? WHERE id = ?");
    $stmt->bind_param("sssssiiisi", $name, $email, $phone, $dupr_rating, $current_rating, $ladder_position, $division_id, $is_active, $bio, $player_id);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Pickleball player updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle player deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_player'])) {
    $player_id = $_POST['player_id'];
    
    // Check if player has games
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_games WHERE player1_id = ? OR player2_id = ? OR player3_id = ? OR player4_id = ?");
    $check_stmt->bind_param("iiii", $player_id, $player_id, $player_id, $player_id);
    $check_stmt->execute();
    $game_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($game_count > 0) {
        $message = '<div class="alert alert-danger">Cannot delete player with existing games. Please delete games first or deactivate the player.</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM pickleball_players WHERE id = ?");
        $stmt->bind_param("i", $player_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Pickleball player deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        
        $stmt->close();
    }
}

// Get current season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get all seasons for dropdown
$seasons = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");

// Get divisions for current season (all existing divisions)
$divisions = [];
if ($current_season) {
    $stmt = $conn->prepare("SELECT * FROM pickleball_divisions WHERE season_id = ? AND is_active = 1 ORDER BY min_rating ASC");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
    $stmt->close();
}

// Get players for current season
$players = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT p.*, d.name as division_name, d.skill_level,
               COUNT(g.id) as total_games,
               SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score > s.player2_score) OR (g.player2_id = p.id AND s.player2_score > s.player1_score) THEN 1 ELSE 0 END) as wins,
               SUM(CASE WHEN (g.player1_id = p.id AND s.player1_score < s.player2_score) OR (g.player2_id = p.id AND s.player2_score < s.player1_score) THEN 1 ELSE 0 END) as losses
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON p.division_id = d.id
        LEFT JOIN pickleball_games g ON (g.player1_id = p.id OR g.player2_id = p.id) AND g.season_id = ? AND g.status = 'completed'
        LEFT JOIN pickleball_scores s ON g.id = s.game_id AND s.game_number = 1
        WHERE p.season_id = ?
        GROUP BY p.id
        ORDER BY p.ladder_position ASC, p.current_rating DESC, p.name
    ");
    $stmt->bind_param("ii", $current_season['id'], $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pickleball Players</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .rating-badge {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
            font-weight: bold;
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
                            <h1 class="display-4 mb-0">🏓 Manage Pickleball Players</h1>
                            <p class="mb-0">Add and manage pickleball players</p>
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

            <!-- Add New Player -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Add New Pickleball Player</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Player Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+1234567890">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="season_id" class="form-label">Season</label>
                                <select class="form-select" id="season_id" name="season_id" required>
                                    <?php 
                                    $seasons->data_seek(0); // Reset result pointer
                                    while ($season = $seasons->fetch_assoc()): ?>
                                        <option value="<?php echo $season['id']; ?>" <?php echo ($current_season && $current_season['id'] == $season['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($season['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="dupr_rating" class="form-label">DUPR Rating</label>
                                <input type="number" class="form-control" id="dupr_rating" name="dupr_rating" step="0.01" min="0" max="5">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="current_rating" class="form-label">Current Rating</label>
                                <input type="number" class="form-control" id="current_rating" name="current_rating" step="0.01" min="0" max="5" value="2.50" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="division_id" class="form-label">Division</label>
                                <select class="form-select" id="division_id" name="division_id">
                                    <option value="">Select Division</option>
                                    <?php foreach ($divisions as $division): ?>
                                        <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['name']); ?> (<?php echo $division['skill_level']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Player bio, experience, etc."></textarea>
                        </div>
                        
                        <button type="submit" name="add_player" class="btn btn-success">Add Player</button>
                    </form>
                </div>
            </div>

            <!-- Existing Players -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Existing Pickleball Players (<?php echo count($players); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($players)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Pos</th>
                                        <th>Name</th>
                                        <th>Division</th>
                                        <th>Rating</th>
                                        <th>DUPR</th>
                                        <th>Record</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($players as $player): ?>
                                        <tr>
                                            <td>
                                                <?php if ($player['ladder_position']): ?>
                                                    <span class="badge bg-primary">#<?php echo $player['ladder_position']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($player['name']); ?></strong>
                                                <?php if ($player['bio']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($player['bio'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($player['division_name']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($player['division_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge rating-badge"><?php echo $player['current_rating']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($player['dupr_rating']): ?>
                                                    <span class="badge bg-info"><?php echo $player['dupr_rating']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $player['wins']; ?>-<?php echo $player['losses']; ?>
                                                <br><small class="text-muted"><?php echo $player['total_games']; ?> games</small>
                                            </td>
                                            <td>
                                                <?php if ($player['email']): ?>
                                                    <small><?php echo htmlspecialchars($player['email']); ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($player['phone']): ?>
                                                    <small><?php echo htmlspecialchars($player['phone']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($player['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $player['id']; ?>">
                                                        Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $player['id']; ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No pickleball players found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($players as $player): ?>
        <div class="modal fade" id="editModal<?php echo $player['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Pickleball Player</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_name_<?php echo $player['id']; ?>" class="form-label">Player Name</label>
                                    <input type="text" class="form-control" id="edit_name_<?php echo $player['id']; ?>" name="name" value="<?php echo htmlspecialchars($player['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_email_<?php echo $player['id']; ?>" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email_<?php echo $player['id']; ?>" name="email" value="<?php echo htmlspecialchars($player['email'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_phone_<?php echo $player['id']; ?>" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="edit_phone_<?php echo $player['id']; ?>" name="phone" value="<?php echo htmlspecialchars($player['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_ladder_position_<?php echo $player['id']; ?>" class="form-label">Ladder Position</label>
                                    <input type="number" class="form-control" id="edit_ladder_position_<?php echo $player['id']; ?>" name="ladder_position" value="<?php echo $player['ladder_position'] ?? ''; ?>" min="1">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="edit_dupr_rating_<?php echo $player['id']; ?>" class="form-label">DUPR Rating</label>
                                    <input type="number" class="form-control" id="edit_dupr_rating_<?php echo $player['id']; ?>" name="dupr_rating" step="0.01" min="0" max="5" value="<?php echo $player['dupr_rating'] ?? ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit_current_rating_<?php echo $player['id']; ?>" class="form-label">Current Rating</label>
                                    <input type="number" class="form-control" id="edit_current_rating_<?php echo $player['id']; ?>" name="current_rating" step="0.01" min="0" max="5" value="<?php echo $player['current_rating']; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit_division_id_<?php echo $player['id']; ?>" class="form-label">Division</label>
                                    <select class="form-select" id="edit_division_id_<?php echo $player['id']; ?>" name="division_id">
                                        <option value="">Select Division</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['id']; ?>" <?php echo $player['division_id'] == $division['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($division['name']); ?> (<?php echo $division['skill_level']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_bio_<?php echo $player['id']; ?>" class="form-label">Bio</label>
                                <textarea class="form-control" id="edit_bio_<?php echo $player['id']; ?>" name="bio" rows="3"><?php echo htmlspecialchars($player['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $player['id']; ?>" name="is_active" <?php echo $player['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_active_<?php echo $player['id']; ?>">
                                        Active Player
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_player" class="btn btn-success">Update Player</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal<?php echo $player['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Pickleball Player</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the player "<?php echo htmlspecialchars($player['name']); ?>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                        <?php if ($player['total_games'] > 0): ?>
                            <p class="text-warning"><strong>Note:</strong> This player has <?php echo $player['total_games']; ?> games. Consider deactivating instead of deleting.</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                            <button type="submit" name="delete_player" class="btn btn-danger">Delete Player</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
