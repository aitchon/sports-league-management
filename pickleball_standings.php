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

// Get standings for each division
$division_standings = [];
if ($current_season && !empty($divisions)) {
    foreach ($divisions as $division) {
        // Get all players in this division (including those with no games played)
        $MIN_GAMES_REQUIRED = 8;
        $stmt = $conn->prepare("
            SELECT 
                p.id as player_id,
                p.name,
                p.division_id,
                COALESCE(lp.position, 999) as position,
                COALESCE(lp.wins, 0) as wins,
                COALESCE(lp.losses, 0) as losses,
                (COALESCE(lp.wins, 0) + COALESCE(lp.losses, 0)) as total_matches,
                COALESCE(lp.points_scored, 0) as points_scored,
                COALESCE(lp.points_against, 0) as points_against,
                COALESCE(lp.win_percentage, 0) as win_percentage,
                COALESCE(lp.point_differential, 0) as point_differential,
                CASE WHEN lp.player_id IS NULL THEN 1 ELSE 0 END as no_games_played,
                CASE WHEN (COALESCE(lp.wins, 0) + COALESCE(lp.losses, 0)) < ? THEN 1 ELSE 0 END as below_minimum_games
            FROM pickleball_players p
            LEFT JOIN pickleball_ladder_positions lp ON p.id = lp.player_id AND lp.season_id = ?
            WHERE p.division_id = ? AND p.is_active = 1 $roster
            ORDER BY 
                CASE WHEN lp.player_id IS NULL THEN 1 ELSE 0 END,
                CASE WHEN (COALESCE(lp.wins, 0) + COALESCE(lp.losses, 0)) < ? THEN 1 ELSE 0 END,
                COALESCE(lp.position, 999),
                p.name
        ");
        $stmt->bind_param("iiii", $MIN_GAMES_REQUIRED, $current_season['id'], $division['id'], $MIN_GAMES_REQUIRED);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $division_standings[$division['id']] = [
            'division' => $division,
            'players' => []
        ];
        
        $players = [];
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }
        $stmt->close();
        
        // Sort players: point differential first, then win percentage, then points scored
        // Players with no games or below minimum games go to the end
        usort($players, function($a, $b) use ($MIN_GAMES_REQUIRED) {
            // Calculate total matches for each player
            $a_total = $a['total_matches'] ?? ($a['wins'] + $a['losses']);
            $b_total = $b['total_matches'] ?? ($b['wins'] + $b['losses']);
            
            // Check if players meet minimum games requirement
            $a_meets_minimum = !$a['no_games_played'] && $a_total >= $MIN_GAMES_REQUIRED;
            $b_meets_minimum = !$b['no_games_played'] && $b_total >= $MIN_GAMES_REQUIRED;
            
            // First, separate players who meet minimum from those who don't
            if ($a_meets_minimum && !$b_meets_minimum) return -1;
            if (!$a_meets_minimum && $b_meets_minimum) return 1;
            if (!$a_meets_minimum && !$b_meets_minimum) {
                // Both don't meet minimum - sort by name
                return strcmp($a['name'], $b['name']);
            }
            
            // Both meet minimum - sort by point differential first
            if ($a['point_differential'] != $b['point_differential']) {
                return $b['point_differential'] <=> $a['point_differential'];
            }
            // Then win percentage
            if ($a['win_percentage'] != $b['win_percentage']) {
                return $b['win_percentage'] <=> $a['win_percentage'];
            }
            // Then points scored
            if ($a['points_scored'] != $b['points_scored']) {
                return $b['points_scored'] <=> $a['points_scored'];
            }
            // Finally by name
            return strcmp($a['name'], $b['name']);
        });
        
        // Separate eligible players (8+ games) from others for position assignment
        $eligible_players = [];
        $other_players = [];
        $MIN_GAMES_REQUIRED = 8;
        
        foreach ($players as $player) {
            $is_no_games = (int)$player['no_games_played'] == 1;
            $wins = (int)($player['wins'] ?? 0);
            $losses = (int)($player['losses'] ?? 0);
            $total_matches = (int)($player['total_matches'] ?? ($wins + $losses));
            $meets_minimum = !$is_no_games && $total_matches >= $MIN_GAMES_REQUIRED;
            
            if ($meets_minimum) {
                $eligible_players[] = $player;
            } else {
                $other_players[] = $player;
            }
        }
        
        // Assign positions to eligible players
        foreach ($eligible_players as $index => &$player) {
            $player['display_position'] = $index + 1;
        }
        unset($player);
        
        // Combine back: eligible players first, then others
        $all_players = array_merge($eligible_players, $other_players);
        
        $division_standings[$division['id']] = [
            'division' => $division,
            'players' => $all_players
        ];
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
        .player-name a {
            color: #2E8B57;
            text-decoration: none;
            transition: color 0.2s;
        }
        .player-name a:hover {
            color: #1e6b3d;
            text-decoration: underline;
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
                        <small class="ms-3" style="font-size: 0.85rem; opacity: 0.9;">
                            <em>Standings sorted by point differential (minimum 8 games required)</em>
                        </small>
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
                                        <th class="stats-cell">Games</th>
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
                                    $MIN_GAMES_REQUIRED = 8;
                                    foreach ($division_data['players'] as $player): 
                                        $is_no_games = (int)$player['no_games_played'] == 1;
                                        $wins = (int)($player['wins'] ?? 0);
                                        $losses = (int)($player['losses'] ?? 0);
                                        $total_matches = (int)($player['total_matches'] ?? ($wins + $losses));
                                        $is_below_minimum = $is_no_games ? false : ($total_matches < $MIN_GAMES_REQUIRED);
                                        
                                        // Get position from pre-calculated display_position
                                        $display_position = $player['display_position'] ?? null;
                                    ?>
                                        <tr class="<?php echo $is_no_games ? 'table-light' : ($is_below_minimum ? 'table-warning' : ''); ?>">
                                            <td>
                                                <?php if ($is_no_games): ?>
                                                    <div class="position-badge position-other" style="background: linear-gradient(135deg, #e9ecef 0%, #ced4da 100%); color: #6c757d;">
                                                        -
                                                    </div>
                                                <?php elseif ($is_below_minimum): ?>
                                                    <div class="position-badge position-other" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); color: #856404;">
                                                        -
                                                    </div>
                                                <?php else: ?>
                                                    <div class="position-badge 
                                                        <?php 
                                                        if ($display_position == 1) echo 'position-1';
                                                        elseif ($display_position == 2) echo 'position-2';
                                                        elseif ($display_position == 3) echo 'position-3';
                                                        else echo 'position-other';
                                                        ?>">
                                                        <?php echo $display_position; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="player-name">
                                                    <?php if (!$is_no_games): ?>
                                                        <a href="pickleball_player_details.php?player_id=<?php echo $player['player_id']; ?>">
                                                            <?php echo htmlspecialchars($player['name']); ?>
                                                        </a>
                                                        <?php if ($is_below_minimum): ?>
                                                            <small class="text-warning">(< 8 games)</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($player['name']); ?>
                                                        <small class="text-muted">(No games played)</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="stats-cell">
                                                <?php if ($is_no_games): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <strong><?php echo $total_matches; ?></strong>
                                                    <?php if ($is_below_minimum): ?>
                                                        <small class="text-warning d-block" style="font-size: 0.7rem;">min: 8</small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
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