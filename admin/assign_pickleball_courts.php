<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
require_once 'pickleball_court_planner.php';

$message = "";
if (!empty($_SESSION['court_flash'])) {
    $message = $_SESSION['court_flash'];
    unset($_SESSION['court_flash']);
}
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

$fridays = [];
if ($current_season) {
    $cursor = new DateTime($current_season['start_date']);
    $end = new DateTime($current_season['end_date']);
    while ((int)$cursor->format('N') !== 5 && $cursor <= $end) {
        $cursor->modify('+1 day');
    }
    for ($day = clone $cursor; $day <= $end; $day->modify('+7 days')) {
        $monthDay = $day->format('m-d');
        if ($monthDay === '12-25' || $monthDay === '01-01') {
            continue;
        }
        $fridays[] = $day->format('Y-m-d');
    }
}

$today = (new DateTime('today'))->format('Y-m-d');
$play_date = $_POST['play_date'] ?? ($_GET['date'] ?? '');
if (!in_array($play_date, $fridays, true)) {
    $play_date = '';
    foreach ($fridays as $friday) {
        if ($friday >= $today) {
            $play_date = $friday;
            break;
        }
    }
    if ($play_date === '' && $fridays) {
        $play_date = $fridays[count($fridays) - 1];
    }
}

$season_divisions = [];
if ($current_season) {
    $stmt = $conn->prepare("SELECT id, name FROM pickleball_divisions WHERE season_id = ? AND is_active = 1 ORDER BY min_rating, name");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $season_divisions[] = $row;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_substitute']) && $current_season && $play_date) {
    $subName = trim($_POST['substitute_name'] ?? '');
    $subDivision = (int)($_POST['substitute_division'] ?? 0);
    $divisionOk = false;
    foreach ($season_divisions as $division) {
        if ((int)$division['id'] === $subDivision) {
            $divisionOk = true;
            break;
        }
    }
    if ($subName === '') {
        $message = '<div class="alert alert-danger">Enter a name for the substitute.</div>';
    } elseif (!$divisionOk) {
        $message = '<div class="alert alert-danger">Choose a division for the substitute.</div>';
    } elseif (!pickleballHasSubstituteColumn($conn)) {
        $message = '<div class="alert alert-danger">Substitutes need the is_substitute column. Run the new statement in admin/pickleball_court_assignment.sql first.</div>';
    } else {
        $dup = $conn->prepare("SELECT id, is_substitute FROM pickleball_players WHERE season_id = ? AND LOWER(name) = LOWER(?) LIMIT 1");
        $dup->bind_param("is", $current_season['id'], $subName);
        $dup->execute();
        $existing = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($existing && !empty($existing['is_substitute'])) {
            $message = '<div class="alert alert-warning">' . htmlspecialchars($subName) . ' is already a substitute.</div>';
        } elseif ($existing) {
            $message = '<div class="alert alert-danger">' . htmlspecialchars($subName) . ' is already registered for this season.</div>';
        } else {
            $insert = $conn->prepare("INSERT INTO pickleball_players (name, current_rating, division_id, season_id, is_active, is_substitute) VALUES (?, 2.50, ?, ?, 1, 1)");
            $insert->bind_param("sii", $subName, $subDivision, $current_season['id']);
            if ($insert->execute()) {
                $_SESSION['court_flash'] = '<div class="alert alert-success">Added ' . htmlspecialchars($subName) . ' as a substitute. Check them in to take the spot of a regular who cannot play.</div>';
                header('Location: assign_pickleball_courts.php?date=' . urlencode($play_date));
                exit();
            }
            $message = '<div class="alert alert-danger">Could not add the substitute. ' . htmlspecialchars($insert->error) . '</div>';
            $insert->close();
        }
    }
}

