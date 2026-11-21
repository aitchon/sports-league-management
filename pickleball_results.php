<?php
include 'admin/db.php';

function resultsScoreValue($value) {
    if ($value === null || $value === '') {
        return null;
    }
    return (int)$value;
}

function resultsScoreText($left, $right) {
    if ($left === null && $right !== null && $right > 0) {
        $left = 0;
    }
    if ($right === null && $left !== null && $left > 0) {
        $right = 0;
    }
    if ($left === null || $right === null || ($left === 0 && $right === 0)) {
        return '';
    }
    return $left . '–' . $right;
}

function resultsWinner($left, $right) {
    if (resultsScoreText($left, $right) === '') {
        return '';
    }
    if ($left === $right) {
        return 'tie';
    }
    return $left > $right ? 'left' : 'right';
}

function resultsPlayerHtml($id, $name) {
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    $label = htmlspecialchars($name);
    if ((int)$id > 0) {
        return '<a href="pickleball_player_details.php?player_id=' . (int)$id . '" class="player-link">' . $label . '</a>';
    }
    return $label;
}

function resultsSideHtml(array $side) {
    $parts = array_filter([
        resultsPlayerHtml($side[0], $side[1]),
        resultsPlayerHtml($side[2], $side[3]),
    ]);
    if (!$parts) {
        return 'TBD';
    }
    return implode(' <span class="pairing-amp">&amp;</span> ', $parts);
}

function resultsMatchesForGame(array $game, array $pairingsByGame) {
    $assigned = $pairingsByGame[(int)$game['id']] ?? [];
    if ($assigned) {
        $matches = [];
        foreach ($assigned as $row) {
            $number = (int)$row['match_number'];
            $matches[] = [
                'number' => $number,
                'left' => [(int)$row['team1_player1_id'], $row['team1_p1_name'], (int)$row['team1_player2_id'], $row['team1_p2_name']],
                'right' => [(int)$row['team2_player1_id'], $row['team2_p1_name'], (int)$row['team2_player2_id'], $row['team2_p2_name']],
                'score_left' => resultsScoreValue($game["match{$number}_team1_score"] ?? null),
                'score_right' => resultsScoreValue($game["match{$number}_team2_score"] ?? null),
            ];
        }
        return $matches;
    }

    $players = [];
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($game["player{$i}_name"])) {
            $players[] = [(int)$game["player{$i}_id"], $game["player{$i}_name"]];
        }
    }
    if (count($players) < 4) {
        return [];
    }
    return [[
        'number' => 1,
        'left' => [$players[0][0], $players[0][1], $players[1][0], $players[1][1]],
        'right' => [$players[2][0], $players[2][1], $players[3][0], $players[3][1]],
        'score_left' => resultsScoreValue($game['match1_team1_score'] ?? null),
        'score_right' => resultsScoreValue($game['match1_team2_score'] ?? null),
    ]];
}

function resultsCourtNumber($location) {
    return preg_match('/(\d+)/', (string)$location, $match) ? (int)$match[1] : PHP_INT_MAX;
}

$current_season = getPickleballSeasonForRequest($conn);
$today = date('Y-m-d');
$all_weeks = [];
$weeks = [];
$selected_week = '';
$week_number = 0;
$games_by_time = [];
$pairings_by_game = [];
$match_scored = 0;

