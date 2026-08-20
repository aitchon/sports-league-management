<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected volleyball season from session
$selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : null;

// Handle game operations
$message = "";

// Add new game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_game'])) {
    $home_team_id = intval($_POST['home_team_id']);
    $away_team_id = intval($_POST['away_team_id']);
    $date = $_POST['date'];
    $location = $_POST['location'];
    $is_playoff = isset($_POST['is_playoff']) ? 1 : 0;
    
    if ($home_team_id && $away_team_id && $date && $location && $selected_season_id) {
        $stmt = $conn->prepare("INSERT INTO volleyball_games (home_team_id, away_team_id, date, location, is_playoff, season_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissii", $home_team_id, $away_team_id, $date, $location, $is_playoff, $selected_season_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Game added successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields and select a season.</div>';
    }
}

// Update game status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $game_id = intval($_POST['game_id']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE volleyball_games SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $game_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Game status updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
}

// Delete game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game'])) {
    $game_id = intval($_POST['game_id']);
    
    // First delete scores
    $stmt = $conn->prepare("DELETE FROM volleyball_scores WHERE game_id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $stmt->close();
    
    // Then delete the game
    $stmt = $conn->prepare("DELETE FROM volleyball_games WHERE id = ?");
    $stmt->bind_param("i", $game_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Game deleted successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
}

// Fetch teams for dropdowns
$teams = [];
if ($selected_season_id) {
    $teams_stmt = $conn->prepare("
        SELECT vt.id, vt.name, vd.name as division_name 
        FROM volleyball_teams vt 
        JOIN volleyball_divisions vd ON vt.division_id = vd.id 
        WHERE vt.season_id = ? AND vt.is_playoff_placeholder = 0
        ORDER BY vd.name, vt.name
    ");
    $teams_stmt->bind_param("i", $selected_season_id);
    $teams_stmt->execute();
    $teams = $teams_stmt->get_result();
    $teams_stmt->close();
}

// Fetch games for display
$games = [];
if ($selected_season_id) {
    $games_stmt = $conn->prepare("
        SELECT vg.id, vg.date, vg.location, vg.status, vg.is_playoff,
               ht.name as home_team, at.name as away_team,
               hd.name as home_division, ad.name as away_division
        FROM volleyball_games vg
        JOIN volleyball_teams ht ON vg.home_team_id = ht.id
        JOIN volleyball_teams at ON vg.away_team_id = at.id
        JOIN volleyball_divisions hd ON ht.division_id = hd.id
        JOIN volleyball_divisions ad ON at.division_id = ad.id
        WHERE vg.season_id = ?
        ORDER BY vg.date DESC, vg.location
    ");
    $games_stmt->bind_param("i", $selected_season_id);
    $games_stmt->execute();
    $games = $games_stmt->get_result();
    $games_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Volleyball Games</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
        }
        .status-scheduled { color: #6c757d; }
        .status-in-progress { color: #fd7e14; }
        .status-completed { color: #198754; }
        .status-cancelled { color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="mb-3">
        <a href="volleyball_dashboard.php" class="btn btn-secondary">← Back to Volleyball Dashboard</a>
    </div>
    <div class="container py-5">
        <div class="sport-header p-4 rounded mb-4">
            <h1 class="display-4 text-center mb-0">🏐 Manage Volleyball Games</h1>
        </div>

        <?php echo $message; ?>

        <!-- Season Selector -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏐 Volleyball Season Selection</h5>
            </div>
            <div class="card-body">
                <form method="post" class="d-flex align-items-center">
                    <div class="me-3">
                        <label for="season_id" class="form-label mb-0">Current Season:</label>
                    </div>
                    <div class="me-3">
                        <select name="season_id" id="season_id" class="form-select" onchange="this.form.submit()">
                            <?php
                            $seasons = $conn->query("SELECT * FROM volleyball_seasons ORDER BY start_date DESC");
                            while ($season = $seasons->fetch_assoc()): ?>
                                <option value="<?php echo $season['id']; ?>" 
                                        <?php echo ($selected_season_id == $season['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($season['name']); ?>
                                    <?php if ($season['is_active']): ?>
                                        (Active)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <input type="hidden" name="select_volleyball_season" value="1">
                    <button type="submit" class="btn btn-primary btn-sm">Select Season</button>
                </form>
            </div>
        </div>

        <?php if (!$selected_season_id): ?>
            <div class="alert alert-warning">
                <h4>No Season Selected</h4>
                <p>Please select a volleyball season from the dropdown above to manage games.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p><strong>Selected Season:</strong> 
                <?php 
                $stmt = $conn->prepare("SELECT name FROM volleyball_seasons WHERE id = ?");
                $stmt->bind_param("i", $selected_season_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $season_name = $result->fetch_assoc()['name'];
                echo htmlspecialchars($season_name);
                $stmt->close();
                ?> (ID: <?php echo $selected_season_id; ?>)</p>
            </div>

            <!-- Add Game Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Schedule New Game</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="home_team_id" class="form-label">Home Team *</label>
                                <select class="form-select" id="home_team_id" name="home_team_id" required>
                                    <option value="">-- Select Home Team --</option>
                                    <?php 
                                    // Reset teams result
                                    if ($selected_season_id) {
                                        $teams_stmt = $conn->prepare("
                                            SELECT vt.id, vt.name, vd.name as division_name 
                                            FROM volleyball_teams vt 
                                            JOIN volleyball_divisions vd ON vt.division_id = vd.id 
                                            WHERE vt.season_id = ? AND vt.is_playoff_placeholder = 0
                                            ORDER BY vd.name, vt.name
                                        ");
                                        $teams_stmt->bind_param("i", $selected_season_id);
                                        $teams_stmt->execute();
                                        $teams = $teams_stmt->get_result();
                                        $teams_stmt->close();
                                    }
                                    while ($team = $teams->fetch_assoc()): ?>
                                        <option value="<?php echo $team['id']; ?>">
                                            <?php echo htmlspecialchars($team['name']); ?> (<?php echo htmlspecialchars($team['division_name']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="away_team_id" class="form-label">Away Team *</label>
                                <select class="form-select" id="away_team_id" name="away_team_id" required>
                                    <option value="">-- Select Away Team --</option>
                                    <?php 
                                    // Reset teams result again
                                    if ($selected_season_id) {
                                        $teams_stmt = $conn->prepare("
                                            SELECT vt.id, vt.name, vd.name as division_name 
                                            FROM volleyball_teams vt 
                                            JOIN volleyball_divisions vd ON vt.division_id = vd.id 
                                            WHERE vt.season_id = ? AND vt.is_playoff_placeholder = 0
                                            ORDER BY vd.name, vt.name
                                        ");
                                        $teams_stmt->bind_param("i", $selected_season_id);
                                        $teams_stmt->execute();
                                        $teams = $teams_stmt->get_result();
                                        $teams_stmt->close();
                                    }
                                    while ($team = $teams->fetch_assoc()): ?>
                                        <option value="<?php echo $team['id']; ?>">
                                            <?php echo htmlspecialchars($team['name']); ?> (<?php echo htmlspecialchars($team['division_name']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date" class="form-label">Date & Time *</label>
                                <input type="datetime-local" class="form-control" id="date" name="date" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_playoff" name="is_playoff">
                                    <label class="form-check-label" for="is_playoff">
                                        This is a playoff game
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_game" class="btn btn-primary">Schedule Game</button>
                    </form>
                </div>
            </div>

            <!-- Games List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Scheduled Games</h5>
                </div>
                <div class="card-body">
                    <?php if ($games->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Home Team</th>
                                        <th>Away Team</th>
                                        <th>Status</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($game = $games->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y g:i A', strtotime($game['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($game['location']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($game['home_team']); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($game['home_division']); ?>)</small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($game['away_team']); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($game['away_division']); ?>)</small>
                                            </td>
                                            <td>
                                                <span class="status-<?php echo $game['status']; ?>">
                                                    <?php echo ucfirst($game['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $game['is_playoff'] ? 'Playoff' : 'Regular'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                                    <option value="scheduled" <?php echo $game['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                                    <option value="in_progress" <?php echo $game['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                    <option value="completed" <?php echo $game['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                    <option value="cancelled" <?php echo $game['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                </select>
                                                                <input type="hidden" name="update_status" value="1">
                                                            </form>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a href="enter_volleyball_score.php?game_id=<?php echo $game['id']; ?>" 
                                                               class="dropdown-item">Enter Score</a>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item text-danger" 
                                                                    onclick="deleteGame(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars($game['home_team']); ?> vs <?php echo htmlspecialchars($game['away_team']); ?>')">
                                                                Delete Game
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No games found for the selected season.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Game Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the game "<span id="gameName"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This will also delete all scores associated with this game!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" id="deleteGameId" name="game_id">
                        <button type="submit" name="delete_game" class="btn btn-danger">Delete Game</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteGame(gameId, gameName) {
            document.getElementById('deleteGameId').value = gameId;
            document.getElementById('gameName').textContent = gameName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Handle season selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_volleyball_season'])) {
    $_SESSION['selected_volleyball_season_id'] = $_POST['season_id'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