$players = [];
if ($current_season) {
    $substituteColumn = pickleballHasSubstituteColumn($conn) ? 'p.is_substitute' : '0 AS is_substitute';
    $substituteOrder = pickleballHasSubstituteColumn($conn) ? 'p.is_substitute, ' : '';
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.division_id, $substituteColumn, d.name AS division_name, d.min_rating,
               (SELECT COUNT(*) FROM pickleball_games g
                WHERE g.season_id = p.season_id AND g.status = 'completed'
                  AND p.id IN (g.player1_id, g.player2_id, g.player3_id, g.player4_id, g.player5_id, g.player6_id, g.player7_id)
               ) AS games_played
        FROM pickleball_players p
        JOIN pickleball_divisions d ON d.id = p.division_id
        WHERE p.season_id = ? AND p.is_active = 1
        ORDER BY d.min_rating, {$substituteOrder}games_played, p.name
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    $stmt->close();
}

$divisions = [];
foreach ($players as $player) {
    $divisionId = (int)$player['division_id'];
    if (!isset($divisions[$divisionId])) {
        $divisions[$divisionId] = [
            'division_id' => $divisionId,
            'name' => $player['division_name'],
            'regulars' => [],
            'substitutes' => [],
            'players' => [],
        ];
    }
    if (!empty($player['is_substitute'])) {
        $divisions[$divisionId]['substitutes'][] = $player;
    } else {
        $divisions[$divisionId]['regulars'][] = $player;
    }
}
foreach ($divisions as $divisionId => $division) {
    $divisions[$divisionId]['players'] = array_merge($division['regulars'], $division['substitutes']);
}

