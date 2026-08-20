<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle new pickleball season addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_season'])) {
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $ladder_type = $_POST['ladder_type'];
    $max_players = (int)$_POST['max_players'];
    $challenge_window_days = (int)$_POST['challenge_window_days'];
    $min_games_per_week = (int)$_POST['min_games_per_week'];

    // If setting as active, deactivate all other pickleball seasons first
    if ($is_active) {
        $conn->query("UPDATE pickleball_seasons SET is_active = 0");
    }

    $stmt = $conn->prepare("INSERT INTO pickleball_seasons (name, start_date, end_date, is_active, ladder_type, max_players, challenge_window_days, min_games_per_week) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisiii", $name, $start_date, $end_date, $is_active, $ladder_type, $max_players, $challenge_window_days, $min_games_per_week);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Pickleball season added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle setting active pickleball season
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_active'])) {
    $season_id = $_POST['season_id'];
    
    // Deactivate all pickleball seasons first
    $conn->query("UPDATE pickleball_seasons SET is_active = 0");
    
    // Activate selected pickleball season
    $stmt = $conn->prepare("UPDATE pickleball_seasons SET is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $season_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Active pickleball season updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    
    $stmt->close();
}

// Handle pickleball season editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_season'])) {
    $season_id = $_POST['season_id'];
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $ladder_type = $_POST['ladder_type'];
    $max_players = (int)$_POST['max_players'];
    $challenge_window_days = (int)$_POST['challenge_window_days'];
    $min_games_per_week = (int)$_POST['min_games_per_week'];

    // If setting as active, deactivate all other pickleball seasons first
    if ($is_active) {
        $conn->query("UPDATE pickleball_seasons SET is_active = 0");
    }

    $stmt = $conn->prepare("UPDATE pickleball_seasons SET name = ?, start_date = ?, end_date = ?, is_active = ?, ladder_type = ?, max_players = ?, challenge_window_days = ?, min_games_per_week = ? WHERE id = ?");
    $stmt->bind_param("sssisiiii", $name, $start_date, $end_date, $is_active, $ladder_type, $max_players, $challenge_window_days, $min_games_per_week, $season_id);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Pickleball season updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle pickleball season deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_season'])) {
    $season_id = $_POST['season_id'];
    
    // Check if season has any players or games
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_players WHERE season_id = ?");
    $check_stmt->bind_param("i", $season_id);
    $check_stmt->execute();
    $player_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_games WHERE season_id = ?");
    $check_stmt->bind_param("i", $season_id);
    $check_stmt->execute();
    $game_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($player_count > 0 || $game_count > 0) {
        $message = '<div class="alert alert-danger">Cannot delete season with existing players or games. Please remove all players and games first.</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM pickleball_seasons WHERE id = ?");
        $stmt->bind_param("i", $season_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Pickleball season deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        
        $stmt->close();
    }
}

// Get all pickleball seasons
$seasons = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pickleball Seasons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
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
                            <h1 class="display-4 mb-0">🏓 Manage Pickleball Seasons</h1>
                            <p class="mb-0">Create and manage pickleball seasons</p>
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

            <!-- Add New Season -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Add New Pickleball Season</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Season Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="ladder_type" class="form-label">Ladder Type</label>
                                <select class="form-select" id="ladder_type" name="ladder_type" required>
                                    <option value="singles">Singles</option>
                                    <option value="doubles">Doubles</option>
                                    <option value="mixed">Mixed</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="max_players" class="form-label">Max Players</label>
                                <input type="number" class="form-control" id="max_players" name="max_players" value="50" min="1" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="challenge_window_days" class="form-label">Challenge Window (Days)</label>
                                <input type="number" class="form-control" id="challenge_window_days" name="challenge_window_days" value="7" min="1" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="min_games_per_week" class="form-label">Min Games/Week</label>
                                <input type="number" class="form-control" id="min_games_per_week" name="min_games_per_week" value="1" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active">
                                <label class="form-check-label" for="is_active">
                                    Set as active season
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_season" class="btn btn-success">Add Season</button>
                    </form>
                </div>
            </div>

            <!-- Existing Seasons -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Existing Pickleball Seasons</h5>
                </div>
                <div class="card-body">
                    <?php if ($seasons->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Max Players</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($season = $seasons->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($season['name']); ?></strong>
                                                <?php if ($season['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucfirst($season['ladder_type']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($season['start_date'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($season['end_date'])); ?></td>
                                            <td><?php echo $season['max_players']; ?></td>
                                            <td>
                                                <?php if ($season['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if (!$season['is_active']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                                                            <button type="submit" name="set_active" class="btn btn-sm btn-success">Set Active</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $season['id']; ?>">
                                                        Edit
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $season['id']; ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No pickleball seasons found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <?php
    $seasons->data_seek(0); // Reset result pointer
    while ($season = $seasons->fetch_assoc()):
    ?>
        <div class="modal fade" id="editModal<?php echo $season['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Pickleball Season</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_name_<?php echo $season['id']; ?>" class="form-label">Season Name</label>
                                    <input type="text" class="form-control" id="edit_name_<?php echo $season['id']; ?>" name="name" value="<?php echo htmlspecialchars($season['name']); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_start_date_<?php echo $season['id']; ?>" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="edit_start_date_<?php echo $season['id']; ?>" name="start_date" value="<?php echo $season['start_date']; ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_end_date_<?php echo $season['id']; ?>" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="edit_end_date_<?php echo $season['id']; ?>" name="end_date" value="<?php echo $season['end_date']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="edit_ladder_type_<?php echo $season['id']; ?>" class="form-label">Ladder Type</label>
                                    <select class="form-select" id="edit_ladder_type_<?php echo $season['id']; ?>" name="ladder_type" required>
                                        <option value="singles" <?php echo $season['ladder_type'] == 'singles' ? 'selected' : ''; ?>>Singles</option>
                                        <option value="doubles" <?php echo $season['ladder_type'] == 'doubles' ? 'selected' : ''; ?>>Doubles</option>
                                        <option value="mixed" <?php echo $season['ladder_type'] == 'mixed' ? 'selected' : ''; ?>>Mixed</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_max_players_<?php echo $season['id']; ?>" class="form-label">Max Players</label>
                                    <input type="number" class="form-control" id="edit_max_players_<?php echo $season['id']; ?>" name="max_players" value="<?php echo $season['max_players']; ?>" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_challenge_window_<?php echo $season['id']; ?>" class="form-label">Challenge Window (Days)</label>
                                    <input type="number" class="form-control" id="edit_challenge_window_<?php echo $season['id']; ?>" name="challenge_window_days" value="<?php echo $season['challenge_window_days']; ?>" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="edit_min_games_<?php echo $season['id']; ?>" class="form-label">Min Games/Week</label>
                                    <input type="number" class="form-control" id="edit_min_games_<?php echo $season['id']; ?>" name="min_games_per_week" value="<?php echo $season['min_games_per_week']; ?>" min="0" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $season['id']; ?>" name="is_active" <?php echo $season['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_active_<?php echo $season['id']; ?>">
                                        Set as active season
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_season" class="btn btn-success">Update Season</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal<?php echo $season['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Pickleball Season</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the season "<?php echo htmlspecialchars($season['name']); ?>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All associated data will be permanently deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                            <button type="submit" name="delete_season" class="btn btn-danger">Delete Season</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

