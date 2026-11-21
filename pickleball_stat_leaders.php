<?php
session_start();
include 'admin/db.php';

// Active season, or the archive season passed as ?season=
$current_season = getPickleballSeasonForRequest($conn);

// Get divisions with players for current season
$divisions = [];
$roster = pickleballRosterSql($conn);
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT d.*, COUNT(p.id) as player_count
        FROM pickleball_divisions d
        INNER JOIN pickleball_players p ON d.id = p.division_id
        WHERE d.season_id = ? AND p.is_active = 1 $roster
        GROUP BY d.id
        ORDER BY d.name
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
    $stmt->close();
}

// Get stat leaders for each division
$stat_leaders = [];
if ($current_season && !empty($divisions)) {
    foreach ($divisions as $division) {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   COUNT(g.id) as games_played,
                   SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) as completed_games,
                   SUM(CASE 
                       WHEN g.status = 'completed' AND (
                           (g.player1_id = p.id AND g.player1_score > g.player2_score) OR
                           (g.player2_id = p.id AND g.player2_score > g.player1_score) OR
                           (g.player3_id = p.id AND g.player3_score > g.player4_score) OR
                           (g.player4_id = p.id AND g.player4_score > g.player3_score)
                       ) THEN 1 
                       ELSE 0 
                   END) as wins,
                   SUM(CASE 
                       WHEN g.status = 'completed' AND (
                           (g.player1_id = p.id AND g.player1_score < g.player2_score) OR
                           (g.player2_id = p.id AND g.player2_score < g.player1_score) OR
                           (g.player3_id = p.id AND g.player3_score < g.player4_score) OR
                           (g.player4_id = p.id AND g.player4_score < g.player3_score)
                       ) THEN 1 
                       ELSE 0 
                   END) as losses,
                   SUM(CASE 
                       WHEN g.status = 'completed' THEN (
                           CASE 
                               WHEN g.player1_id = p.id THEN g.player1_score
                               WHEN g.player2_id = p.id THEN g.player2_score
                               WHEN g.player3_id = p.id THEN g.player3_score
                               WHEN g.player4_id = p.id THEN g.player4_score
                               ELSE 0
                           END
                       ) ELSE 0 
                   END) as total_points_scored,
                   SUM(CASE 
                       WHEN g.status = 'completed' THEN (
                           CASE 
                               WHEN g.player1_id = p.id THEN g.player2_score
                               WHEN g.player2_id = p.id THEN g.player1_score
                               WHEN g.player3_id = p.id THEN g.player4_score
                               WHEN g.player4_id = p.id THEN g.player3_score
                               ELSE 0
                           END
                       ) ELSE 0 
                   END) as total_points_allowed
            FROM pickleball_players p
            LEFT JOIN pickleball_games g ON (
                g.player1_id = p.id OR g.player2_id = p.id OR 
                g.player3_id = p.id OR g.player4_id = p.id
            ) AND g.season_id = ? AND g.division_id = ?
            WHERE p.division_id = ? AND p.is_active = 1
            GROUP BY p.id
            HAVING completed_games > 0
            ORDER BY wins DESC, completed_games DESC, p.name
        ");
        $stmt->bind_param("iii", $current_season['id'], $division['id'], $division['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $division_stats = [];
        while ($row = $result->fetch_assoc()) {
            $row['win_percentage'] = $row['completed_games'] > 0 ? round(($row['wins'] / $row['completed_games']) * 100, 1) : 0;
            $row['points_per_game'] = $row['completed_games'] > 0 ? round($row['total_points_scored'] / $row['completed_games'], 1) : 0;
            $row['points_allowed_per_game'] = $row['completed_games'] > 0 ? round($row['total_points_allowed'] / $row['completed_games'], 1) : 0;
            $row['point_differential'] = $row['total_points_scored'] - $row['total_points_allowed'];
            $row['point_differential_per_game'] = $row['completed_games'] > 0 ? round($row['point_differential'] / $row['completed_games'], 1) : 0;
            $division_stats[] = $row;
        }
        $stat_leaders[$division['id']] = $division_stats;
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Stat Leaders - <?php echo $current_season ? htmlspecialchars($current_season['name']) : 'No Active Season'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .division-card {
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stats-table {
            margin-bottom: 0;
        }
        .stat-leader {
            background-color: #e8f5e8;
            font-weight: bold;
        }
        .no-season {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .stat-category {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 mb-0">📊 Pickleball Stat Leaders</h1>
                    <?php if ($current_season): ?>
                        <p class="lead mb-0"><?php echo htmlspecialchars($current_season['name']); ?> Season</p>
                        <small class="opacity-75">
                            <?php echo date('M j, Y', strtotime($current_season['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($current_season['end_date'])); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_standings.php', $current_season)); ?>" class="btn btn-light me-2">🏆 Standings</a>
                    <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_schedule.php', $current_season)); ?>" class="btn btn-outline-light me-2">📅 Schedule</a>
                    <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_results.php', $current_season)); ?>" class="btn btn-outline-light me-2">📊 Results</a>
                    <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_history.php', $current_season)); ?>" class="btn btn-outline-light">History</a>
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
                            <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_results.php', $current_season)); ?>" class="btn btn-primary">📊 Results</a>
                            <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_history.php', $current_season)); ?>" class="btn btn-outline-secondary">History</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo pickleballArchiveBanner($current_season); ?>
        <?php if (!$current_season): ?>
            <div class="no-season">
                <h3>No Active Season</h3>
                <p>There is currently no active pickleball season.</p>
            </div>
        <?php elseif (empty($divisions)): ?>
            <div class="no-season">
                <h3>No Divisions Available</h3>
                <p>No divisions with active players found for the current season.</p>
            </div>
        <?php else: ?>
            <?php foreach ($divisions as $division): ?>
                <div class="card division-card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><?php echo htmlspecialchars($division['name']); ?></h4>
                        <small><?php echo $division['player_count']; ?> player<?php echo $division['player_count'] != 1 ? 's' : ''; ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stat_leaders[$division['id']])): ?>
                            <?php 
                            $stats = $stat_leaders[$division['id']];
                            
                            // Sort by different criteria for different leaderboards
                            $wins_leaders = $stats;
                            usort($wins_leaders, function($a, $b) {
                                return $b['wins'] - $a['wins'];
                            });
                            
                            $win_pct_leaders = $stats;
                            usort($win_pct_leaders, function($a, $b) {
                                if ($a['completed_games'] < 3 && $b['completed_games'] < 3) return 0;
                                if ($a['completed_games'] < 3) return 1;
                                if ($b['completed_games'] < 3) return -1;
                                return $b['win_percentage'] - $a['win_percentage'];
                            });
                            
                            $ppg_leaders = $stats;
                            usort($ppg_leaders, function($a, $b) {
                                return $b['points_per_game'] - $a['points_per_game'];
                            });
                            
                            $games_leaders = $stats;
                            usort($games_leaders, function($a, $b) {
                                return $b['completed_games'] - $a['completed_games'];
                            });
                            ?>
                            
                            <div class="row">
                                <!-- Most Wins -->
                                <div class="col-md-6 mb-4">
                                    <div class="stat-category">
                                        <h5 class="mb-3">🏆 Most Wins</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm stats-table">
                                                <thead>
                                                    <tr>
                                                        <th>Player</th>
                                                        <th class="text-center">Wins</th>
                                                        <th class="text-center">Games</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($wins_leaders, 0, 5) as $index => $player): ?>
                                                        <tr class="<?php echo $index == 0 ? 'stat-leader' : ''; ?>">
                                                            <td><?php echo htmlspecialchars($player['name']); ?></td>
                                                            <td class="text-center fw-bold"><?php echo $player['wins']; ?></td>
                                                            <td class="text-center"><?php echo $player['completed_games']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Win Percentage -->
                                <div class="col-md-6 mb-4">
                                    <div class="stat-category">
                                        <h5 class="mb-3">📈 Win Percentage</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm stats-table">
                                                <thead>
                                                    <tr>
                                                        <th>Player</th>
                                                        <th class="text-center">PCT</th>
                                                        <th class="text-center">Games</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($win_pct_leaders, 0, 5) as $index => $player): ?>
                                                        <tr class="<?php echo $index == 0 ? 'stat-leader' : ''; ?>">
                                                            <td><?php echo htmlspecialchars($player['name']); ?></td>
                                                            <td class="text-center fw-bold"><?php echo $player['win_percentage']; ?>%</td>
                                                            <td class="text-center"><?php echo $player['completed_games']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Points Per Game -->
                                <div class="col-md-6 mb-4">
                                    <div class="stat-category">
                                        <h5 class="mb-3">⚡ Points Per Game</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm stats-table">
                                                <thead>
                                                    <tr>
                                                        <th>Player</th>
                                                        <th class="text-center">PPG</th>
                                                        <th class="text-center">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($ppg_leaders, 0, 5) as $index => $player): ?>
                                                        <tr class="<?php echo $index == 0 ? 'stat-leader' : ''; ?>">
                                                            <td><?php echo htmlspecialchars($player['name']); ?></td>
                                                            <td class="text-center fw-bold"><?php echo $player['points_per_game']; ?></td>
                                                            <td class="text-center"><?php echo $player['total_points_scored']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Most Games Played -->
                                <div class="col-md-6 mb-4">
                                    <div class="stat-category">
                                        <h5 class="mb-3">🎯 Most Games Played</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm stats-table">
                                                <thead>
                                                    <tr>
                                                        <th>Player</th>
                                                        <th class="text-center">Games</th>
                                                        <th class="text-center">W-L</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($games_leaders, 0, 5) as $index => $player): ?>
                                                        <tr class="<?php echo $index == 0 ? 'stat-leader' : ''; ?>">
                                                            <td><?php echo htmlspecialchars($player['name']); ?></td>
                                                            <td class="text-center fw-bold"><?php echo $player['completed_games']; ?></td>
                                                            <td class="text-center"><?php echo $player['wins']; ?>-<?php echo $player['losses']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <p>No completed games found in this division.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Stat Categories</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>🏆 Most Wins:</strong> Total number of games won
                            </div>
                            <div class="col-md-3">
                                <strong>📈 Win Percentage:</strong> Wins divided by completed games (minimum 3 games)
                            </div>
                            <div class="col-md-3">
                                <strong>⚡ Points Per Game:</strong> Average points scored per completed game
                            </div>
                            <div class="col-md-3">
                                <strong>🎯 Most Games Played:</strong> Total number of completed games
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>