<?php
include 'admin/db.php';

// Get current pickleball season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get all seasons for dropdown
$all_seasons = [];
$seasons_result = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
while ($row = $seasons_result->fetch_assoc()) {
    $all_seasons[] = $row;
}

// Get selected season
$selected_season_id = isset($_GET['season']) ? (int)$_GET['season'] : ($current_season ? $current_season['id'] : null);
$selected_season = null;
if ($selected_season_id) {
    $stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE id = ?");
    $stmt->bind_param("i", $selected_season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_season = $result->fetch_assoc();
    $stmt->close();
}

// Get season champions (top player in each division)
$champions = [];
if ($selected_season) {
    $stmt = $conn->prepare("
        SELECT p.name, p.current_rating, d.name as division_name, d.skill_level,
               COUNT(pg.id) as total_games,
               SUM(CASE 
                   WHEN (pg.player1_id = p.id AND ps.player1_score > ps.player2_score) OR 
                        (pg.player2_id = p.id AND ps.player2_score > ps.player1_score) 
                   THEN 1 ELSE 0 END) as wins,
               SUM(CASE 
                   WHEN (pg.player1_id = p.id AND ps.player1_score < ps.player2_score) OR 
                        (pg.player2_id = p.id AND ps.player2_score < ps.player1_score) 
                   THEN 1 ELSE 0 END) as losses
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON p.division_id = d.id
        LEFT JOIN pickleball_games pg ON (pg.player1_id = p.id OR pg.player2_id = p.id) AND pg.season_id = ? AND pg.status = 'completed'
        LEFT JOIN pickleball_scores ps ON pg.id = ps.game_id AND ps.game_number = 1
        WHERE p.season_id = ? AND p.is_active = 1 AND p.ladder_position = 1" . pickleballRosterSql($conn) . "
        GROUP BY p.id, p.name, p.current_rating, d.name, d.skill_level
        ORDER BY d.min_rating ASC
    ");
    $stmt->bind_param("ii", $selected_season['id'], $selected_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $champions[] = $row;
    }
    $stmt->close();
}

// Get recent completed matches
$recent_matches = [];
if ($selected_season) {
    $stmt = $conn->prepare("
        SELECT pg.id, pg.date, pg.location, pg.game_type, pg.is_challenge,
               p1.name as player1_name, p2.name as player2_name,
               p3.name as player3_name, p4.name as player4_name,
               ps.player1_score, ps.player2_score, ps.player3_score, ps.player4_score,
               d.name as division_name
        FROM pickleball_games pg
        JOIN pickleball_players p1 ON pg.player1_id = p1.id
        JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_scores ps ON pg.id = ps.game_id AND ps.game_number = 1
        LEFT JOIN pickleball_divisions d ON p1.division_id = d.id
        WHERE pg.season_id = ? AND pg.status = 'completed'
        ORDER BY pg.date DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $selected_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_matches[] = $row;
    }
    $stmt->close();
}

// Get season statistics
$season_stats = [];
if ($selected_season) {
    // Total players
    $stmt = $conn->prepare("SELECT COUNT(*) as total_players FROM pickleball_players WHERE season_id = ? AND is_active = 1" . pickleballRosterSql($conn, 'pickleball_players'));
    $stmt->bind_param("i", $selected_season['id']);
    $stmt->execute();
    $season_stats['total_players'] = $stmt->get_result()->fetch_assoc()['total_players'];
    $stmt->close();
    
    // Total matches
    $stmt = $conn->prepare("SELECT COUNT(*) as total_matches FROM pickleball_games WHERE season_id = ?");
    $stmt->bind_param("i", $selected_season['id']);
    $stmt->execute();
    $season_stats['total_matches'] = $stmt->get_result()->fetch_assoc()['total_matches'];
    $stmt->close();
    
    // Completed matches
    $stmt = $conn->prepare("SELECT COUNT(*) as completed_matches FROM pickleball_games WHERE season_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $selected_season['id']);
    $stmt->execute();
    $season_stats['completed_matches'] = $stmt->get_result()->fetch_assoc()['completed_matches'];
    $stmt->close();
    
    // Total challenges
    $stmt = $conn->prepare("SELECT COUNT(*) as total_challenges FROM pickleball_challenges WHERE season_id = ?");
    $stmt->bind_param("i", $selected_season['id']);
    $stmt->execute();
    $season_stats['total_challenges'] = $stmt->get_result()->fetch_assoc()['total_challenges'];
    $stmt->close();
    
    // Completed challenges
    $stmt = $conn->prepare("SELECT COUNT(*) as completed_challenges FROM pickleball_challenges WHERE season_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $selected_season['id']);
    $stmt->execute();
    $season_stats['completed_challenges'] = $stmt->get_result()->fetch_assoc()['completed_challenges'];
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Season History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .champion-card {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
            border: 2px solid #FFD700;
        }
        .match-card {
            transition: transform 0.2s;
        }
        .match-card:hover {
            transform: translateY(-2px);
        }
        .season-selector {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
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
                            <h1 class="display-4 mb-0">🏓 Pickleball Season History</h1>
                            <p class="mb-0">Past seasons and historical data</p>
                        </div>
                        <div>
                            <a href="history.php" class="btn btn-light me-2">🏀 Basketball</a>
                            <a href="volleyball_history.php" class="btn btn-light me-2">🏐 Volleyball</a>
                            <a href="pickleball_standings.php" class="btn btn-light me-2">🏓 Pickleball</a>
                            <a href="pickleball_history.php<?php echo $selected_season ? '?season=' . (int)$selected_season['id'] : ''; ?>" class="btn btn-outline-light">History</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Season Selector -->
            <div class="season-selector">
                <h6>Select Season:</h6>
                <form method="GET" class="d-flex align-items-center">
                    <div class="me-3">
                        <select name="season" id="season" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($all_seasons as $season): ?>
                                <option value="<?php echo $season['id']; ?>" 
                                        <?php echo $selected_season_id == $season['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($season['name']); ?>
                                    (<?php echo ucfirst($season['ladder_type']); ?>)
                                    <?php if ($season['is_active']): ?>
                                        (Active)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">View Season</button>
                </form>
            </div>

            <?php if ($selected_season): ?>
                <!-- Season Info -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🏓 <?php echo htmlspecialchars($selected_season['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Season:</strong> <?php echo date('M j, Y', strtotime($selected_season['start_date'])); ?> - <?php echo date('M j, Y', strtotime($selected_season['end_date'])); ?></p>
                                <p><strong>Type:</strong> <?php echo ucfirst($selected_season['ladder_type']); ?> Ladder</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Max Players:</strong> <?php echo $selected_season['max_players']; ?></p>
                                <p><strong>Challenge Window:</strong> <?php echo $selected_season['challenge_window_days']; ?> days</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Season Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $season_stats['total_players']; ?></h3>
                                <p class="card-text">Total Players</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $season_stats['total_matches']; ?></h3>
                                <p class="card-text">Total Matches</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $season_stats['completed_matches']; ?></h3>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo $season_stats['total_challenges']; ?></h3>
                                <p class="card-text">Total Challenges</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Season Champions -->
                <?php if (!empty($champions)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">🏆 Season Champions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($champions as $champion): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card champion-card">
                                            <div class="card-body text-center">
                                                <h5 class="card-title"><?php echo htmlspecialchars($champion['division_name']); ?> Division</h5>
                                                <h4 class="mb-2"><?php echo htmlspecialchars($champion['name']); ?></h4>
                                                <p class="mb-1"><strong>Rating:</strong> <?php echo $champion['current_rating']; ?></p>
                                                <p class="mb-1"><strong>Record:</strong> <?php echo $champion['wins']; ?>-<?php echo $champion['losses']; ?></p>
                                                <p class="mb-0"><strong>Games:</strong> <?php echo $champion['total_games']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Matches -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">📅 Recent Matches</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_matches)): ?>
                            <div class="row">
                                <?php foreach ($recent_matches as $match): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card match-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?php if ($match['game_type'] == 'singles'): ?>
                                                                <?php 
                                                                $players = [];
                                                                if ($match['player1_name']) $players[] = htmlspecialchars($match['player1_name']);
                                                                if ($match['player2_name']) $players[] = htmlspecialchars($match['player2_name']);
                                                                if ($match['player3_name']) $players[] = htmlspecialchars($match['player3_name']);
                                                                if ($match['player4_name']) $players[] = htmlspecialchars($match['player4_name']);
                                                                if ($match['player5_name']) $players[] = htmlspecialchars($match['player5_name']);
                                                                echo implode(', ', $players);
                                                                if (empty($players)) echo 'TBD';
                                                                ?>
                                                            <?php else: ?>
                                                                <?php 
                                                                $players = [];
                                                                if ($match['player1_name']) $players[] = htmlspecialchars($match['player1_name']);
                                                                if ($match['player2_name']) $players[] = htmlspecialchars($match['player2_name']);
                                                                if ($match['player3_name']) $players[] = htmlspecialchars($match['player3_name']);
                                                                if ($match['player4_name']) $players[] = htmlspecialchars($match['player4_name']);
                                                                if ($match['player5_name']) $players[] = htmlspecialchars($match['player5_name']);
                                                                echo implode(', ', $players);
                                                                if (empty($players)) echo 'TBD';
                                                                ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($match['division_name'] ?? 'Unassigned'); ?> • 
                                                            <?php echo htmlspecialchars($match['location']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php if ($match['is_challenge']): ?>
                                                            <span class="badge bg-danger">Challenge</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Ladder</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo $match['player1_score']; ?> - <?php echo $match['player2_score']; ?></strong>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y g:i A', strtotime($match['date'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No matches found for this season.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Season Timeline -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">📈 Season Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6>Season Started</h6>
                                    <p class="text-muted"><?php echo date('F j, Y', strtotime($selected_season['start_date'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($season_stats['total_challenges'] > 0): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6>First Challenge Issued</h6>
                                        <p class="text-muted"><?php echo $season_stats['total_challenges']; ?> total challenges this season</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($season_stats['completed_matches'] > 0): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-warning"></div>
                                    <div class="timeline-content">
                                        <h6>First Match Completed</h6>
                                        <p class="text-muted"><?php echo $season_stats['completed_matches']; ?> matches completed</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6>Season <?php echo $selected_season['is_active'] ? 'Ends' : 'Ended'; ?></h6>
                                    <p class="text-muted"><?php echo date('F j, Y', strtotime($selected_season['end_date'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="row mt-4">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">🏆 Standings</h5>
                                <p class="card-text">View ladder standings</p>
                                <a href="pickleball_standings.php<?php echo $selected_season ? '?season=' . (int)$selected_season['id'] : ''; ?>" class="btn btn-success">View Standings</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">📅 Schedule</h5>
                                <p class="card-text">View upcoming matches</p>
                                <a href="pickleball_schedule.php<?php echo $selected_season ? '?season=' . (int)$selected_season['id'] : ''; ?>" class="btn btn-success">View Schedule</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">📊 Results</h5>
                                <p class="card-text">Scores for each week</p>
                                <a href="pickleball_results.php<?php echo $selected_season ? '?season=' . (int)$selected_season['id'] : ''; ?>" class="btn btn-success">View Results</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Season Selected</h4>
                    <p>Please select a season to view its history.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        .timeline-marker {
            position: absolute;
            left: -35px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 15px;
            width: 2px;
            height: calc(100% + 15px);
            background: #dee2e6;
        }
    </style>
</body>
</html>
