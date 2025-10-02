<?php
include 'admin/db.php';

// Get current pickleball season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get divisions for current season (only those with players)
$divisions = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT DISTINCT d.*, COUNT(p.id) as player_count
        FROM pickleball_divisions d
        INNER JOIN pickleball_players p ON d.id = p.division_id
        WHERE d.season_id = ? AND d.is_active = 1 AND p.is_active = 1
        GROUP BY d.id
        ORDER BY d.min_rating ASC
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
    $stmt->close();
}

// Get schedule data - show all upcoming games
$schedule = null;
$schedule_error = '';
if ($current_season) {
    $query = "
        SELECT pg.id, DATE(pg.date) AS game_date, TIME(pg.date) AS start_time, 
               pg.location, pg.game_type,
               p1.name AS player1_name, p2.name AS player2_name, 
               p3.name AS player3_name, p4.name AS player4_name, p5.name AS player5_name,
               pg.status, 
               d.name AS division_name, d.id AS division_id,
               pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
        LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
        WHERE DATE(pg.date) >= CURDATE() AND pg.season_id = ?
        ORDER BY game_date, pg.location, start_time
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $current_season['id']);
        $stmt->execute();
        $schedule = $stmt->get_result();
        $stmt->close();
    } catch (Exception $e) {
        $schedule_error = 'Error loading schedule: ' . $e->getMessage();
        $schedule = null;
    }
}

// Organize data by date and location
$grouped_schedule = [];
if ($schedule) {
    while ($game = $schedule->fetch_assoc()) {
        $date = $game['game_date'];
        $location = $game['location'];
        $grouped_schedule[$date][$location][] = $game;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .schedule-group {
            margin-bottom: 30px;
        }
        .date-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
        }
        .location-header {
            background: #f8f9fa;
            padding: 8px 15px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
        .game-card {
            border: 1px solid #dee2e6;
            border-top: none;
            transition: all 0.2s;
        }
        .game-card:hover {
            background-color: #f8f9fa;
        }
        .game-card:last-child {
            border-radius: 0 0 8px 8px;
        }
        .ladder-badge {
            background: linear-gradient(45deg, #4ECDC4, #44A08D);
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
                    <div class="text-center">
                        <h1 class="display-4 mb-0">🏓 Pickleball Schedule</h1>
                        <p class="mb-0">Upcoming matches and games</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="container mb-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <a href="pickleball_standings.php" class="btn btn-success">🏆 Standings</a>
                                <a href="pickleball_ladder.php" class="btn btn-primary">📈 Ladder</a>
                                <a href="pickleball_schedule.php" class="btn btn-info">📅 Schedule</a>
                                <a href="pickleball_stat_leaders.php" class="btn btn-warning">⭐ Stat Leaders</a>
                                <a href="pickleball_availability.php" class="btn btn-secondary">📋 Availability</a>
                                <a href="pickleball_match_assignments.php" class="btn btn-dark">🎯 Match Assignments</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if ($schedule_error): ?>
                <div class="alert alert-danger">
                    <h4>Schedule Error</h4>
                    <p><?php echo htmlspecialchars($schedule_error); ?></p>
                    <p><a href="admin/setup_doubles_columns.php" class="btn btn-warning">Setup Database Columns</a></p>
                </div>
            <?php endif; ?>
            
            <?php if ($current_season): ?>
                <!-- Season Info -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🏓 <?php echo htmlspecialchars($current_season['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Season:</strong> <?php echo date('M j, Y', strtotime($current_season['start_date'])); ?> - <?php echo date('M j, Y', strtotime($current_season['end_date'])); ?></p>
                                <p><strong>Type:</strong> <?php echo ucfirst($current_season['ladder_type']); ?> Ladder</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Max Players:</strong> <?php echo $current_season['max_players']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Schedule -->
                <?php if (!empty($grouped_schedule)): ?>
                    <?php foreach ($grouped_schedule as $date => $locations): ?>
                        <div class="schedule-group">
                            <h4 class="date-header">
                                <?php echo date('l, F j, Y', strtotime($date)); ?>
                            </h4>
                            
                            <?php foreach ($locations as $location => $games): ?>
                                <div class="card">
                                    <div class="location-header">
                                        📍 <?php echo htmlspecialchars($location); ?>
                                    </div>
                                    
                                    <?php foreach ($games as $game): ?>
                                        <div class="game-card p-3">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <span class="badge bg-secondary">
                                                        <?php echo date('g:i A', strtotime($game['start_time'])); ?>
                                                    </span>
                                                    <span class="badge ladder-badge ms-1">Ladder</span>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($game['game_type'] == 'round_robin'): ?>
                                                            <div class="text-center flex-grow-1">
                                                                <div class="fw-bold">
                                                                    Round Robin
                                                                </div>
                                                                <div class="small">
                                                                    <?php 
                                                                    $players = [];
                                                                    if ($game['player1_name']) $players[] = htmlspecialchars($game['player1_name']);
                                                                    if ($game['player2_name']) $players[] = htmlspecialchars($game['player2_name']);
                                                                    if ($game['player3_name']) $players[] = htmlspecialchars($game['player3_name']);
                                                                    if ($game['player4_name']) $players[] = htmlspecialchars($game['player4_name']);
                                                                    if ($game['player5_name']) $players[] = htmlspecialchars($game['player5_name']);
                                                                    echo implode(', ', $players);
                                                                    if (empty($players)) echo 'TBD';
                                                                    ?>
                                                                </div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($game['division_name'] ?? 'Unassigned'); ?></small>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-center flex-grow-1">
                                                                <div class="fw-bold">
                                                                    <?php 
                                                                    $players = [];
                                                                    if ($game['player1_name']) $players[] = htmlspecialchars($game['player1_name']);
                                                                    if ($game['player2_name']) $players[] = htmlspecialchars($game['player2_name']);
                                                                    if ($game['player3_name']) $players[] = htmlspecialchars($game['player3_name']);
                                                                    if ($game['player4_name']) $players[] = htmlspecialchars($game['player4_name']);
                                                                    if ($game['player5_name']) $players[] = htmlspecialchars($game['player5_name']);
                                                                    echo implode(', ', $players);
                                                                    if (empty($players)) echo 'TBD';
                                                                    ?>
                                                                </div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($game['division_name'] ?? 'Unassigned'); ?></small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <span class="badge bg-<?php echo $game['status'] == 'completed' ? 'success' : ($game['status'] == 'scheduled' ? 'primary' : 'warning'); ?>">
                                                        <?php echo ucfirst($game['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <?php if ($game['status'] == 'completed'): ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-success">Completed</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center text-muted">
                                                            <small>No score yet</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($game['notes']): ?>
                                                <div class="row mt-2">
                                                    <div class="col-12">
                                                        <small class="text-muted">
                                                            <strong>Notes:</strong> <?php echo htmlspecialchars($game['notes']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-muted">No matches scheduled</h5>
                        <p class="text-muted">No upcoming matches scheduled.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Active Pickleball Season</h4>
                    <p>There is currently no active pickleball season.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
