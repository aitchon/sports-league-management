<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get current season
$current_season = null;
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
    }
    $stmt->close();

// Get divisions for current season
$divisions = [];
if ($current_season) {
    $stmt = $conn->prepare("SELECT id, name FROM pickleball_divisions WHERE season_id = ? ORDER BY name");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $divisions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get unique dates where games exist (for date filter dropdown)
$available_dates = [];
if ($current_season) {
    $dates_stmt = $conn->prepare("
        SELECT DISTINCT DATE(pg.date) as game_date
        FROM pickleball_games pg
        WHERE pg.season_id = ?
        ORDER BY game_date DESC
    ");
    $dates_stmt->bind_param("i", $current_season['id']);
    $dates_stmt->execute();
    $dates_result = $dates_stmt->get_result();
    while ($date_row = $dates_result->fetch_assoc()) {
        $available_dates[] = $date_row['game_date'];
    }
    $dates_stmt->close();
}

// Get games for current season
$games = [];
if ($current_season) {
    // Date filter - only show specific dates that have games
    $date_filter = "";
    if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all' && !empty($_GET['date_filter'])) {
        $selected_date = $_GET['date_filter'];
        // Validate that the selected date is in available dates
        if (in_array($selected_date, $available_dates)) {
            $date_filter = "AND DATE(pg.date) = ?";
        }
    }
    
    $stmt = $conn->prepare("
        SELECT pg.*, p1.name as player1_name, p2.name as player2_name,
               p3.name as player3_name, p4.name as player4_name, p5.name as player5_name,
               p6.name as player6_name, p7.name as player7_name,
               d.name as division_name
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
        LEFT JOIN pickleball_players p6 ON pg.player6_id = p6.id
        LEFT JOIN pickleball_players p7 ON pg.player7_id = p7.id
        LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
        WHERE pg.season_id = ?
        $date_filter
        ORDER BY pg.date DESC
    ");
    
    // Bind parameters based on date filter
    if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all' && !empty($_GET['date_filter']) && in_array($_GET['date_filter'], $available_dates)) {
        $stmt->bind_param("is", $current_season['id'], $_GET['date_filter']);
    } else {
        $stmt->bind_param("i", $current_season['id']);
    }
    $stmt->execute();
    $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$message = "";

// Handle adding new game
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_game'])) {
    $game_type = $_POST['game_type'];
    $status = $_POST['status'];
    $date = $_POST['date'] . ' ' . $_POST['time'];
    $location = $_POST['location'];
    $division_id = $_POST['division_id'] ? (int)$_POST['division_id'] : null;
    $notes = $_POST['notes'] ?: null;
    
    // Get player IDs
    $player1_id = $_POST['player1_id'] ? (int)$_POST['player1_id'] : null;
    $player2_id = $_POST['player2_id'] ? (int)$_POST['player2_id'] : null;
    $player3_id = $_POST['player3_id'] ? (int)$_POST['player3_id'] : null;
    $player4_id = $_POST['player4_id'] ? (int)$_POST['player4_id'] : null;
    $player5_id = $_POST['player5_id'] ? (int)$_POST['player5_id'] : null;
    $player6_id = $_POST['player6_id'] ? (int)$_POST['player6_id'] : null;
    $player7_id = $_POST['player7_id'] ? (int)$_POST['player7_id'] : null;
    
    $stmt = $conn->prepare("
        INSERT INTO pickleball_games (game_type, status, date, location, division_id, notes, 
                                    player1_id, player2_id, player3_id, player4_id, player5_id, player6_id, player7_id, season_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssisiiiiiiii", $game_type, $status, $date, $location, $division_id, $notes, 
                      $player1_id, $player2_id, $player3_id, $player4_id, $player5_id, $player6_id, $player7_id, $current_season['id']);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Game added successfully!</div>';
        // Refresh the page to show updated data, preserve date filter if set
        $redirect_url = $_SERVER['PHP_SELF'];
        if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all') {
            $redirect_url .= '?date_filter=' . urlencode($_GET['date_filter']);
        }
        header("Location: " . $redirect_url);
        exit();
    } else {
        $message = '<div class="alert alert-danger">Error adding game: ' . $conn->error . '</div>';
    }
    $stmt->close();
}

