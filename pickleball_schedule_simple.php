<?php
include 'admin/db.php';

// Get current pickleball season
$current_season = null;
$message = '';

try {
    $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $current_season = $result->fetch_assoc();
    }
} catch (Exception $e) {
    $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
}

// Get games - very simple query
$games = [];
if ($current_season) {
    try {
        $query = "SELECT * FROM pickleball_games WHERE season_id = ? ORDER BY date ASC";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $games = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error loading games: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Schedule - Simple</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <h1 class="display-4 mb-0">🏓 Pickleball Schedule - Simple Version</h1>
            <p class="lead mb-0">Basic schedule display for debugging</p>
        </div>
    </div>

    <div class="container">
        <?php echo $message; ?>
        
        <?php if (!$current_season): ?>
            <div class="alert alert-warning">
                <h4>No Active Season</h4>
                <p>There is currently no active pickleball season.</p>
                <a href="admin/manage_pickleball_seasons.php" class="btn btn-primary">Create Season</a>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">🏓 <?php echo htmlspecialchars($current_season['name']); ?></h5>
                </div>
                <div class="card-body">
                    <p><strong>Season:</strong> <?php echo date('M j, Y', strtotime($current_season['start_date'])); ?> - <?php echo date('M j, Y', strtotime($current_season['end_date'])); ?></p>
                    <p><strong>Type:</strong> <?php echo ucfirst($current_season['ladder_type']); ?> Ladder</p>
                </div>
            </div>

            <?php if (empty($games)): ?>
                <div class="alert alert-info">
                    <h4>No Games Found</h4>
                    <p>No games have been created for this season yet.</p>
                    <a href="admin/manage_pickleball_games.php" class="btn btn-primary">Create Games</a>
                </div>
            <?php else: ?>
                <h3>All Games (<?php echo count($games); ?> total)</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Players</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr>
                                    <td><?php echo $game['id']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($game['date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($game['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($game['location']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $game['game_type'] == 'doubles' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($game['game_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $game['status'] == 'completed' ? 'success' : ($game['status'] == 'scheduled' ? 'primary' : 'warning'); ?>">
                                            <?php echo ucfirst($game['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $player_count = 0;
                                        if ($game['player1_id']) $player_count++;
                                        if ($game['player2_id']) $player_count++;
                                        if ($game['player3_id']) $player_count++;
                                        if ($game['player4_id']) $player_count++;
                                        
                                        if ($player_count == 0) {
                                            echo '<span class="text-muted">No players assigned</span>';
                                        } else {
                                            echo $player_count . ' player' . ($player_count != 1 ? 's' : '') . ' assigned';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($game['notes'] ?: ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="mt-4">
            <h4>Debug Links</h4>
            <a href="admin/diagnose_schedule.php" class="btn btn-info me-2">Run Diagnostics</a>
            <a href="pickleball_schedule.php" class="btn btn-primary me-2">View Full Schedule</a>
            <a href="admin/pickleball_dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>







