<?php
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

// Get ladder positions for each division
$division_ladders = [];
if ($current_season && !empty($divisions)) {
    foreach ($divisions as $division) {
        $stmt = $conn->prepare("
            SELECT lp.*, p.name, p.division_id
            FROM pickleball_ladder_positions lp
            JOIN pickleball_players p ON lp.player_id = p.id
            WHERE lp.season_id = ? AND p.division_id = ? AND p.is_active = 1 $roster
            ORDER BY lp.position
        ");
        $stmt->bind_param("ii", $current_season['id'], $division['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $division_ladders[$division['id']] = [
            'division' => $division,
            'players' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            $division_ladders[$division['id']]['players'][] = $row;
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
    <title>Pickleball Ladder by Division</title>
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
        .ladder-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .ladder-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .position-number {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
        }
        .gold { color: #FFD700; }
        .silver { color: #C0C0C0; }
        .bronze { color: #CD7F32; }
        .player-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2E8B57;
        }
        .stats-row {
            background-color: rgba(46, 139, 87, 0.1);
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2E8B57;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏆 Pickleball Ladder</h1>
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
        <?php if (empty($division_ladders)): ?>
            <div class="alert alert-info">
                <h4>No Ladder Positions</h4>
                <p>No ladder positions have been calculated yet. Scores need to be entered first.</p>
            </div>
        <?php else: ?>
            <?php foreach ($division_ladders as $division_id => $division_data): ?>
                <div class="division-card mb-4">
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
                        <div class="row p-3">
                            <?php foreach ($division_data['players'] as $position): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="ladder-card">
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <div class="position-number 
                                                    <?php 
                                                    if ($position['position'] == 1) echo 'gold';
                                                    elseif ($position['position'] == 2) echo 'silver';
                                                    elseif ($position['position'] == 3) echo 'bronze';
                                                    ?>">
                                                    #<?php echo $position['position']; ?>
                                                </div>
                                            </div>
                                            <div class="col-9">
                                                <div class="player-name"><?php echo htmlspecialchars($position['name']); ?></div>
                                                
                                                <div class="stats-row">
                                                    <div class="row">
                                                        <div class="col-4 stat-item">
                                                            <div class="stat-number"><?php echo $position['wins']; ?></div>
                                                            <div class="stat-label">Wins</div>
                                                        </div>
                                                        <div class="col-4 stat-item">
                                                            <div class="stat-number"><?php echo $position['losses']; ?></div>
                                                            <div class="stat-label">Losses</div>
                                                        </div>
                                                        <div class="col-4 stat-item">
                                                            <div class="stat-number"><?php echo number_format($position['win_percentage'], 1); ?>%</div>
                                                            <div class="stat-label">Win %</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="stats-row">
                                                    <div class="row">
                                                        <div class="col-6 stat-item">
                                                            <div class="stat-number"><?php echo $position['points_scored']; ?></div>
                                                            <div class="stat-label">Points For</div>
                                                        </div>
                                                        <div class="col-6 stat-item">
                                                            <div class="stat-number"><?php echo $position['point_differential']; ?></div>
                                                            <div class="stat-label">Point Diff</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_schedule.php', $current_season)); ?>" class="btn btn-primary me-2">View Schedule</a>
                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_results.php', $current_season)); ?>" class="btn btn-primary me-2">View Results</a>
                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_standings.php', $current_season)); ?>" class="btn btn-success me-2">View Standings</a>
                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_stat_leaders.php', $current_season)); ?>" class="btn btn-info me-2">View Stat Leaders</a>
                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_history.php', $current_season)); ?>" class="btn btn-outline-secondary">History</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
