<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected volleyball season from session
$selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : null;

// Get current volleyball season
$current_volleyball_season = null;
if ($selected_season_id) {
    $stmt = $conn->prepare("SELECT * FROM volleyball_seasons WHERE id = ?");
    $stmt->bind_param("i", $selected_season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_volleyball_season = $result->fetch_assoc();
    $stmt->close();
} else {
    // Get active volleyball season
    $result = $conn->query("SELECT * FROM volleyball_seasons WHERE is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $current_volleyball_season = $result->fetch_assoc();
        $_SESSION['selected_volleyball_season_id'] = $current_volleyball_season['id'];
    }
}

// Get volleyball season stats
$total_teams = 0;
$total_players = 0;
$total_games = 0;
$completed_games = 0;

if ($current_volleyball_season) {
    // Count teams
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM volleyball_teams WHERE season_id = ? AND is_playoff_placeholder = 0");
    $stmt->bind_param("i", $current_volleyball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_teams = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count players
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM volleyball_players WHERE season_id = ?");
    $stmt->bind_param("i", $current_volleyball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_players = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count games
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM volleyball_games WHERE season_id = ?");
    $stmt->bind_param("i", $current_volleyball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_games = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count completed games
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM volleyball_games WHERE season_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $current_volleyball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed_games = $result->fetch_assoc()['count'];
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volleyball League Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
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
                            <h1 class="display-4 mb-0">🏐 Volleyball League Admin</h1>
                            <p class="mb-0">Manage your volleyball league</p>
                        </div>
                        <div>
                            <a href="dashboard.php" class="btn btn-light me-2">🏀 Basketball</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
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
                                            <?php echo ($current_volleyball_season && $current_volleyball_season['id'] == $season['id']) ? 'selected' : ''; ?>>
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
                    <?php if ($current_volleyball_season): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <strong>Selected:</strong> <?php echo htmlspecialchars($current_volleyball_season['name']); ?> 
                                (<?php echo date('M j, Y', strtotime($current_volleyball_season['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($current_volleyball_season['end_date'])); ?>)
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($current_volleyball_season): ?>
                <!-- Season Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $total_teams; ?></h3>
                                <p class="card-text">Teams</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $total_players; ?></h3>
                                <p class="card-text">Players</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $total_games; ?></h3>
                                <p class="card-text">Total Games</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $completed_games; ?></h3>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Links -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏐 Manage Seasons</h5>
                                <p class="card-text">Create and manage volleyball seasons</p>
                                <a href="manage_volleyball_seasons.php" class="btn btn-primary">Manage Seasons</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">👥 Manage Teams</h5>
                                <p class="card-text">Add and edit volleyball teams</p>
                                <a href="manage_volleyball_teams.php" class="btn btn-primary">Manage Teams</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏃 Manage Players</h5>
                                <p class="card-text">Add and edit volleyball players</p>
                                <a href="manage_volleyball_players.php" class="btn btn-primary">Manage Players</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏆 Manage Divisions</h5>
                                <p class="card-text">Create and manage volleyball divisions</p>
                                <a href="manage_volleyball_divisions.php" class="btn btn-primary">Manage Divisions</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📅 Manage Games</h5>
                                <p class="card-text">Schedule and manage volleyball games</p>
                                <a href="manage_volleyball_games.php" class="btn btn-primary">Manage Games</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏆 Manage Divisions</h5>
                                <p class="card-text">Create and manage volleyball divisions</p>
                                <a href="manage_volleyball_divisions.php" class="btn btn-primary">Manage Divisions</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📊 Enter Scores</h5>
                                <p class="card-text">Enter and manage game scores</p>
                                <a href="manage_volleyball_games.php" class="btn btn-success">Enter Scores</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📊 View Frontend</h5>
                                <p class="card-text">View public volleyball pages</p>
                                <a href="../volleyball_standings.php" class="btn btn-success" target="_blank">View Standings</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Volleyball Season Found</h4>
                    <p>Please create a volleyball season first.</p>
                    <a href="manage_volleyball_seasons.php" class="btn btn-primary">Create Season</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
