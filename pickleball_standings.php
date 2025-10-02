<?php
session_start();
include 'admin/db.php';

// Get current season
$current_season = null;
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}
$stmt->close();

// Get divisions with players for current season
$divisions = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT d.*, COUNT(p.id) as player_count
        FROM pickleball_divisions d
        INNER JOIN pickleball_players p ON d.id = p.division_id
        WHERE d.season_id = ? AND p.is_active = 1
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

// Get standings for each division
$division_standings = [];
if ($current_season && !empty($divisions)) {
    foreach ($divisions as $division) {
        // Get all players in this division (including those with no games played)
        $stmt = $conn->prepare("
            SELECT 
                p.id as player_id,
                p.name,
                p.division_id,
                COALESCE(lp.position, 999) as position,
                COALESCE(lp.wins, 0) as wins,
                COALESCE(lp.losses, 0) as losses,
                COALESCE(lp.points_scored, 0) as points_scored,
                COALESCE(lp.points_against, 0) as points_against,
                COALESCE(lp.win_percentage, 0) as win_percentage,
                COALESCE(lp.point_differential, 0) as point_differential,
                CASE WHEN lp.player_id IS NULL THEN 1 ELSE 0 END as no_games_played
            FROM pickleball_players p
            LEFT JOIN pickleball_ladder_positions lp ON p.id = lp.player_id AND lp.season_id = ?
            WHERE p.division_id = ? AND p.is_active = 1
            ORDER BY 
                CASE WHEN lp.player_id IS NULL THEN 1 ELSE 0 END,
                COALESCE(lp.position, 999),
                p.name
        ");
        $stmt->bind_param("ii", $current_season['id'], $division['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $division_standings[$division['id']] = [
            'division' => $division,
            'players' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            $division_standings[$division['id']]['players'][] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Standings by Division</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .division-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .division-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .standings-table {
            margin: 0;
        }
        .standings-table th {
            background-color: #e9ecef;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        .standings-table td {
            border: none;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .position-badge {
            font-size: 1.1rem;
            font-weight: bold;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .position-1 { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
        .position-2 { background: linear-gradient(135deg, #C0C0C0 0%, #A0A0A0 100%); }
        .position-3 { background: linear-gradient(135deg, #CD7F32 0%, #B8860B 100%); }
        .position-other { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        .player-name {
            font-weight: 600;
            color: #2E8B57;
        }
        .stats-cell {
            text-align: center;
            font-weight: 500;
        }
        .win-percentage {
            font-weight: bold;
            color: #28a745;
        }
        .point-diff {
            font-weight: bold;
        }
        .point-diff.positive {
            color: #28a745;
        }
        .point-diff.negative {
            color: #dc3545;
        }
        .point-diff.neutral {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏆 Pickleball Standings</h1>
                    <p class="lead mb-0">
                        <?php if ($current_season): ?>
                            <?php echo htmlspecialchars($current_season['name']); ?> Season - By Division
                        <?php else: ?>
                            Current Season - By Division
                        <?php endif; ?>
                    </p>
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
        <?php if (empty($division_standings)): ?>
            <div class="alert alert-info">
                <h4>No Standings Available</h4>
                <p>No division standings found for the current season. Players need to be assigned to divisions and games need to be completed.</p>
            </div>
        <?php else: ?>
            <?php foreach ($division_standings as $division_id => $division_data): ?>
                <div class="division-card">
                    <div class="division-header">
                        <?php echo htmlspecialchars($division_data['division']['name']); ?>
                        <span class="badge bg-light text-dark ms-2">
                            <?php echo count($division_data['players']); ?> Player<?php echo count($division_data['players']) != 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    
                    <?php if (empty($division_data['players'])): ?>
                        <div class="p-3 text-muted">
                            No players in this division yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table standings-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Player</th>
                                        <th class="stats-cell">Wins</th>
                                        <th class="stats-cell">Losses</th>
                                        <th class="stats-cell">Win %</th>
                                        <th class="stats-cell">Points For</th>
                                        <th class="stats-cell">Points Against</th>
                                        <th class="stats-cell">Point Diff</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $position_counter = 1;
                                    foreach ($division_data['players'] as $player): 
                                        $is_no_games = $player['no_games_played'] == 1;
                                    ?>
                                        <tr class="<?php echo $is_no_games ? 'table-light' : ''; ?>">
                                            <td>
                                                <?php if ($is_no_games): ?>
                                                    <div class="position-badge position-other" style="background: linear-gradient(135deg, #e9ecef 0%, #ced4da 100%); color: #6c757d;">
                                                        -
                                                    </div>
                                                <?php else: ?>
                                                    <div class="position-badge 
                                                        <?php 
                                                        if ($player['position'] == 1) echo 'position-1';
                                                        elseif ($player['position'] == 2) echo 'position-2';
                                                        elseif ($player['position'] == 3) echo 'position-3';
                                                        else echo 'position-other';
                                                        ?>">
                                                        <?php echo $player['position']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="player-name">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                    <?php if ($is_no_games): ?>
                                                        <small class="text-muted">(No games played)</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?php echo $player['wins']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><?php echo $player['losses']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <span class="win-percentage"><?php echo number_format($player['win_percentage'], 1); ?>%</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <strong><?php echo $player['points_scored']; ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <strong><?php echo $player['points_against']; ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <span class="point-diff 
                                                        <?php 
                                                        if ($player['point_differential'] > 0) echo 'positive';
                                                        elseif ($player['point_differential'] < 0) echo 'negative';
                                                        else echo 'neutral';
                                                        ?>">
                                                        <?php echo $player['point_differential'] > 0 ? '+' : ''; ?><?php echo $player['point_differential']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                    $position_counter++;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>