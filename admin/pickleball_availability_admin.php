<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get current pickleball season
$current_season = null;
$message = '';
try {
    $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $current_season = $result->fetch_assoc();
    }
} catch (Exception $e) {
    $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '. Please run the SQL setup first.</div>';
}

// Get all players for the current season
$players = [];
if ($current_season) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, d.name as division_name
            FROM pickleball_players p
            LEFT JOIN pickleball_divisions d ON p.division_id = d.id
            WHERE p.season_id = ? AND p.is_active = 1
            ORDER BY p.name
        ");
        $stmt->bind_param("i", $current_season['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error loading players: ' . $e->getMessage() . '</div>';
    }
}

// Get selected player
$selected_player_id = isset($_GET['player']) ? (int)$_GET['player'] : null;
$selected_player = null;
if ($selected_player_id) {
    foreach ($players as $player) {
        if ($player['id'] == $selected_player_id) {
            $selected_player = $player;
            break;
        }
    }
}

// Handle availability submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_availability'])) {
    $player_id = (int)$_POST['player_id'];
    $match_date = $_POST['match_date'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        // Check if availability record exists
        $stmt = $conn->prepare("SELECT id FROM pickleball_availability WHERE player_id = ? AND match_date = ?");
        $stmt->bind_param("is", $player_id, $match_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE pickleball_availability SET is_available = ?, notes = ?, updated_at = NOW() WHERE player_id = ? AND match_date = ?");
            $stmt->bind_param("isis", $is_available, $notes, $player_id, $match_date);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO pickleball_availability (player_id, match_date, is_available, notes, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isis", $player_id, $match_date, $is_available, $notes);
        }
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Availability updated successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error updating availability: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $player_id = (int)$_POST['player_id'];
    $bulk_action = $_POST['bulk_action'];
    $is_available = ($bulk_action === 'available_all') ? 1 : 0;
    
    try {
        foreach ($scheduled_matches as $match) {
            $match_date = $match['match_date'];
            
            // Check if availability record exists
            $stmt = $conn->prepare("SELECT id FROM pickleball_availability WHERE player_id = ? AND match_date = ?");
            $stmt->bind_param("is", $player_id, $match_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE pickleball_availability SET is_available = ?, updated_at = NOW() WHERE player_id = ? AND match_date = ?");
                $stmt->bind_param("iis", $is_available, $player_id, $match_date);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO pickleball_availability (player_id, match_date, is_available, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("isi", $player_id, $match_date, $is_available);
            }
            
            $stmt->execute();
            $stmt->close();
        }
        
        $action_text = $is_available ? 'available' : 'unavailable';
        $message = '<div class="alert alert-success">Player marked as ' . $action_text . ' for all scheduled match days!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Get scheduled matches for the current season
$scheduled_matches = [];
if ($current_season) {
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT DATE(date) as match_date, 
                   COUNT(*) as match_count,
                   GROUP_CONCAT(DISTINCT g.location ORDER BY g.location SEPARATOR ', ') as locations,
                   GROUP_CONCAT(DISTINCT CONCAT(g.game_type, ' (', TIME_FORMAT(g.date, '%h:%i %p'), ')') ORDER BY g.date SEPARATOR ', ') as match_details
            FROM pickleball_games g
            WHERE g.season_id = ? AND g.status = 'scheduled'
            GROUP BY DATE(date)
            ORDER BY match_date
        ");
        $stmt->bind_param("i", $current_season['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $scheduled_matches[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        // If no matches table, show empty
        $scheduled_matches = [];
    }
}

// Get current availability for selected player
$current_availability = [];
if ($selected_player) {
    try {
        $stmt = $conn->prepare("
            SELECT match_date, is_available, notes
            FROM pickleball_availability
            WHERE player_id = ?
        ");
        $stmt->bind_param("i", $selected_player['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $current_availability[$row['match_date']] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        // If no availability table, use empty array
        $current_availability = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Availability Admin - <?php echo $current_season ? htmlspecialchars($current_season['name']) : 'No Active Season'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .match-day-card {
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
        .availability-form {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-top: 1rem;
        }
        .no-matches {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .player-info {
            background-color: #e8f5e8;
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 mb-0">📅 Pickleball Availability Admin</h1>
                    <?php if ($current_season): ?>
                        <p class="lead mb-0"><?php echo htmlspecialchars($current_season['name']); ?> Season</p>
                        <small class="opacity-75">
                            <?php echo date('M j, Y', strtotime($current_season['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($current_season['end_date'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <a href="pickleball_dashboard.php" class="btn btn-light me-2">🏠 Dashboard</a>
                    <a href="manage_pickleball_games.php" class="btn btn-outline-light">🎾 Games</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo $message; ?>

        <?php if (!$current_season): ?>
            <div class="no-matches">
                <h3>No Active Season</h3>
                <p>There is currently no active pickleball season.</p>
            </div>
        <?php elseif (empty($scheduled_matches)): ?>
            <div class="no-matches">
                <h3>No Scheduled Matches</h3>
                <p>There are currently no scheduled matches for the current season.</p>
                <p class="text-muted">Schedule some matches first, then return to manage player availability.</p>
                <a href="manage_pickleball_games.php" class="btn btn-primary">Schedule Matches</a>
            </div>
        <?php else: ?>
            <!-- Player Selection -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Select Player to Manage Availability</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <select name="player" class="form-select" onchange="this.form.submit()">
                                <option value="">Choose a player to manage availability...</option>
                                <?php foreach ($players as $player): ?>
                                    <option value="<?php echo $player['id']; ?>" <?php echo ($selected_player && $selected_player['id'] == $player['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($player['name']); ?>
                                        <?php if ($player['division_name']): ?>
                                            - <?php echo htmlspecialchars($player['division_name']); ?>
                                        <?php endif; ?>
                                        <?php if ($player['phone']): ?>
                                            (<?php echo htmlspecialchars($player['phone']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">Load Player Availability</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_player): ?>
                <!-- Player Info -->
                <div class="player-info">
                    <h5 class="mb-1"><?php echo htmlspecialchars($selected_player['name']); ?></h5>
                    <div class="row">
                        <div class="col-md-4">
                            <small><strong>Division:</strong> <?php echo $selected_player['division_name'] ? htmlspecialchars($selected_player['division_name']) : 'Not assigned'; ?></small>
                        </div>
                        <div class="col-md-4">
                            <small><strong>Phone:</strong> <?php echo $selected_player['phone'] ? htmlspecialchars($selected_player['phone']) : 'Not provided'; ?></small>
                        </div>
                        <div class="col-md-4">
                            <small><strong>Email:</strong> <?php echo $selected_player['email'] ? htmlspecialchars($selected_player['email']) : 'Not provided'; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Scheduled Match Days -->
                <div class="row">
                    <?php foreach ($scheduled_matches as $match): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card match-day-card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <?php echo date('l, M j', strtotime($match['match_date'])); ?>
                                    </h6>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <?php echo $match['match_count']; ?> match<?php echo $match['match_count'] != 1 ? 'es' : ''; ?> scheduled
                                        </small><br>
                                        <small class="text-muted">📍 <?php echo htmlspecialchars($match['locations']); ?></small><br>
                                        <small class="text-muted">⏰ <?php echo htmlspecialchars($match['match_details']); ?></small>
                                    </p>
                                    
                                    <!-- Availability Form for this match day -->
                                    <form method="POST" class="availability-form">
                                        <input type="hidden" name="player_id" value="<?php echo $selected_player['id']; ?>">
                                        <input type="hidden" name="match_date" value="<?php echo $match['match_date']; ?>">
                                        
                                        <?php 
                                        $availability = $current_availability[$match['match_date']] ?? null;
                                        $is_available = $availability ? $availability['is_available'] : 1; // Default to available if no record
                                        $notes = $availability ? $availability['notes'] : '';
                                        ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="is_available" id="available_<?php echo str_replace('-', '_', $match['match_date']); ?>" <?php echo $is_available ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="available_<?php echo str_replace('-', '_', $match['match_date']); ?>">
                                                Player is available for this match day
                                            </label>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label for="notes_<?php echo str_replace('-', '_', $match['match_date']); ?>" class="form-label">Admin Notes</label>
                                            <textarea class="form-control form-control-sm" name="notes" id="notes_<?php echo str_replace('-', '_', $match['match_date']); ?>" rows="2" placeholder="Any notes about this player's availability..."><?php echo htmlspecialchars($notes); ?></textarea>
                                        </div>
                                        
                                        <button type="submit" name="update_availability" class="btn btn-success btn-sm">
                                            Update Availability
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Bulk Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Bulk Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="player_id" value="<?php echo $selected_player['id']; ?>">
                                    <input type="hidden" name="bulk_action" value="available_all">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Mark player as available for ALL scheduled match days?')">
                                        Mark Available for All Days
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="player_id" value="<?php echo $selected_player['id']; ?>">
                                    <input type="hidden" name="bulk_action" value="unavailable_all">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Mark player as unavailable for ALL scheduled match days?')">
                                        Mark Unavailable for All Days
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Admin Instructions</h5>
                        <ol>
                            <li><strong>Select a player</strong> from the dropdown above</li>
                            <li><strong>Review their availability</strong> for each scheduled match day</li>
                            <li><strong>Update availability</strong> by checking/unchecking the availability box</li>
                            <li><strong>Add admin notes</strong> if needed for scheduling purposes</li>
                            <li><strong>Use bulk actions</strong> to quickly mark availability for all days</li>
                        </ol>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <h5>Select a Player</h5>
                    <p>Please select a player from the dropdown above to manage their availability for scheduled match days.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>