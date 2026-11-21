<?php
include 'admin/db.php';

// Active season, or the archive season passed as ?season=
$current_season = getPickleballSeasonForRequest($conn);

// Get divisions for current season (only those with players)
$divisions = [];
$roster = pickleballRosterSql($conn);
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT DISTINCT d.*, COUNT(p.id) as player_count
        FROM pickleball_divisions d
        INNER JOIN pickleball_players p ON d.id = p.division_id
        WHERE d.season_id = ? AND d.is_active = 1 AND p.is_active = 1 $roster
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

$schedule_players = [];
$selected_player_id = isset($_GET['player']) ? (int)$_GET['player'] : 0;
$selected_player_name = '';
if ($current_season) {
    $substituteColumn = pickleballHasSubstituteColumn($conn) ? 'p.is_substitute' : '0 AS is_substitute';
    $stmt = $conn->prepare("
        SELECT p.id, p.name, d.name AS division_name, $substituteColumn
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON d.id = p.division_id
        WHERE p.season_id = ? AND p.is_active = 1
        ORDER BY p.name
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $schedule_players[] = $row;
        if ((int)$row['id'] === $selected_player_id) {
            $selected_player_name = $row['name'];
        }
    }
    $stmt->close();
    if ($selected_player_name === '') {
        $selected_player_id = 0;
    }
}

$week_statuses = [];
$players_by_division = [];
if ($current_season) {
    $week_statuses = pickleballLoadWeekStatuses($conn, (int)$current_season['id']);
    foreach ($schedule_players as $schedule_player) {
        $divisionName = $schedule_player['division_name'] ?: 'Unassigned';
        $players_by_division[$divisionName][] = $schedule_player;
    }
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
               p6.name AS player6_name, p7.name AS player7_name,
               pg.status, 
               d.name AS division_name, d.id AS division_id,
               pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id,
               pg.player6_id, pg.player7_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
        LEFT JOIN pickleball_players p6 ON pg.player6_id = p6.id
        LEFT JOIN pickleball_players p7 ON pg.player7_id = p7.id
        LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
        WHERE " . (empty($current_season['is_active']) ? "" : "DATE(pg.date) >= CURDATE() AND ") . "pg.season_id = ?
        " . ($selected_player_id ? "AND ? IN (pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id, pg.player5_id, pg.player6_id, pg.player7_id)" : "") . "
        ORDER BY game_date, start_time, pg.location, pg.id
    ";
    
    try {
        $stmt = $conn->prepare($query);
        if ($selected_player_id) {
            $stmt->bind_param("ii", $current_season['id'], $selected_player_id);
        } else {
            $stmt->bind_param("i", $current_season['id']);
        }
        $stmt->execute();
        $schedule = $stmt->get_result();
        $stmt->close();
    } catch (Exception $e) {
        $schedule_error = 'Error loading schedule: ' . $e->getMessage();
        $schedule = null;
    }
}

// Organize data by date, then start time. Courts stay in numeric order inside each time.
$grouped_schedule = [];
if ($schedule) {
    while ($game = $schedule->fetch_assoc()) {
        $grouped_schedule[$game['game_date']][$game['start_time']][] = $game;
    }
    foreach ($grouped_schedule as &$times) {
        ksort($times);
        foreach ($times as &$games) {
            usort($games, function ($a, $b) {
                $courtNumber = function ($location) {
                    return preg_match('/(\d+)/', (string)$location, $match) ? (int)$match[1] : PHP_INT_MAX;
                };
                $courtCompare = $courtNumber($a['location']) <=> $courtNumber($b['location']);
                if ($courtCompare !== 0) {
                    return $courtCompare;
                }
                return strcmp((string)$a['location'], (string)$b['location']);
            });
        }
        unset($games);
    }
    unset($times);
}

