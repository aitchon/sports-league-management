<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected pickleball season from session
$selected_season_id = isset($_SESSION['selected_pickleball_season_id']) ? $_SESSION['selected_pickleball_season_id'] : null;

// Get current pickleball season
$current_pickleball_season = null;
if ($selected_season_id) {
    $stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE id = ?");
    $stmt->bind_param("i", $selected_season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_pickleball_season = $result->fetch_assoc();
    $stmt->close();
} else {
    // Get active pickleball season
    $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $current_pickleball_season = $result->fetch_assoc();
        $_SESSION['selected_pickleball_season_id'] = $current_pickleball_season['id'];
    }
}

// Get pickleball season stats
$total_players = 0;
$total_games = 0;
$completed_games = 0;
$active_challenges = 0;
$ladder_matches = 0;

if ($current_pickleball_season) {
    // Count players
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_players WHERE season_id = ? AND is_active = 1" . pickleballRosterSql($conn, 'pickleball_players'));
    $stmt->bind_param("i", $current_pickleball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_players = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count games
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_games WHERE season_id = ?");
    $stmt->bind_param("i", $current_pickleball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_games = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count completed games
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_games WHERE season_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $current_pickleball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed_games = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count active challenges
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_challenges WHERE season_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $current_pickleball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $active_challenges = $result->fetch_assoc()['count'];
    $stmt->close();

    // Count ladder matches
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pickleball_games WHERE season_id = ? AND is_ladder_match = 1");
    $stmt->bind_param("i", $current_pickleball_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $ladder_matches = $result->fetch_assoc()['count'];
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Ladder League Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .ladder-icon {
            font-size: 1.2em;
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
                            <h1 class="display-4 mb-0">🏓 Pickleball Ladder League Admin</h1>
                            <p class="mb-0">Manage your pickleball ladder league</p>
                        </div>
                        <div>
                            <a href="dashboard.php" class="btn btn-light me-2">🏀 Basketball</a>
                            <a href="volleyball_dashboard.php" class="btn btn-light me-2">🏐 Volleyball</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Season Selector -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">🏓 Pickleball Season Selection</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="d-flex align-items-center">
                        <div class="me-3">
                            <label for="season_id" class="form-label mb-0">Current Season:</label>
                        </div>
                        <div class="me-3">
                            <select name="season_id" id="season_id" class="form-select" onchange="this.form.submit()">
                                <?php
                                $seasons = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
                                while ($season = $seasons->fetch_assoc()): ?>
                                    <option value="<?php echo $season['id']; ?>" 
                                            <?php echo ($current_pickleball_season && $current_pickleball_season['id'] == $season['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($season['name']); ?>
                                        (<?php echo ucfirst($season['ladder_type']); ?>)
                                        <?php if ($season['is_active']): ?>
                                            (Active)
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <input type="hidden" name="select_pickleball_season" value="1">
                        <button type="submit" class="btn btn-success btn-sm">Select Season</button>
                    </form>
                    <?php if ($current_pickleball_season): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <strong>Selected:</strong> <?php echo htmlspecialchars($current_pickleball_season['name']); ?> 
                                (<?php echo date('M j, Y', strtotime($current_pickleball_season['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($current_pickleball_season['end_date'])); ?>)
                                <br>
                                <strong>Type:</strong> <?php echo ucfirst($current_pickleball_season['ladder_type']); ?> Ladder
                                <strong>Max Players:</strong> <?php echo $current_pickleball_season['max_players']; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($current_pickleball_season): ?>
                <!-- Season Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $total_players; ?></h3>
                                <p class="card-text">Active Players</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $total_games; ?></h3>
                                <p class="card-text">Total Matches</p>
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
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $active_challenges; ?></h3>
                                <p class="card-text">Active Challenges</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Links -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏓 Manage Seasons</h5>
                                <p class="card-text">Create and manage pickleball seasons</p>
                                <a href="manage_pickleball_seasons.php" class="btn btn-success">Manage Seasons</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📊 Manage Divisions</h5>
                                <p class="card-text">Create and manage skill level divisions</p>
                                <a href="manage_pickleball_divisions.php" class="btn btn-success">Manage Divisions</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">👥 Manage Players</h5>
                                <p class="card-text">Add and edit pickleball players</p>
                                <a href="manage_pickleball_players.php" class="btn btn-success">Manage Players</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏆 Ladder Management</h5>
                                <p class="card-text">View and manage ladder rankings</p>
                                <a href="manage_pickleball_ladder.php" class="btn btn-success">Manage Ladder</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">⚔️ Manage Challenges</h5>
                                <p class="card-text">View and manage player challenges</p>
                                <a href="manage_pickleball_challenges.php" class="btn btn-success">Manage Challenges</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📅 Manage Matches</h5>
                                <p class="card-text">Schedule and manage pickleball matches</p>
                                <a href="manage_pickleball_games.php" class="btn btn-success">Manage Matches</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏓 Assign Courts</h5>
                                <p class="card-text">Set who is playing and build the court plan. 20 minutes when 24 or fewer are playing.</p>
                                <a href="assign_pickleball_courts.php" class="btn btn-success">Assign Courts</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📊 Enter Scores</h5>
                                <p class="card-text">Enter and manage match scores</p>
                                <a href="enter_pickleball_scores.php" class="btn btn-warning">Enter Scores</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📈 View Frontend</h5>
                                <p class="card-text">View public pickleball pages</p>
                                <a href="../pickleball_standings.php" class="btn btn-info" target="_blank">View Ladder</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📊 Statistics</h5>
                                <p class="card-text">View detailed player statistics</p>
                                <a href="../pickleball_stat_leaders.php" class="btn btn-info" target="_blank">View Stats</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📅 Player Availability</h5>
                                <p class="card-text">View and manage player availability</p>
                                <a href="pickleball_availability_admin.php" class="btn btn-success">Manage Availability</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📅 Public Availability</h5>
                                <p class="card-text">Player availability entry page</p>
                                <a href="../pickleball_availability.php" class="btn btn-info" target="_blank">View Page</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📱 SMS Manager</h5>
                                <p class="card-text">Send text messages to players</p>
                                <a href="pickleball_sms_manager.php" class="btn btn-success">SMS Manager</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">📱 Match SMS</h5>
                                <p class="card-text">Send match-specific notifications</p>
                                <a href="pickleball_match_sms.php" class="btn btn-info">Match SMS</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">📊 Quick Ladder Overview</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get top 5 players in ladder
                                $top_players_stmt = $conn->prepare("
                                    SELECT p.name, p.current_rating, p.ladder_position, d.name as division_name
                                    FROM pickleball_players p
                                    LEFT JOIN pickleball_divisions d ON p.division_id = d.id
                                    WHERE p.season_id = ? AND p.is_active = 1
                                    ORDER BY p.ladder_position ASC
                                    LIMIT 5
                                ");
                                $top_players_stmt->bind_param("i", $current_pickleball_season['id']);
                                $top_players_stmt->execute();
                                $top_players = $top_players_stmt->get_result();
                                $top_players_stmt->close();
                                ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>🏆 Top 5 Ladder Players</h6>
                                        <?php if ($top_players->num_rows > 0): ?>
                                            <div class="list-group list-group-flush">
                                                <?php while ($player = $top_players->fetch_assoc()): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong>#<?php echo $player['ladder_position'] ?? 'N/A'; ?></strong> 
                                                            <?php echo htmlspecialchars($player['name']); ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($player['division_name']); ?> - Rating: <?php echo $player['current_rating']; ?></small>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No players in ladder yet.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>⚔️ Recent Activity</h6>
                                        <?php
                                        // Get recent completed matches
                                        $recent_matches_stmt = $conn->prepare("
                                            SELECT pg.id, pg.date, p1.name as player1, p2.name as player2, 
                                                   ps1.player1_score, ps1.player2_score, ps1.winner_id
                                            FROM pickleball_games pg
                                            JOIN pickleball_players p1 ON pg.player1_id = p1.id
                                            JOIN pickleball_players p2 ON pg.player2_id = p2.id
                                            LEFT JOIN pickleball_scores ps1 ON pg.id = ps1.game_id AND ps1.game_number = 1
                                            WHERE pg.season_id = ? AND pg.status = 'completed'
                                            ORDER BY pg.date DESC
                                            LIMIT 3
                                        ");
                                        $recent_matches_stmt->bind_param("i", $current_pickleball_season['id']);
                                        $recent_matches_stmt->execute();
                                        $recent_matches = $recent_matches_stmt->get_result();
                                        $recent_matches_stmt->close();
                                        ?>
                                        
                                        <?php if ($recent_matches->num_rows > 0): ?>
                                            <div class="list-group list-group-flush">
                                                <?php while ($match = $recent_matches->fetch_assoc()): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($match['player1']); ?></strong> vs 
                                                                <strong><?php echo htmlspecialchars($match['player2']); ?></strong>
                                                            </div>
                                                            <div>
                                                                <?php if ($match['player1_score'] && $match['player2_score']): ?>
                                                                    <?php echo $match['player1_score']; ?>-<?php echo $match['player2_score']; ?>
                                                                <?php else: ?>
                                                                    Completed
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($match['date'])); ?></small>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No recent matches.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Pickleball Season Found</h4>
                    <p>Please create a pickleball season first.</p>
                    <a href="manage_pickleball_seasons.php" class="btn btn-success">Create Season</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Handle season selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_pickleball_season'])) {
    $_SESSION['selected_pickleball_season_id'] = $_POST['season_id'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
