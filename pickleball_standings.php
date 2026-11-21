<?php
session_start();
include 'admin/db.php';

// Active season, or the archive season passed as ?season=
$current_season = getPickleballSeasonForRequest($conn);

// One ranking for the season.
$players = [];
$roster = pickleballRosterSql($conn);
if ($current_season) {
    $tie_counts = pickleballTieCounts($conn, (int)$current_season['id']);
    $stmt = $conn->prepare("
        SELECT
            p.id as player_id,
            p.name,
            COALESCE(lp.wins, 0) as wins,
            COALESCE(lp.losses, 0) as losses,
            COALESCE(lp.points_scored, 0) as points_scored,
            COALESCE(lp.points_against, 0) as points_against,
            COALESCE(lp.win_percentage, 0) as win_percentage,
            COALESCE(lp.point_differential, 0) as point_differential
        FROM pickleball_players p
        INNER JOIN pickleball_divisions d ON d.id = p.division_id AND d.season_id = ?
        LEFT JOIN pickleball_ladder_positions lp ON p.id = lp.player_id AND lp.season_id = ?
        WHERE p.is_active = 1 $roster
    ");
    $stmt->bind_param("ii", $current_season['id'], $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ties = (int)($tie_counts[(int)$row['player_id']] ?? 0);
        $row['ties'] = $ties;
        $row['total_matches'] = (int)$row['wins'] + (int)$row['losses'] + $ties;
        $players[] = $row;
    }
    $stmt->close();

    usort($players, function($a, $b) {
        $a_played = (int)$a['total_matches'] > 0;
        $b_played = (int)$b['total_matches'] > 0;
        if ($a_played !== $b_played) {
            return $a_played ? -1 : 1;
        }
        return pickleballRankCompare($a, $b);
    });

    $rank = 1;
    foreach ($players as &$player) {
        if ((int)$player['total_matches'] > 0) {
            $player['display_position'] = $rank;
            $rank++;
        }
    }
    unset($player);
}

// Tied games are not wins or losses, so the games column has to count them from the scores.
function pickleballTieCounts($conn, $seasonId) {
    $counts = [];
    $matchNumbers = [];
    for ($i = 1; $i <= 20; $i++) {
        $column = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match{$i}_team1_score'");
        if (!$column || $column->num_rows === 0) {
            break;
        }
        $matchNumbers[] = $i;
    }
    if (!$matchNumbers) {
        return $counts;
    }
    $unions = [];
    foreach ($matchNumbers as $matchNumber) {
        foreach (['team1_player1_id', 'team1_player2_id', 'team2_player1_id', 'team2_player2_id'] as $slot) {
            $unions[] = "
                SELECT pma.{$slot} AS player_id
                FROM pickleball_match_assignments pma
                INNER JOIN pickleball_games pg ON pg.id = pma.game_id
                WHERE pg.season_id = {$seasonId}
                  AND pg.status = 'completed'
                  AND pma.match_number = {$matchNumber}
                  AND pma.{$slot} IS NOT NULL
                  AND pg.match{$matchNumber}_team1_score IS NOT NULL
                  AND pg.match{$matchNumber}_team2_score IS NOT NULL
                  AND pg.match{$matchNumber}_team1_score = pg.match{$matchNumber}_team2_score
                  AND NOT (pg.match{$matchNumber}_team1_score = 0 AND pg.match{$matchNumber}_team2_score = 0)
            ";
        }
    }
    $sql = "SELECT player_id, COUNT(*) AS ties FROM (" . implode(" UNION ALL ", $unions) . ") tied GROUP BY player_id";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $counts[(int)$row['player_id']] = (int)$row['ties'];
        }
    }
    return $counts;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Standings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .standings-shell {
            width: min(100%, 1400px);
            margin-left: auto;
            margin-right: auto;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
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
            width: 100%;
            min-width: 36rem;
            table-layout: fixed;
        }
        .standings-table th,
        .standings-table td {
            padding: 0.4rem 0.15rem;
        }
        .standings-table th {
            background-color: #e9ecef;
            border: none;
            font-weight: 600;
            color: #495057;
            font-size: 0.78rem;
            line-height: 1.15;
            white-space: normal;
        }
        .standings-table td {
            border: none;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .standings-table .col-rank { width: 8%; }
        .standings-table .col-player { width: 22%; }
        .standings-table .col-games,
        .standings-table .col-wins,
        .standings-table .col-losses,
        .standings-table .col-ties { width: 7.5%; }
        .standings-table .col-pct { width: 10%; }
        .standings-table .col-for,
        .standings-table .col-against { width: 10%; }
        .standings-table .col-diff { width: 10%; }
        .position-badge {
            font-size: 0.95rem;
            font-weight: bold;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin: 0 auto;
        }
        .position-1 { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
        .position-2 { background: linear-gradient(135deg, #C0C0C0 0%, #A0A0A0 100%); }
        .position-3 { background: linear-gradient(135deg, #CD7F32 0%, #B8860B 100%); }
        .position-other { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        .player-name {
            font-weight: 600;
            color: #2E8B57;
            overflow-wrap: anywhere;
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
            white-space: nowrap;
        }
        .stats-cell .badge {
            font-size: 0.8rem;
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
        <div class="standings-shell">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏆 Pickleball Standings</h1>
                    <p class="lead mb-0">
                        <?php if ($current_season): ?>
                            <?php echo htmlspecialchars($current_season['name']); ?> Season
                        <?php else: ?>
                            Current Season
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="standings-shell mb-4">
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

    <div class="standings-shell">
        <?php echo pickleballArchiveBanner($current_season); ?>
        <?php if (empty($players)): ?>
            <div class="alert alert-info">
                <h4>No Standings Available</h4>
                <p>No standings found for the current season. Players need to be assigned to divisions and games need to be completed.</p>
            </div>
        <?php else: ?>
                <div class="division-card">
                    <div class="division-header">
                        Season ranking
                        <span class="badge bg-light text-dark ms-2">
                            <?php echo count($players); ?> Player<?php echo count($players) != 1 ? 's' : ''; ?>
                        </span>
                        <small class="ms-3" style="font-size: 0.85rem; opacity: 0.9;">
                            <em>Sorted by win percentage, then point differential per game. Games include ties.</em>
                        </small>
                    </div>
                        <div class="table-responsive">
                            <table class="table standings-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="col-rank">#</th>
                                        <th class="col-player">Player</th>
                                        <th class="stats-cell col-games">Games</th>
                                        <th class="stats-cell col-wins">Wins</th>
                                        <th class="stats-cell col-losses">Losses</th>
                                        <th class="stats-cell col-ties">Ties</th>
                                        <th class="stats-cell col-pct">Win %</th>
                                        <th class="stats-cell col-for">Points For</th>
                                        <th class="stats-cell col-against">Points Against</th>
                                        <th class="stats-cell col-diff">Point Diff</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($players as $player):
                                        $total_matches = (int)$player['total_matches'];
                                        $is_no_games = $total_matches === 0;
                                        $display_position = $player['display_position'] ?? null;
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
                                                    <span class="badge bg-secondary"><?php echo (int)$player['ties']; ?></span>
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
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                </div>
        <?php endif; ?>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>