if ($current_season) {
    $dateStmt = $conn->prepare("
        SELECT DISTINCT DATE(date) AS game_date
        FROM pickleball_games
        WHERE season_id = ?
        ORDER BY game_date
    ");
    $dateStmt->bind_param("i", $current_season['id']);
    $dateStmt->execute();
    $dateResult = $dateStmt->get_result();
    while ($row = $dateResult->fetch_assoc()) {
        $all_weeks[] = $row['game_date'];
    }
    $dateStmt->close();

    $scoredWeeks = [];
    $scoredStmt = $conn->prepare("
        SELECT DISTINCT DATE(date) AS game_date
        FROM pickleball_games
        WHERE season_id = ?
          AND (
            COALESCE(match1_team1_score, 0) + COALESCE(match1_team2_score, 0)
            + COALESCE(match2_team1_score, 0) + COALESCE(match2_team2_score, 0)
            + COALESCE(match3_team1_score, 0) + COALESCE(match3_team2_score, 0)
            + COALESCE(match4_team1_score, 0) + COALESCE(match4_team2_score, 0)
            + COALESCE(match5_team1_score, 0) + COALESCE(match5_team2_score, 0)
            + COALESCE(match6_team1_score, 0) + COALESCE(match6_team2_score, 0)
            + COALESCE(match7_team1_score, 0) + COALESCE(match7_team2_score, 0)
          ) > 0
    ");
    $scoredStmt->bind_param("i", $current_season['id']);
    $scoredStmt->execute();
    $scoredResult = $scoredStmt->get_result();
    while ($row = $scoredResult->fetch_assoc()) {
        $scoredWeeks[$row['game_date']] = true;
    }
    $scoredStmt->close();

    foreach ($all_weeks as $week) {
        if ($week <= $today || isset($scoredWeeks[$week])) {
            $weeks[] = $week;
        }
    }

    $requested = isset($_GET['week']) ? $_GET['week'] : '';
    if (in_array($requested, $weeks, true)) {
        $selected_week = $requested;
    } else {
        foreach ($weeks as $week) {
            if ($week <= $today) {
                $selected_week = $week;
            }
        }
        if ($selected_week === '' && $weeks) {
            $selected_week = $weeks[0];
        }
    }
    $weekIndex = array_search($selected_week, $all_weeks, true);
    $week_number = $weekIndex === false ? 0 : $weekIndex + 1;

    if ($selected_week !== '') {
        $stmt = $conn->prepare("
            SELECT pg.id, DATE(pg.date) AS game_date, TIME(pg.date) AS start_time,
                   pg.location, pg.status,
                   p1.name AS player1_name, p2.name AS player2_name,
                   p3.name AS player3_name, p4.name AS player4_name,
                   pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id,
                   pg.match1_team1_score, pg.match1_team2_score,
                   pg.match2_team1_score, pg.match2_team2_score,
                   pg.match3_team1_score, pg.match3_team2_score,
                   pg.match4_team1_score, pg.match4_team2_score,
                   pg.match5_team1_score, pg.match5_team2_score,
                   pg.match6_team1_score, pg.match6_team2_score,
                   pg.match7_team1_score, pg.match7_team2_score
            FROM pickleball_games pg
            LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
            LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
            LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
            LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
            WHERE pg.season_id = ? AND DATE(pg.date) = ?
            ORDER BY start_time, pg.id
        ");
        $stmt->bind_param("is", $current_season['id'], $selected_week);
        $stmt->execute();
        $result = $stmt->get_result();
        $gameIds = [];
        while ($game = $result->fetch_assoc()) {
            $games_by_time[$game['start_time']][] = $game;
            $gameIds[] = (int)$game['id'];
        }
        $stmt->close();

        if ($gameIds) {
            $idList = implode(',', $gameIds);
            $pairingResult = $conn->query("
                SELECT pma.game_id, pma.match_number,
                       pma.team1_player1_id, pma.team1_player2_id,
                       pma.team2_player1_id, pma.team2_player2_id,
                       t1a.name AS team1_p1_name, t1b.name AS team1_p2_name,
                       t2a.name AS team2_p1_name, t2b.name AS team2_p2_name
                FROM pickleball_match_assignments pma
                LEFT JOIN pickleball_players t1a ON pma.team1_player1_id = t1a.id
                LEFT JOIN pickleball_players t1b ON pma.team1_player2_id = t1b.id
                LEFT JOIN pickleball_players t2a ON pma.team2_player1_id = t2a.id
                LEFT JOIN pickleball_players t2b ON pma.team2_player2_id = t2b.id
                WHERE pma.game_id IN ($idList)
                ORDER BY pma.game_id, pma.match_number
            ");
            if ($pairingResult) {
                while ($row = $pairingResult->fetch_assoc()) {
                    $pairings_by_game[(int)$row['game_id']][] = $row;
                }
            }
        }

        foreach ($games_by_time as $start_time => &$games) {
            usort($games, function ($a, $b) {
                $courtCompare = resultsCourtNumber($a['location']) <=> resultsCourtNumber($b['location']);
                if ($courtCompare !== 0) {
                    return $courtCompare;
                }
                $locationCompare = strcmp((string)$a['location'], (string)$b['location']);
                if ($locationCompare !== 0) {
                    return $locationCompare;
                }
                return (int)$a['id'] <=> (int)$b['id'];
            });
            $kept = [];
            foreach ($games as $game) {
                $scored = [];
                foreach (resultsMatchesForGame($game, $pairings_by_game) as $match) {
                    if (resultsScoreText($match['score_left'], $match['score_right']) === '') {
                        continue;
                    }
                    $scored[] = $match;
                }
                if (!$scored) {
                    continue;
                }
                $game['scored_matches'] = $scored;
                $match_scored += count($scored);
                $kept[] = $game;
            }
            if ($kept) {
                $games = $kept;
            } else {
                unset($games_by_time[$start_time]);
            }
        }
        unset($games);
        ksort($games_by_time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
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
            justify-content: space-between;
            gap: 0.5rem 1rem;
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
        }
        .game-card:last-child {
            border-radius: 0 0 8px 8px;
        }
        .player-link {
            color: inherit;
            text-decoration: none;
        }
        .player-link:hover {
            color: #2E8B57;
            text-decoration: underline;
        }
        .pairing + .pairing {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #eee;
        }
        .pairing-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.85rem;
        }
        .pairing-side {
            flex: 1 1 12rem;
            min-width: 0;
        }
        .pairing-side.is-winner {
            font-weight: 700;
        }
        .pairing-score {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            min-width: 4.5rem;
            text-align: center;
        }
        .score-win {
            color: #157347;
        }
        .score-pending {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .pairing-match {
            width: 100%;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6c757d;
        }
        .pairing-amp {
            color: #6c757d;
        }
        @media (max-width: 768px) {
            .pairing-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .pairing-score {
                text-align: left;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="text-center">
                        <h1 class="display-4 mb-0">🏓 Pickleball Results</h1>
                        <p class="mb-0">Scores for each week</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mb-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_standings.php', $current_season)); ?>" class="btn btn-success">🏆 Standings</a>
                                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_schedule.php', $current_season)); ?>" class="btn btn-info">📅 Schedule</a>
                                <a href="<?php echo htmlspecialchars(pickleballPublicHref('pickleball_results.php', $current_season)); ?>" class="btn btn-primary" aria-current="page">📊 Results</a>
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
                <div class="alert alert-warning">
                    <h4>No Active Pickleball Season</h4>
                    <p>There is currently no active pickleball season.</p>
                </div>
            <?php elseif (!$weeks): ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">No games yet</h5>
                    <p class="text-muted">Results will show here after games are scheduled.</p>
                </div>
            <?php else: ?>
                <form method="get" class="card mb-4">
                    <div class="card-body">
                        <?php if (empty($current_season['is_active'])): ?>
                            <input type="hidden" name="season" value="<?php echo (int)$current_season['id']; ?>">
                        <?php endif; ?>
                        <label for="week" class="form-label fw-bold">Week</label>
                        <select class="form-select" id="week" name="week" onchange="this.form.submit()">
                            <?php foreach ($weeks as $week): ?>
                                <?php $option_number = array_search($week, $all_weeks, true) + 1; ?>
                                <option value="<?php echo htmlspecialchars($week); ?>" <?php echo $week === $selected_week ? 'selected' : ''; ?>>
                                    Week <?php echo (int)$option_number; ?> — <?php echo date('l, F j, Y', strtotime($week)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <div class="schedule-group mb-4">
                    <h4 class="date-header">
                        <span>Week <?php echo (int)$week_number; ?> · <?php echo date('l, F j, Y', strtotime($selected_week)); ?></span>
                        <span class="badge bg-light text-dark">
                            <?php if ($match_scored === 0): ?>
                                No results
                            <?php else: ?>
                                <?php echo (int)$match_scored; ?> result<?php echo $match_scored === 1 ? '' : 's'; ?>
                            <?php endif; ?>
                        </span>
                    </h4>

                    <?php if (!$games_by_time): ?>
                        <div class="card">
                            <div class="p-3 text-muted">No results for this week.</div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($games_by_time as $start_time => $games): ?>
                        <div class="card">
                            <div class="time-header">
                                <?php echo date('g:i A', strtotime($start_time)); ?>
                            </div>
                            <?php foreach ($games as $game): ?>
                                <?php $matches = $game['scored_matches']; ?>
                                <div class="game-card p-3">
                                    <div class="mb-2">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($game['location'] ?: 'Court TBD'); ?></span>
                                        <?php if ($game['status'] === 'forfeit'): ?>
                                            <span class="badge bg-warning text-dark">Forfeit</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php foreach ($matches as $match): ?>
                                        <?php
                                        $winner = resultsWinner($match['score_left'], $match['score_right']);
                                        $score_text = resultsScoreText($match['score_left'], $match['score_right']);
                                        ?>
                                        <div class="pairing">
                                            <?php if (count($matches) > 1): ?>
                                                <div class="pairing-match">Match <?php echo (int)$match['number']; ?></div>
                                            <?php endif; ?>
                                            <div class="pairing-row">
                                                <div class="pairing-side<?php echo $winner === 'left' ? ' is-winner' : ''; ?>">
                                                    <?php echo resultsSideHtml($match['left']); ?>
                                                </div>
                                                <div class="pairing-score">
                                                    <?php if ($score_text === ''): ?>
                                                        <span class="score-pending">No score yet</span>
                                                    <?php else: ?>
                                                        <span class="<?php echo $winner === 'left' ? 'score-win' : ''; ?>"><?php echo (int)$match['score_left']; ?></span>
                                                        <span class="pairing-amp">–</span>
                                                        <span class="<?php echo $winner === 'right' ? 'score-win' : ''; ?>"><?php echo (int)$match['score_right']; ?></span>
                                                        <?php if ($winner === 'tie'): ?>
                                                            <span class="score-pending">Tie</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="pairing-side<?php echo $winner === 'right' ? ' is-winner' : ''; ?>">
                                                    <?php echo resultsSideHtml($match['right']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