$schedule_dates = [];
if ($current_season) {
    $dateQuery = "
        SELECT DISTINCT DATE(date) AS game_date
        FROM pickleball_games
        WHERE " . (empty($current_season['is_active']) ? "" : "DATE(date) >= CURDATE() AND ") . "season_id = ?
        ORDER BY game_date
    ";
    $dateStmt = $conn->prepare($dateQuery);
    $dateStmt->bind_param("i", $current_season['id']);
    $dateStmt->execute();
    $dateResult = $dateStmt->get_result();
    while ($row = $dateResult->fetch_assoc()) {
        $schedule_dates[] = $row['game_date'];
    }
    $dateStmt->close();
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
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }
        .date-map-link {
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: underline;
        }
        .date-map-link:hover {
            color: #fff;
        }
        .time-header {
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
        .player-link {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s;
        }
        .player-link:hover {
            color: #2E8B57;
            text-decoration: underline;
        }
        .week-roster {
            border: 1px solid #dee2e6;
            border-top: none;
            background: #fff;
            padding: 0;
        }
        .week-roster-summary {
            list-style: none;
            cursor: pointer;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 1rem;
            user-select: none;
        }
        .week-roster-summary::-webkit-details-marker {
            display: none;
        }
        .week-roster-summary::marker {
            content: "";
        }
        .week-roster-summary::before {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            margin-right: 0.15rem;
            border-right: 2px solid #495057;
            border-bottom: 2px solid #495057;
            transform: rotate(-45deg);
            flex: 0 0 auto;
        }
        .week-roster[open] > .week-roster-summary::before {
            transform: rotate(45deg);
            margin-top: -0.2rem;
        }
        .week-roster-lists {
            padding: 0.75rem 1rem 0.35rem;
            border-top: 1px solid #dee2e6;
        }
        .week-player {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0 0.85rem 0.55rem 0;
        }
        .week-player.is-selected .player-link {
            font-weight: 700;
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
                        <p class="mb-0"><?php echo ($current_season && empty($current_season['is_active'])) ? 'Season games' : 'Upcoming matches and games'; ?></p>
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
                                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_standings.php', $current_season)); ?>" class="btn btn-success">🏆 Standings</a>
                                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_schedule.php', $current_season)); ?>" class="btn btn-info">📅 Schedule</a>
                                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_history.php', $current_season)); ?>" class="btn btn-outline-secondary">History</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo pickleballArchiveBanner($current_season); ?>
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


                <form method="get" class="card mb-4">
                    <div class="card-body">
                        <?php if ($current_season && empty($current_season['is_active'])): ?>
                            <input type="hidden" name="season" value="<?php echo (int)$current_season['id']; ?>">
                        <?php endif; ?>
                        <label for="player" class="form-label fw-bold">Show one player</label>
                        <select class="form-select" id="player" name="player" onchange="this.form.submit()">
                            <option value="">All players</option>
                            <?php foreach ($schedule_players as $schedule_player): ?>
                                <option value="<?php echo (int)$schedule_player['id']; ?>" <?php echo (int)$schedule_player['id'] === $selected_player_id ? 'selected' : ''; ?>>
                                    <?php
                                    echo htmlspecialchars($schedule_player['name']);
                                    if (!empty($schedule_player['division_name'])) {
                                        echo ' (' . htmlspecialchars($schedule_player['division_name']);
                                        echo !empty($schedule_player['is_substitute']) ? ', substitute' : '';
                                        echo ')';
                                    } elseif (!empty($schedule_player['is_substitute'])) {
                                        echo ' (substitute)';
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <?php if ($selected_player_name): ?>
                                Court assignments for <?php echo htmlspecialchars($selected_player_name); ?>.
                            <?php else: ?>
                                Choose a name to see that player's courts and times.
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <!-- Schedule -->
                <?php if (!empty($schedule_dates)): ?>
                    <?php
                    $bash_map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Bash Pickleball Club, 6881 Chicago Rd, Warren, MI 48092');
                    ?>
                    <?php foreach ($schedule_dates as $date): ?>
                        <?php $times = $grouped_schedule[$date] ?? []; ?>
                        <div class="schedule-group">
                            <h4 class="date-header">
                                <span><?php echo date('l, F j, Y', strtotime($date)); ?></span>
                                <?php if (!empty($current_season['is_active'])): ?>
                                    <a class="date-map-link" href="<?php echo htmlspecialchars($bash_map_url); ?>" target="_blank" rel="noopener noreferrer">Map</a>
                                <?php endif; ?>
                            </h4>
                            <?php if ($players_by_division): ?>
                                <?php
                                $playersByStatus = ['confirmed' => [], 'not confirmed' => [], 'not playing' => []];
                                foreach ($schedule_players as $schedule_player) {
                                    $playerStatus = pickleballNormalizeWeekStatus($week_statuses[$date][(int)$schedule_player['id']] ?? 'not confirmed');
                                    $playersByStatus[$playerStatus][] = $schedule_player;
                                }
                                ?>
                                <details class="week-roster">
                                    <summary class="week-roster-summary">
                                        <span class="fw-bold">Confirmations</span>
                                        <span class="badge bg-success"><?php echo count($playersByStatus['confirmed']); ?> confirmed</span>
                                        <span class="badge bg-secondary"><?php echo count($playersByStatus['not confirmed']); ?> not confirmed</span>
                                        <span class="badge bg-danger"><?php echo count($playersByStatus['not playing']); ?> not playing</span>
                                    </summary>
                                    <div class="week-roster-lists">
                                        <?php foreach ($playersByStatus as $playerStatus => $statusPlayers): ?>
                                            <div class="mb-2">
                                                <div class="small fw-bold text-<?php echo htmlspecialchars(pickleballWeekStatusClass($playerStatus)); ?>">
                                                    <?php echo htmlspecialchars(pickleballWeekStatusLabel($playerStatus)); ?>
                                                </div>
                                                <?php if (!$statusPlayers): ?>
                                                    <div class="small text-muted mb-1">None</div>
                                                <?php endif; ?>
                                                <?php foreach ($statusPlayers as $schedule_player): ?>
                                                    <?php $isSelected = $selected_player_id && (int)$schedule_player['id'] === $selected_player_id; ?>
                                                    <span class="week-player<?php echo $isSelected ? ' is-selected' : ''; ?>">
                                                        <a href="pickleball_player_details.php?player_id=<?php echo (int)$schedule_player['id']; ?>" class="player-link"><?php echo htmlspecialchars($schedule_player['name']); ?></a>
                                                        <?php if (!empty($schedule_player['division_name'])): ?>
                                                            <span class="small text-muted"><?php echo htmlspecialchars($schedule_player['division_name']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($schedule_player['is_substitute'])): ?>
                                                            <span class="badge bg-light text-dark">Sub</span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                            
                            <?php if (!$times && $selected_player_name): ?>
                                <div class="card">
                                    <div class="p-3 text-muted">No court assignment for <?php echo htmlspecialchars($selected_player_name); ?> this week.</div>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($times as $start_time => $games): ?>
                                <div class="card">
                                    <div class="time-header">
                                        <?php echo date('g:i A', strtotime($start_time)); ?>
                                    </div>
                                    
                                    <?php foreach ($games as $game): ?>
                                        <div class="game-card p-3">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars($game['location'] ?: 'Court TBD'); ?>
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
                                                                    for ($i = 1; $i <= 7; $i++) {
                                                                        $name_key = "player{$i}_name";
                                                                        $id_key = "player{$i}_id";
                                                                        if (!empty($game[$name_key]) && !empty($game[$id_key])) {
                                                                            $playerName = htmlspecialchars($game[$name_key]);
                                                                            if ($selected_player_id && (int)$game[$id_key] === $selected_player_id) {
                                                                                $playerName = '<strong>' . $playerName . '</strong>';
                                                                            }
                                                                            $players[] = '<a href="pickleball_player_details.php?player_id=' . $game[$id_key] . '" class="player-link">' . $playerName . '</a>';
                                                                        } elseif (!empty($game[$name_key])) {
                                                                            $players[] = htmlspecialchars($game[$name_key]);
                                                                        }
                                                                    }
                                                                    echo implode(', ', $players);
                                                                    if (empty($players)) echo 'TBD';
                                                                    ?>
                                                                </div>
                                                                <?php if (!empty($game['division_name'])): ?><small class="text-muted"><?php echo htmlspecialchars($game['division_name']); ?></small><?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-center flex-grow-1">
                                                                <div class="fw-bold">
                                                                    <?php 
                                                                    $players = [];
                                                                    for ($i = 1; $i <= 7; $i++) {
                                                                        $name_key = "player{$i}_name";
                                                                        $id_key = "player{$i}_id";
                                                                        if (!empty($game[$name_key]) && !empty($game[$id_key])) {
                                                                            $playerName = htmlspecialchars($game[$name_key]);
                                                                            if ($selected_player_id && (int)$game[$id_key] === $selected_player_id) {
                                                                                $playerName = '<strong>' . $playerName . '</strong>';
                                                                            }
                                                                            $players[] = '<a href="pickleball_player_details.php?player_id=' . $game[$id_key] . '" class="player-link">' . $playerName . '</a>';
                                                                        } elseif (!empty($game[$name_key])) {
                                                                            $players[] = htmlspecialchars($game[$name_key]);
                                                                        }
                                                                    }
                                                                    echo implode(', ', $players);
                                                                    if (empty($players)) echo 'TBD';
                                                                    ?>
                                                                </div>
                                                                <?php if (!empty($game['division_name'])): ?><small class="text-muted"><?php echo htmlspecialchars($game['division_name']); ?></small><?php endif; ?>
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
                        <?php if ($selected_player_name): ?>
                            <h5 class="text-muted">No court assignments for <?php echo htmlspecialchars($selected_player_name); ?></h5>
                            <p class="text-muted">This player is not on an upcoming court.</p>
                        <?php else: ?>
                            <h5 class="text-muted">No matches scheduled</h5>
                            <p class="text-muted">No upcoming matches scheduled.</p>
                        <?php endif; ?>
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