// Handle game editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_game'])) {
    $game_id = (int)$_POST['game_id'];
    $game_type = $_POST['game_type'];
    $status = $_POST['status'];
    $date = $_POST['date'] . ' ' . $_POST['time'];
    $location = $_POST['location'];
    $division_id = $_POST['division_id'] ? (int)$_POST['division_id'] : null;
    $notes = $_POST['notes'] ?: null;
    
    // Get player IDs
    $player1_id = $_POST['player1_id'] ? (int)$_POST['player1_id'] : null;
    $player2_id = $_POST['player2_id'] ? (int)$_POST['player2_id'] : null;
    $player3_id = $_POST['player3_id'] ? (int)$_POST['player3_id'] : null;
    $player4_id = $_POST['player4_id'] ? (int)$_POST['player4_id'] : null;
    $player5_id = $_POST['player5_id'] ? (int)$_POST['player5_id'] : null;
    $player6_id = $_POST['player6_id'] ? (int)$_POST['player6_id'] : null;
    $player7_id = $_POST['player7_id'] ? (int)$_POST['player7_id'] : null;
    
    $stmt = $conn->prepare("
        UPDATE pickleball_games 
        SET game_type = ?, status = ?, date = ?, location = ?, division_id = ?, notes = ?,
            player1_id = ?, player2_id = ?, player3_id = ?, player4_id = ?, player5_id = ?, player6_id = ?, player7_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssisiiiiiiii", $game_type, $status, $date, $location, $division_id, $notes, 
                      $player1_id, $player2_id, $player3_id, $player4_id, $player5_id, $player6_id, $player7_id, $game_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Game updated successfully!</div>';
        // Refresh the page to show updated data, preserve date filter if set
        $redirect_url = $_SERVER['PHP_SELF'];
        if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all') {
            $redirect_url .= '?date_filter=' . urlencode($_GET['date_filter']);
        }
        header("Location: " . $redirect_url);
        exit();
    } else {
        $message = '<div class="alert alert-danger">Error updating game: ' . $conn->error . '</div>';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pickleball Games</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Manage Pickleball Games</h1>
                        <div>
                            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addGameModal">
                                + Add New Game
                            </button>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">← Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo $message; ?>

            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">📊 Filter Games</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Date Filter (Days with Games):</label>
                                <select name="date_filter" id="dateFilterSelect" class="form-select" onchange="submitForm()">
                                    <option value="all" <?php echo (!isset($_GET['date_filter']) || $_GET['date_filter'] == 'all') ? 'selected' : ''; ?>>
                                        📅 All Dates (<?php echo count($available_dates); ?> days with games)
                                    </option>
                                    <?php foreach ($available_dates as $date): ?>
                                        <?php
                                        $date_formatted = date('l, M j, Y', strtotime($date));
                                        $is_today = $date == date('Y-m-d');
                                        $is_selected = (isset($_GET['date_filter']) && $_GET['date_filter'] == $date);
                                        ?>
                                        <option value="<?php echo htmlspecialchars($date); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo $date_formatted; ?><?php echo $is_today ? ' (Today)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mt-2">
                                    <?php if (isset($_GET['date_filter']) && $_GET['date_filter'] != 'all'): ?>
                                        Showing games from: <strong><?php echo date('l, M j, Y', strtotime($_GET['date_filter'])); ?></strong>
                                    <?php else: ?>
                                        Showing games from all <?php echo count($available_dates); ?> days with games
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Games (<?php echo count($games); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($games)): ?>
                        <div class="row">
                            <?php foreach ($games as $game): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card game-card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php 
                                                $players = [];
                                                if ($game['player1_name']) $players[] = htmlspecialchars($game['player1_name']);
                                                if ($game['player2_name']) $players[] = htmlspecialchars($game['player2_name']);
                                                if ($game['player3_name']) $players[] = htmlspecialchars($game['player3_name']);
                                                if ($game['player4_name']) $players[] = htmlspecialchars($game['player4_name']);
                                                if ($game['player5_name']) $players[] = htmlspecialchars($game['player5_name']);
                                                if ($game['player6_name']) $players[] = htmlspecialchars($game['player6_name']);
                                                if ($game['player7_name']) $players[] = htmlspecialchars($game['player7_name']);
                                                echo implode(', ', $players);
                                                if (empty($players)) echo 'TBD';
                                                ?>
                                            </h6>
                                            <p class="card-text">
                                                <strong>Date:</strong> <?php echo date('M j, Y', strtotime($game['date'])); ?><br>
                                                <strong>Time:</strong> <?php echo date('g:i A', strtotime($game['date'])); ?><br>
                                                <strong>Location:</strong> <?php echo htmlspecialchars($game['location']); ?><br>
                                                <strong>Type:</strong> <?php echo ucfirst($game['game_type']); ?><br>
                                                <strong>Division:</strong> <?php echo htmlspecialchars($game['division_name'] ?? 'Unassigned'); ?><br>
                                                <strong>Status:</strong> 
                                                <span class="badge bg-<?php echo $game['status'] == 'completed' ? 'success' : ($game['status'] == 'scheduled' ? 'primary' : 'warning'); ?>">
                                                    <?php echo ucfirst($game['status']); ?>
                                                </span>
                                            </p>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $game['id']; ?>">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="if(confirm('Are you sure?')) { window.location.href='delete_game.php?id=<?php echo $game['id']; ?>&sport=pickleball'; }">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No pickleball games found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Game Modal -->
    <div class="modal fade" id="addGameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_game_type" class="form-label">Game Type</label>
                                <select class="form-select" id="add_game_type" name="game_type" required>
                                    <option value="">Select Game Type</option>
                                    <option value="singles">Singles</option>
                                    <option value="doubles" selected>Doubles</option>
                                    <option value="mixed">Mixed</option>
                                    <option value="round_robin">Round Robin (4-5 Players)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_status" class="form-label">Status</label>
                                <select class="form-select" id="add_status" name="status" required>
                                    <option value="scheduled" selected>Scheduled</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="add_date" name="date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_time" class="form-label">Time</label>
                                <input type="time" class="form-control" id="add_time" name="time" value="15:30" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="add_location" name="location" value="UWM" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_division" class="form-label">Division</label>
                                <select class="form-select" id="add_division" name="division_id">
                                    <option value="">Select Division</option>
                                    <?php foreach ($divisions as $division): ?>
                                        <option value="<?php echo $division['id']; ?>">
                                            <?php echo htmlspecialchars($division['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Player Selection Fields -->
                        <div class="mb-3">
                            <h6>Players</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="add_player1_id" class="form-label">Player 1 (Optional)</label>
                                    <select class="form-select" id="add_player1_id" name="player1_id">
                                        <option value="">Select Player 1</option>
                                        <?php
                                        if ($current_season) {
                                            $players_stmt = $conn->prepare("SELECT id, name FROM pickleball_players WHERE season_id = ? ORDER BY name");
                                            $players_stmt->bind_param("i", $current_season['id']);
                                            $players_stmt->execute();
                                            $all_players = $players_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            $players_stmt->close();
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="add_player2_id" class="form-label">Player 2 (Optional)</label>
                                    <select class="form-select" id="add_player2_id" name="player2_id">
                                        <option value="">Select Player 2</option>
                                        <?php
                                        if ($current_season) {
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="add_player3_id" class="form-label">Player 3 (Optional)</label>
                                    <select class="form-select" id="add_player3_id" name="player3_id">
                                        <option value="">Select Player 3</option>
                                        <?php
                                        if ($current_season) {
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="add_player4_id" class="form-label">Player 4 (Optional)</label>
                                    <select class="form-select" id="add_player4_id" name="player4_id">
                                        <option value="">Select Player 4</option>
                                        <?php
                                        if ($current_season) {
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="add_player5_id" class="form-label">Player 5 (Optional)</label>
                                    <select class="form-select" id="add_player5_id" name="player5_id">
                                        <option value="">Select Player 5</option>
                                        <?php
                                        if ($current_season) {
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="add_player6_id" class="form-label">Player 6 (Optional)</label>
                                    <select class="form-select" id="add_player6_id" name="player6_id">
                                        <option value="">Select Player 6</option>
                                        <?php
                                        if ($current_season) {
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="add_player7_id" class="form-label">Player 7 (Optional)</label>
                                    <select class="form-select" id="add_player7_id" name="player7_id">
                                        <option value="">Select Player 7</option>
                                        <?php
                                        if ($current_season) {
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="add_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_game" class="btn btn-success">Add Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modals for each game -->
    <?php foreach ($games as $game): ?>
        <div class="modal fade" id="editModal<?php echo $game['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Game #<?php echo $game['id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_game_type_<?php echo $game['id']; ?>" class="form-label">Game Type</label>
                                    <select class="form-select" id="edit_game_type_<?php echo $game['id']; ?>" name="game_type" required>
                                        <option value="singles" <?php echo $game['game_type'] == 'singles' ? 'selected' : ''; ?>>Singles</option>
                                        <option value="doubles" <?php echo $game['game_type'] == 'doubles' ? 'selected' : ''; ?>>Doubles</option>
                                        <option value="mixed" <?php echo $game['game_type'] == 'mixed' ? 'selected' : ''; ?>>Mixed</option>
                                        <option value="round_robin" <?php echo $game['game_type'] == 'round_robin' ? 'selected' : ''; ?>>Round Robin (4-5 Players)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_status_<?php echo $game['id']; ?>" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status_<?php echo $game['id']; ?>" name="status" required>
                                        <option value="scheduled" <?php echo $game['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="in_progress" <?php echo $game['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $game['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $game['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_date_<?php echo $game['id']; ?>" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="edit_date_<?php echo $game['id']; ?>" name="date" value="<?php echo date('Y-m-d', strtotime($game['date'])); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_time_<?php echo $game['id']; ?>" class="form-label">Time</label>
                                    <input type="time" class="form-control" id="edit_time_<?php echo $game['id']; ?>" name="time" value="<?php echo date('H:i', strtotime($game['date'])); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_location_<?php echo $game['id']; ?>" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="edit_location_<?php echo $game['id']; ?>" name="location" value="<?php echo htmlspecialchars($game['location']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_division_<?php echo $game['id']; ?>" class="form-label">Division</label>
                                    <select class="form-select" id="edit_division_<?php echo $game['id']; ?>" name="division_id">
                                        <option value="">Select Division</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['id']; ?>" <?php echo $game['division_id'] == $division['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($division['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Player Selection Fields -->
                            <div class="mb-3">
                                <h6>Players</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player1_id_<?php echo $game['id']; ?>" class="form-label">Player 1 (Optional)</label>
                                        <select class="form-select" id="edit_player1_id_<?php echo $game['id']; ?>" name="player1_id">
                                            <option value="">Select Player 1</option>
                                            <?php
                                            $players_stmt = $conn->prepare("SELECT id, name FROM pickleball_players WHERE season_id = ? ORDER BY name");
                                            $players_stmt->bind_param("i", $current_season['id']);
                                            $players_stmt->execute();
                                            $all_players = $players_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            $players_stmt->close();
                                            foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player1_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player2_id_<?php echo $game['id']; ?>" class="form-label">Player 2 (Optional)</label>
                                        <select class="form-select" id="edit_player2_id_<?php echo $game['id']; ?>" name="player2_id">
                                            <option value="">Select Player 2</option>
                                            <?php foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player2_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player3_id_<?php echo $game['id']; ?>" class="form-label">Player 3 (Optional)</label>
                                        <select class="form-select" id="edit_player3_id_<?php echo $game['id']; ?>" name="player3_id">
                                            <option value="">Select Player 3</option>
                                            <?php foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player3_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player4_id_<?php echo $game['id']; ?>" class="form-label">Player 4 (Optional)</label>
                                        <select class="form-select" id="edit_player4_id_<?php echo $game['id']; ?>" name="player4_id">
                                            <option value="">Select Player 4</option>
                                            <?php foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player4_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player5_id_<?php echo $game['id']; ?>" class="form-label">Player 5 (Optional)</label>
                                        <select class="form-select" id="edit_player5_id_<?php echo $game['id']; ?>" name="player5_id">
                                            <option value="">Select Player 5</option>
                                            <?php foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player5_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player6_id_<?php echo $game['id']; ?>" class="form-label">Player 6 (Optional)</label>
                                        <select class="form-select" id="edit_player6_id_<?php echo $game['id']; ?>" name="player6_id">
                                            <option value="">Select Player 6</option>
                                            <?php foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player6_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_player7_id_<?php echo $game['id']; ?>" class="form-label">Player 7 (Optional)</label>
                                        <select class="form-select" id="edit_player7_id_<?php echo $game['id']; ?>" name="player7_id">
                                            <option value="">Select Player 7</option>
                                            <?php foreach ($all_players as $player): ?>
                                                <option value="<?php echo $player['id']; ?>" <?php echo $game['player7_id'] == $player['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_notes_<?php echo $game['id']; ?>" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit_notes_<?php echo $game['id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars($game['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_game" class="btn btn-success">Update Game</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitForm() {
            document.getElementById('filterForm').submit();
        }
        
        console.log('Bootstrap loaded:', typeof bootstrap !== 'undefined');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            console.log('Games in database: <?php echo count($games); ?>');
            
            // Set default date to today for new games
            const addDateInput = document.getElementById('add_date');
            if (addDateInput) {
                const today = new Date().toISOString().split('T')[0];
                addDateInput.value = today;
            }
            
            // Set default time to 3:30pm (15:30)
            const addTimeInput = document.getElementById('add_time');
            if (addTimeInput && !addTimeInput.value) {
                addTimeInput.value = '15:30';
            }
            
            // Add form submission handling for edit modals
            const editForms = document.querySelectorAll('form[method="POST"]');
            editForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitted');
                    // Form will submit normally to the server
                });
            });
            
            // Add validation for game type requirements
            const addGameForm = document.querySelector('form[action*="add_game"], form:has([name="add_game"])');
            if (addGameForm) {
                addGameForm.addEventListener('submit', function(e) {
                    const gameType = document.getElementById('add_game_type').value;
                    
                    // Basic validation
                    if (!gameType) {
                        alert('Please select a game type.');
                        e.preventDefault();
                        return;
                    }
                    
                    // For round robin games, if players are selected, require at least 4 players
                    if (gameType === 'round_robin') {
                        const player1 = document.getElementById('add_player1_id').value;
                        const player2 = document.getElementById('add_player2_id').value;
                        const player3 = document.getElementById('add_player3_id').value;
                        const player4 = document.getElementById('add_player4_id').value;
                        const player5 = document.getElementById('add_player5_id').value;
                        const player6 = document.getElementById('add_player6_id').value;
                        const player7 = document.getElementById('add_player7_id').value;
                        
                        // Count selected players
                        const selectedPlayers = [player1, player2, player3, player4, player5, player6, player7].filter(p => p);
                        
                        // Only validate if at least one player is selected
                        if (selectedPlayers.length > 0) {
                            if (selectedPlayers.length < 4 || selectedPlayers.length > 7) {
                                alert('Round Robin games require between 4 and 7 players if any players are assigned.');
                                e.preventDefault();
                                return;
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>