$saved_presence = [];
if ($current_season && $play_date) {
    $stmt = $conn->prepare("SELECT player_id, is_present FROM pickleball_session_players WHERE season_id = ? AND play_date = ?");
    $stmt->bind_param("is", $current_season['id'], $play_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $saved_presence[(int)$row['player_id']] = (int)$row['is_present'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign']) && $current_season && $play_date) {
    $courtNumbers = parseCourtNumbers($_POST['courts'] ?? '');
    if (!$courtNumbers) {
        $message = '<div class="alert alert-danger">Enter the court numbers for this Friday, such as 3-7 or 1, 2, 4.</div>';
    } else {
    $presentIds = [];
    foreach ($_POST['present'] ?? [] as $playerId) {
        $presentIds[(int)$playerId] = true;
    }
    foreach ($divisions as $division) {
        $openSpots = 0;
        foreach ($division['regulars'] as $player) {
            if (!isset($presentIds[(int)$player['id']])) {
                $openSpots++;
            }
        }
        foreach ($division['substitutes'] as $player) {
            $playerId = (int)$player['id'];
            if (!isset($presentIds[$playerId])) {
                continue;
            }
            if ($openSpots <= 0) {
                unset($presentIds[$playerId]);
                continue;
            }
            $openSpots--;
        }
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        $delete = $conn->prepare("DELETE FROM pickleball_session_players WHERE season_id = ? AND play_date = ?");
        $delete->bind_param("is", $current_season['id'], $play_date);
        $delete->execute();
        $delete->close();

        $insertPresence = $conn->prepare("INSERT INTO pickleball_session_players (season_id, play_date, player_id, is_present) VALUES (?, ?, ?, ?)");
        foreach ($players as $player) {
            $playerId = (int)$player['id'];
            $isPresent = isset($presentIds[$playerId]) ? 1 : 0;
            $insertPresence->bind_param("isii", $current_season['id'], $play_date, $playerId, $isPresent);
            $insertPresence->execute();
            $saved_presence[$playerId] = $isPresent;
        }
        $insertPresence->close();

        $playing = [];
        foreach ($divisions as $divisionId => $division) {
            $ids = [];
            $seasonGames = [];
            $openSpots = 0;
            $busiestSeason = 0;
            foreach ($division['regulars'] as $player) {
                $playerId = (int)$player['id'];
                $gamesPlayed = (int)$player['games_played'];
                if ($gamesPlayed > $busiestSeason) {
                    $busiestSeason = $gamesPlayed;
                }
                if (!empty($saved_presence[$playerId])) {
                    $ids[] = $playerId;
                    $seasonGames[$playerId] = $gamesPlayed;
                } else {
                    $openSpots++;
                }
            }
            foreach ($division['substitutes'] as $player) {
                $playerId = (int)$player['id'];
                if ($openSpots <= 0 || empty($saved_presence[$playerId])) {
                    $saved_presence[$playerId] = 0;
                    continue;
                }
                $ids[] = $playerId;
                $seasonGames[$playerId] = $busiestSeason + 1;
                $openSpots--;
            }
            $playing[] = [
                'division_id' => $divisionId,
                'name' => $division['name'],
                'player_ids' => $ids,
                'season_games' => $seasonGames,
            ];
        }
        $plan = buildNightPlan($playing, $courtNumbers);

        $existing = $conn->prepare("SELECT id FROM pickleball_games WHERE season_id = ? AND DATE(date) = ? AND status = 'scheduled'");
        $existing->bind_param("is", $current_season['id'], $play_date);
        $existing->execute();
        $gameIds = [];
        $existingResult = $existing->get_result();
        while ($row = $existingResult->fetch_assoc()) {
            $gameIds[] = (int)$row['id'];
        }
        $existing->close();
        if ($gameIds) {
            $idList = implode(',', $gameIds);
            $conn->query("UPDATE pickleball_challenges SET game_id = NULL WHERE game_id IN ($idList)");
            $conn->query("DELETE FROM pickleball_ladder_history WHERE game_id IN ($idList)");
            $conn->query("DELETE FROM pickleball_scores WHERE game_id IN ($idList)");
            $conn->query("DELETE FROM pickleball_player_stats WHERE game_id IN ($idList)");
            $conn->query("DELETE FROM pickleball_games WHERE id IN ($idList)");
        }

        $kept = $conn->prepare("SELECT date, location FROM pickleball_games WHERE season_id = ? AND DATE(date) = ? AND status <> 'scheduled'");
        $kept->bind_param("is", $current_season['id'], $play_date);
        $kept->execute();
        $occupied = [];
        $keptResult = $kept->get_result();
        while ($row = $keptResult->fetch_assoc()) {
            $occupied[$row['date'] . '|' . $row['location']] = true;
        }
        $kept->close();

        $insertGame = $conn->prepare("
            INSERT INTO pickleball_games
                (player1_id, player2_id, player3_id, player4_id, date, location, status, game_type, is_ladder_match, is_challenge, season_id, division_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'scheduled', 'doubles', 1, 0, ?, ?, ?)
        ");
        $insertMatch = $conn->prepare("
            INSERT INTO pickleball_match_assignments
                (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
            VALUES (?, 1, ?, ?, ?, ?)
        ");
        $gameCount = 0;
        foreach ($plan as $round) {
            foreach ($round['courts'] as $court) {
                $when = $play_date . ' ' . $round['time'];
                if (isset($occupied[$when . '|' . $court['court']])) {
                    continue;
                }
                $minutes = (int)($round['minutes'] ?? 15);
                $notes = $court['repeat_partner'] ? 'Repeat partner' : ($minutes . '-minute game');
                $p1 = $court['players'][0];
                $p2 = $court['players'][1];
                $p3 = $court['players'][2];
                $p4 = $court['players'][3];
                $divisionId = $court['division_id'];
                $location = $court['court'];
                $insertGame->bind_param("iiiississ", $p1, $p2, $p3, $p4, $when, $location, $current_season['id'], $divisionId, $notes);
                $insertGame->execute();
                $gameId = $conn->insert_id;
                $insertMatch->bind_param("iiiii", $gameId, $p1, $p2, $p3, $p4);
                $insertMatch->execute();
                $gameCount++;
            }
        }
        $insertGame->close();
        $insertMatch->close();
        $conn->commit();
        $sessionMinutes = (int)($plan[0]['minutes'] ?? 15);
        $message = '<div class="alert alert-success">Assigned ' . $gameCount . ' games for ' . date('l, F j, Y', strtotime($play_date)) . '. ' . $sessionMinutes . '-minute games on courts ' . formatCourtNumbers($courtNumbers) . '.</div>';
    } catch (Throwable $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">Could not save the court assignment. ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    }
}

$names = [];
foreach ($players as $player) {
    $names[(int)$player['id']] = $player['name'];
}

$saved_rounds = [];
if ($current_season && $play_date) {
    $stmt = $conn->prepare("
        SELECT pg.id, pg.date, pg.location, pg.division_id, pg.notes,
               pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id,
               d.name AS division_name,
               a.team1_player1_id, a.team1_player2_id, a.team2_player1_id, a.team2_player2_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_divisions d ON d.id = pg.division_id
        LEFT JOIN pickleball_match_assignments a ON a.game_id = pg.id AND a.match_number = 1
        WHERE pg.season_id = ? AND DATE(pg.date) = ? AND pg.player1_id IS NOT NULL
        ORDER BY pg.date, pg.location
    ");
    $stmt->bind_param("is", $current_season['id'], $play_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $saved_rounds[$row['date']][] = $row;
    }
    $stmt->close();
}

$savedLocations = [];
foreach ($saved_rounds as $courts) {
    foreach ($courts as $court) {
        $savedLocations[] = $court['location'];
    }
}
$courtField = formatCourtNumbers(courtNumbersForDate($play_date, courtsFromLocations($savedLocations)));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $postedCourts = trim($_POST['courts'] ?? '');
    if ($postedCourts !== '') {
        $parsedCourts = parseCourtNumbers($postedCourts);
        $courtField = $parsedCourts ? formatCourtNumbers($parsedCourts) : $postedCourts;
    }
}

function courtSideIds(array $court) {
    $leftA = (int)($court['team1_player1_id'] ?: $court['player1_id']);
    $leftB = (int)($court['team1_player2_id'] ?: $court['player2_id']);
    $rightA = (int)($court['team2_player1_id'] ?: $court['player3_id']);
    $rightB = (int)($court['team2_player2_id'] ?: $court['player4_id']);
    return [$leftA, $leftB, $rightA, $rightB];
}

function courtSideLabel($idA, $idB, array $names) {
    $left = $idA > 0 ? ($names[$idA] ?? 'Open') : 'Open';
    $right = $idB > 0 ? ($names[$idB] ?? 'Open') : 'Open';
    return $left . ' & ' . $right;
}

function savedRoundMinutes(array $savedRounds) {
    $times = array_keys($savedRounds);
    sort($times);
    $gaps = [];
    for ($i = 1; $i < count($times); $i++) {
        $gap = (int)round((strtotime($times[$i]) - strtotime($times[$i - 1])) / 60);
        if ($gap > 0) {
            $gaps[] = $gap;
        }
    }
    if ($gaps && min($gaps) === 20 && max($gaps) === 20) {
        return 20;
    }
    foreach ($savedRounds as $courts) {
        foreach ($courts as $court) {
            if (strpos((string)($court['notes'] ?? ''), '20-minute') !== false) {
                return 20;
            }
        }
    }
    return 15;
}

function assignmentPlayerTotals(array $savedRounds, array $divisions, array $savedPresence) {
    $roundMinutes = savedRoundMinutes($savedRounds);
    $played = [];
    $matches = [];
    foreach ($savedRounds as $when => $courts) {
        foreach ($courts as $court) {
            foreach (courtSideIds($court) as $playerId) {
                if ($playerId <= 0) {
                    continue;
                }
                $played[$playerId][$when] = true;
                $matches[$playerId] = ($matches[$playerId] ?? 0) + 1;
            }
        }
    }
    $rows = [];
    $seen = [];
    foreach ($divisions as $division) {
        foreach ($division['players'] as $player) {
            $playerId = (int)$player['id'];
            if (!playerIsChecked($player, $savedPresence) && empty($matches[$playerId])) {
                continue;
            }
            $waitRounds = 0;
            foreach (array_keys($savedRounds) as $when) {
                if (empty($played[$playerId][$when])) {
                    $waitRounds++;
                }
            }
            $rows[] = [
                'name' => $player['name'],
                'division' => $division['name'],
                'matches' => $matches[$playerId] ?? 0,
                'wait_minutes' => $waitRounds * $roundMinutes,
            ];
            $seen[$playerId] = true;
        }
    }
    foreach ($matches as $playerId => $count) {
        if (!empty($seen[$playerId])) {
            continue;
        }
        $waitRounds = 0;
        foreach (array_keys($savedRounds) as $when) {
            if (empty($played[$playerId][$when])) {
                $waitRounds++;
            }
        }
        $rows[] = [
            'name' => 'Player ' . $playerId,
            'division' => '',
            'matches' => $count,
            'wait_minutes' => $waitRounds * $roundMinutes,
        ];
    }
    usort($rows, function ($a, $b) {
        $division = strcasecmp($a['division'], $b['division']);
        if ($division !== 0) {
            return $division;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    return $rows;
}

function playerIsChecked(array $player, array $savedPresence) {
    $playerId = (int)$player['id'];
    if (array_key_exists($playerId, $savedPresence)) {
        return (int)$savedPresence[$playerId] === 1;
    }
    return empty($player['is_substitute']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $play_date ? 'Courts ' . date('M j, Y', strtotime($play_date)) : 'Assign Pickleball Courts'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header { background: linear-gradient(135deg, #4CAF50, #66BB6A); color: white; }
        .player-row { display: flex; justify-content: space-between; gap: 0.5rem; }
        .sheet-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sheet-table th, .sheet-table td { border: 1px solid #bbb; padding: 0.45rem 0.55rem; vertical-align: middle; }
        .sheet-table th { background: #f4f4f4; }
        .sheet-table th:nth-child(1), .sheet-table td:nth-child(1) { width: 5.5rem; }
        .sheet-table th:nth-child(2), .sheet-table td:nth-child(2) { width: 6.5rem; }
        .sheet-table.totals { table-layout: auto; margin-bottom: 1.5rem; }
        .sheet-table.totals th:nth-child(1), .sheet-table.totals td:nth-child(1),
        .sheet-table.totals th:nth-child(2), .sheet-table.totals td:nth-child(2) { width: auto; }
        .sheet-table.totals th:nth-child(3), .sheet-table.totals td:nth-child(3),
        .sheet-table.totals th:nth-child(4), .sheet-table.totals td:nth-child(4) { width: 6.5rem; text-align: right; }
        .match { display: flex; align-items: stretch; gap: 0.75rem; }
        .match .side { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: flex-end; }
        .match .side.right { text-align: right; }
        .match .team { font-size: 1rem; line-height: 1.3; }
        .match .vs { align-self: center; font-weight: 700; }
        .score-caption { font-size: 0.75rem; color: #333; margin-top: 0.35rem; }
        .score-line { border-bottom: 2px dashed #111; height: 2.6rem; }
        .sheet-table tbody tr.sheet-game td { border-bottom: 2px dashed #222; padding-top: 0.7rem; padding-bottom: 0.55rem; }
        .sheet-sitting td { color: #444; background: #fafafa; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; font-size: 12pt; }
            .container, .container-fluid { max-width: none; padding: 0 !important; }
            .sheet-table th, .sheet-table td { border-color: #000; }
            .sheet-table th { background: #fff; }
            .sheet-sitting td { background: #fff; }
            .match .team { font-size: 12pt; }
            .score-line { height: 0.62in; border-bottom: 1.75pt dashed #000; }
            .sheet-table tbody tr.sheet-game td { border-bottom: 1.75pt dashed #000; }
            body.print-totals .court-sheet { display: none !important; }
            body.print-sheet .totals-sheet { display: none !important; }
            tr { break-inside: avoid; }
            @page { margin: 0.45in; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4 no-print">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">Assign Courts</h1>
                            <p class="mb-0">7:00 to 9:00 PM. 20-minute games when 24 or fewer are playing, 15-minute games otherwise.</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container pb-5">
            <div class="no-print"><?php echo $message; ?></div>
            <?php if (!$current_season): ?>
                <div class="alert alert-warning">No active pickleball season.</div>
            <?php elseif (!$fridays): ?>
                <div class="alert alert-warning">This season has no Friday play dates.</div>
            <?php else: ?>
                <form method="get" class="row g-3 align-items-end mb-4 no-print">
                    <div class="col-md-4">
                        <label for="date" class="form-label">Play date</label>
                        <select class="form-select" id="date" name="date" onchange="this.form.submit()">
                            <?php foreach ($fridays as $friday): ?>
                                <option value="<?php echo $friday; ?>" <?php echo $friday === $play_date ? 'selected' : ''; ?>>
                                    <?php echo date('l, F j, Y', strtotime($friday)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <form method="post" class="card mb-4 no-print">
                    <div class="card-header bg-success text-white">
                        <strong>Add a substitute</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">A substitute takes the spot of a regular in one division who cannot play that week. They stay off the court until a regular is unchecked.</p>
                        <input type="hidden" name="play_date" value="<?php echo htmlspecialchars($play_date); ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="substitute_name" class="form-label">Name</label>
                                <input class="form-control" id="substitute_name" name="substitute_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="substitute_division" class="form-label">Division</label>
                                <select class="form-select" id="substitute_division" name="substitute_division" required>
                                    <option value="">Choose a division</option>
                                    <?php foreach ($season_divisions as $division): ?>
                                        <option value="<?php echo (int)$division['id']; ?>"><?php echo htmlspecialchars($division['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="add_substitute" class="btn btn-success">Add substitute</button>
                            </div>
                        </div>
                    </div>
                </form>

                <form method="post" class="no-print">
                    <input type="hidden" name="play_date" value="<?php echo htmlspecialchars($play_date); ?>">
                    <div class="row">
                        <?php foreach ($divisions as $division): ?>
                            <?php
                            $checkedCount = 0;
                            foreach ($division['regulars'] as $player) {
                                if (playerIsChecked($player, $saved_presence)) {
                                    $checkedCount++;
                                }
                            }
                            $openSpots = count($division['regulars']) - $checkedCount;
                            ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                        <strong><?php echo htmlspecialchars($division['name']); ?></strong>
                                        <span>
                                            <?php echo count($division['regulars']); ?> registered<?php if ($division['substitutes']): ?>,
                                            <?php echo count($division['substitutes']); ?> <?php echo count($division['substitutes']) === 1 ? 'substitute' : 'substitutes'; ?><?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <label class="form-label" for="count-<?php echo (int)$division['division_id']; ?>">Regulars playing</label>
                                        <input class="form-control mb-3 division-count" type="number" min="0" max="<?php echo count($division['regulars']); ?>"
                                               id="count-<?php echo (int)$division['division_id']; ?>"
                                               data-division="<?php echo (int)$division['division_id']; ?>"
                                               value="<?php echo $checkedCount; ?>">
                                        <p class="text-muted small">Uncheck a regular who cannot play. The count keeps the regulars with fewer games. A substitute can take one of those open spots.</p>
                                        <?php foreach ($division['regulars'] as $player): ?>
                                            <label class="player-row form-check mb-1">
                                                <span>
                                                    <input class="form-check-input regular-check" type="checkbox"
                                                           name="present[]" value="<?php echo (int)$player['id']; ?>"
                                                           data-division="<?php echo (int)$division['division_id']; ?>"
                                                           <?php echo playerIsChecked($player, $saved_presence) ? 'checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                </span>
                                                <span class="text-muted small"><?php echo (int)$player['games_played']; ?> played</span>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if ($division['substitutes']): ?>
                                            <?php $spotsLeft = $openSpots; ?>
                                            <p class="text-muted small mt-3 mb-1">Substitutes</p>
                                            <p class="text-muted small sub-status" data-division="<?php echo (int)$division['division_id']; ?>"></p>
                                            <?php foreach ($division['substitutes'] as $player): ?>
                                                <?php
                                                $subChecked = playerIsChecked($player, $saved_presence) && $spotsLeft > 0;
                                                if ($subChecked) {
                                                    $spotsLeft--;
                                                }
                                                ?>
                                                <label class="player-row form-check mb-1">
                                                    <span>
                                                        <input class="form-check-input sub-check" type="checkbox"
                                                               name="present[]" value="<?php echo (int)$player['id']; ?>"
                                                               data-division="<?php echo (int)$division['division_id']; ?>"
                                                               <?php echo $subChecked ? 'checked' : ''; ?>
                                                               <?php echo !$subChecked && $spotsLeft <= 0 ? 'disabled' : ''; ?>>
                                                        <?php echo htmlspecialchars($player['name']); ?>
                                                        <span class="badge bg-secondary">Sub</span>
                                                    </span>
                                                    <span class="text-muted small"><?php echo (int)$player['games_played']; ?> played</span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-4">
                            <label for="courts" class="form-label">Courts</label>
                            <input class="form-control" id="courts" name="courts" value="<?php echo htmlspecialchars($courtField); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <p class="text-muted small mb-2">These numbers are used when you reassign. A range such as 3-7 fills every court from 3 through 7.</p>
                        </div>
                    </div>
                    <button type="submit" name="assign" class="btn btn-success">Reassign courts</button>
                </form>

                <?php if ($saved_rounds): ?>
                    <?php $playerTotals = assignmentPlayerTotals($saved_rounds, $divisions, $saved_presence); ?>
                    <?php if ($playerTotals): ?>
                        <section class="totals-sheet mt-5">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="mb-1"><?php echo date('l, F j, Y', strtotime($play_date)); ?></h2>
                                    <p class="mb-0">Matches and wait. <?php echo savedRoundMinutes($saved_rounds); ?>-minute games.</p>
                                </div>
                                <button type="button" class="btn btn-outline-success no-print" onclick="printSection('totals')">Print matches and wait</button>
                            </div>
                            <table class="sheet-table totals">
                                <thead>
                                    <tr>
                                        <th>Player</th>
                                        <th>Division</th>
                                        <th>Matches</th>
                                        <th>Wait</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($playerTotals as $total): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($total['name']); ?></td>
                                            <td><?php echo htmlspecialchars($total['division']); ?></td>
                                            <td><?php echo (int)$total['matches']; ?></td>
                                            <td><?php echo $total['wait_minutes'] > 0 ? (int)$total['wait_minutes'] . ' min' : 'None'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                    <?php endif; ?>
                    <section class="court-sheet mt-5">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h2 class="mb-1"><?php echo date('l, F j, Y', strtotime($play_date)); ?></h2>
                                <p class="mb-0">Court assignments. <?php echo savedRoundMinutes($saved_rounds); ?>-minute games, 7:00–9:00 PM. Write each side's score on the dashed line. Team 1 is the left side.</p>
                            </div>
                            <button type="button" class="btn btn-outline-success no-print" onclick="printSection('sheet')">Print scoresheet</button>
                        </div>
                        <table class="sheet-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Court</th>
                                    <th>Match</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_rounds as $when => $courts): ?>
                                    <?php
                                    $playingNow = [];
                                    foreach ($courts as $court) {
                                        foreach (courtSideIds($court) as $playerId) {
                                            if ($playerId > 0) {
                                                $playingNow[$playerId] = true;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php foreach ($courts as $court): ?>
                                        <?php [$leftA, $leftB, $rightA, $rightB] = courtSideIds($court); ?>
                                        <tr class="sheet-game">
                                            <td class="text-nowrap"><?php echo date('g:i A', strtotime($when)); ?></td>
                                            <td class="text-nowrap">
                                                <?php echo htmlspecialchars($court['location']); ?>
                                                <?php if (!empty($court['division_name'])): ?>
                                                    <div class="small"><?php echo htmlspecialchars($court['division_name']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="match">
                                                    <div class="side">
                                                        <div class="team"><?php echo htmlspecialchars(courtSideLabel($leftA, $leftB, $names)); ?></div>
                                                        <div class="score-caption">Team 1 score</div>
                                                        <div class="score-line"></div>
                                                    </div>
                                                    <div class="vs">vs</div>
                                                    <div class="side right">
                                                        <div class="team"><?php echo htmlspecialchars(courtSideLabel($rightA, $rightB, $names)); ?></div>
                                                        <div class="score-caption">Team 2 score</div>
                                                        <div class="score-line"></div>
                                                    </div>
                                                </div>
                                                <?php if ($court['notes'] === 'Repeat partner'): ?>
                                                    <div class="small">Repeat partner</div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php
                                    $sittingLines = [];
                                    foreach ($divisions as $division) {
                                        $sittingNames = [];
                                        foreach ($division['players'] as $player) {
                                            $playerId = (int)$player['id'];
                                            if (playerIsChecked($player, $saved_presence) && empty($playingNow[$playerId])) {
                                                $sittingNames[] = $player['name'];
                                            }
                                        }
                                        if ($sittingNames) {
                                            $sittingLines[] = $division['name'] . ': ' . implode(', ', $sittingNames);
                                        }
                                    }
                                    if ($sittingLines):
                                    ?>
                                        <tr class="sheet-sitting">
                                            <td><?php echo date('g:i A', strtotime($when)); ?></td>
                                            <td>Sitting</td>
                                            <td><?php echo htmlspecialchars(implode(' · ', $sittingLines)); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function printSection(name) {
            document.body.classList.remove('print-totals', 'print-sheet');
            document.body.classList.add('print-' + name);
            window.print();
        }
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('print-totals', 'print-sheet');
        });
        function regularBoxes(division) {
            return document.querySelectorAll('.regular-check[data-division="' + division + '"]');
        }
        function subBoxes(division) {
            return document.querySelectorAll('.sub-check[data-division="' + division + '"]');
        }
        function openSpots(division) {
            let spots = 0;
            regularBoxes(division).forEach(function (box) {
                if (!box.checked) spots++;
            });
            return spots;
        }
        function limitSubstitutes(division) {
            const spots = openSpots(division);
            let filled = 0;
            subBoxes(division).forEach(function (box) {
                if (box.checked && filled < spots) {
                    filled++;
                    box.disabled = false;
                } else {
                    box.checked = false;
                }
            });
            subBoxes(division).forEach(function (box) {
                box.disabled = !box.checked && filled >= spots;
            });
            const status = document.querySelector('.sub-status[data-division="' + division + '"]');
            if (!status) return;
            const open = openSpots(division);
            if (open === 0) {
                status.textContent = 'Uncheck a regular who cannot play, then check a substitute to take that spot.';
            } else if (filled === 0) {
                status.textContent = open + (open === 1 ? ' open spot.' : ' open spots.');
            } else {
                status.textContent = filled + ' of ' + open + (open === 1 ? ' open spot filled.' : ' open spots filled.');
            }
        }
        document.querySelectorAll('.division-count').forEach(function (input) {
            input.addEventListener('change', function () {
                const boxes = regularBoxes(input.dataset.division);
                const count = Math.max(0, Math.min(boxes.length, parseInt(input.value || '0', 10)));
                input.value = count;
                boxes.forEach(function (box, index) {
                    box.checked = index < count;
                });
                limitSubstitutes(input.dataset.division);
            });
        });
        document.querySelectorAll('.regular-check').forEach(function (box) {
            box.addEventListener('change', function () {
                const division = box.dataset.division;
                let count = 0;
                regularBoxes(division).forEach(function (item) {
                    if (item.checked) count++;
                });
                const input = document.getElementById('count-' + division);
                if (input) input.value = count;
                limitSubstitutes(division);
            });
        });
        document.querySelectorAll('.sub-check').forEach(function (box) {
            box.addEventListener('change', function () {
                limitSubstitutes(box.dataset.division);
            });
        });
        document.querySelectorAll('.division-count').forEach(function (input) {
            limitSubstitutes(input.dataset.division);
        });
    </script>
</body>
</html>
