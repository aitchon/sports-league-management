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
                $subNote = $rankByResults
                    ? 'They fill a court that would otherwise have fewer than 4 of its regulars, and they do not move up or down.'
                    : 'They fill in when a regular in that division is marked not playing.';
                $_SESSION['court_flash'] = '<div class="alert alert-success">Added ' . htmlspecialchars($subName) . ' as a substitute. ' . $subNote . '</div>';
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

$week_statuses = [];
$all_week_statuses = [];
if ($current_season && $play_date) {
    $all_week_statuses = pickleballLoadWeekStatuses($conn, (int)$current_season['id']);
    $week_statuses = $all_week_statuses[$play_date] ?? [];
}

$weekStatusOk = true;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['save_status']) || isset($_POST['assign']) || isset($_POST['patch']))
    && $current_season
    && $play_date
    && isset($_POST['week_status'])
    && is_array($_POST['week_status'])
) {
    $playerIds = [];
    foreach ($players as $player) {
        $playerIds[] = (int)$player['id'];
    }
    if (pickleballSaveWeekStatuses($conn, (int)$current_season['id'], $play_date, $playerIds, $_POST['week_status'])) {
        foreach ($playerIds as $playerId) {
            $week_statuses[$playerId] = pickleballNormalizeWeekStatus($_POST['week_status'][$playerId] ?? 'not confirmed');
        }
        if (isset($_POST['save_status']) && !isset($_POST['assign'])) {
            $_SESSION['court_flash'] = '<div class="alert alert-success">Saved weekly status for ' . date('l, F j, Y', strtotime($play_date)) . '. Players with no choice stay not confirmed.</div>';
            header('Location: assign_pickleball_courts.php?date=' . urlencode($play_date));
            exit();
        }
    } else {
        $weekStatusOk = false;
        $message = '<div class="alert alert-danger">Could not save weekly status. Run the new statement in admin/pickleball_court_assignment.sql first.</div>';
    }
}

$firstPlayDate = $fridays[0] ?? '';
$rankByResults = $play_date !== '' && $firstPlayDate !== '' && $play_date !== $firstPlayDate;
$previousPlayDate = previousPlayDate($fridays, $play_date);
$scoreGate = '';
if ($rankByResults && $previousPlayDate && $current_season && playDateAwaitingScores($conn, (int)$current_season['id'], $previousPlayDate)) {
    $scoreGate = 'Enter the scores for ' . date('l, F j', strtotime($previousPlayDate)) . ' before assigning courts for ' . date('l, F j', strtotime($play_date)) . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign']) && $current_season && $play_date) {
    $courtNumbers = parseCourtNumbers($_POST['courts'] ?? '');
    if (!$courtNumbers) {
        $message = '<div class="alert alert-danger">Enter the court numbers for this Friday, such as 3-7 or 1, 2, 4.</div>';
    } elseif ($scoreGate !== '') {
        $message = '<div class="alert alert-warning">' . htmlspecialchars($scoreGate) . '</div>';
    } else {
    $presentIds = [];
    $ladderPlan = null;
    if ($rankByResults) {
        try {
            $ladderPlan = buildLadderCourtPlan($conn, (int)$current_season['id'], $play_date, $fridays, $players, $week_statuses, $all_week_statuses, $courtNumbers);
        } catch (Throwable $e) {
            $ladderPlan = null;
            $message = '<div class="alert alert-danger">Could not build the courts. ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        if ($ladderPlan) {
            foreach ($ladderPlan['playing'] as $group) {
                foreach ($group['player_ids'] as $playerId) {
                    $presentIds[(int)$playerId] = true;
                }
            }
            if (!$ladderPlan['playing']) {
                $message = '<div class="alert alert-danger">At least one court needs 4 players before the night can be assigned.</div>';
                $ladderPlan = null;
            }
        }
    } else {
        foreach ($divisions as $division) {
            $openSpots = 0;
            foreach ($division['regulars'] as $player) {
                if (playerWillPlay($player, $week_statuses)) {
                    $presentIds[(int)$player['id']] = true;
                } else {
                    $openSpots++;
                }
            }
            foreach ($division['substitutes'] as $player) {
                if ($openSpots <= 0 || !playerWillPlay($player, $week_statuses)) {
                    continue;
                }
                $presentIds[(int)$player['id']] = true;
                $openSpots--;
            }
        }
    }

    if (!$rankByResults || $ladderPlan) {
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
        if ($rankByResults) {
            $plan = buildGroupedNightPlan($ladderPlan['playing']);
        } else {
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
        }

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
                $divisionId = (int)($court['division_id'] ?? 0);
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
        if ($rankByResults) {
            saveCourtRungs($conn, (int)$current_season['id'], $play_date, $ladderPlan['rungs']);
        }
        $conn->commit();
        $sessionMinutes = (int)($plan[0]['minutes'] ?? 15);
        $assignedCourts = [];
        foreach ($plan as $round) {
            foreach ($round['courts'] as $court) {
                if (preg_match('/(\d+)/', (string)$court['court'], $courtMatch)) {
                    $assignedCourts[(int)$courtMatch[1]] = (int)$courtMatch[1];
                }
            }
        }
        $courtSummary = formatCourtNumbers(array_values($assignedCourts));
        if ($rankByResults) {
            $assignNames = [];
            foreach ($players as $assignPlayer) {
                $assignNames[(int)$assignPlayer['id']] = $assignPlayer['name'];
            }
            $message = '<div class="alert alert-success">' . htmlspecialchars(ladderAssignmentText($ladderPlan['summary'], $assignNames, $gameCount, $play_date, $courtSummary, $sessionMinutes)) . '</div>';
        } else {
            $message = '<div class="alert alert-success">Assigned ' . $gameCount . ' games for ' . date('l, F j, Y', strtotime($play_date)) . '. ' . $sessionMinutes . '-minute games on courts ' . formatCourtNumbers($courtNumbers) . '.</div>';
        }
    } catch (Throwable $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">Could not save the court assignment. ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    }
    }
}

$names = [];
foreach ($players as $player) {
    $names[(int)$player['id']] = $player['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patch']) && $current_season && $play_date && $weekStatusOk) {
    $rows = loadPlayDateGames($conn, (int)$current_season['id'], $play_date);
    $scheduled = [];
    $completed = 0;
    foreach ($rows as $row) {
        if ($row['status'] === 'scheduled') {
            $scheduled[] = $row;
        } else {
            $completed++;
        }
    }
    if (!$scheduled) {
        $text = $completed
            ? 'These games are already completed, so this schedule was left as it is.'
            : 'Save a court assignment for this Friday first. Then Update for absences can swap out players who cannot make it.';
        $_SESSION['court_flash'] = '<div class="alert alert-warning">' . $text . '</div>';
        header('Location: assign_pickleball_courts.php?date=' . urlencode($play_date));
        exit();
    }
    $plannerGames = [];
    $gamesById = [];
    foreach ($scheduled as $row) {
        $game = [
            'id' => (int)$row['id'],
            'time' => $row['date'],
            'location' => $row['location'],
            'division_id' => (int)$row['division_id'],
            'status' => 'scheduled',
            'slots' => courtSideIds($row),
        ];
        $plannerGames[] = $game;
        $gamesById[$game['id']] = $game;
    }
    $rankedNight = (bool)$scheduled;
    foreach ($scheduled as $row) {
        if ((int)$row['division_id'] > 0) {
            $rankedNight = false;
            break;
        }
    }
    $willPlay = [];
    foreach ($players as $player) {
        if (playerWillPlay($player, $week_statuses)) {
            $willPlay[(int)$player['id']] = true;
        }
    }
    $plannerDivisions = [];
    if ($rankedNight) {
        [$plannerGames, $plannerDivisions] = courtGroupPlanner($plannerGames, $players);
        $subIds = [];
        foreach ($players as $player) {
            if (!empty($player['is_substitute'])) {
                $subIds[] = (int)$player['id'];
            }
        }
        foreach ($plannerDivisions as $index => $division) {
            $regularsPlaying = 0;
            foreach ($division['regulars'] as $playerId) {
                if (!empty($willPlay[(int)$playerId])) {
                    $regularsPlaying++;
                }
            }
            if ($regularsPlaying >= 4) {
                continue;
            }
            foreach ($subIds as $subId) {
                if (!in_array($subId, $plannerDivisions[$index]['substitutes'], true)) {
                    $plannerDivisions[$index]['substitutes'][] = $subId;
                }
            }
        }
    } else {
    foreach ($divisions as $division) {
        $regulars = [];
        $subs = [];
        foreach ($division['regulars'] as $player) {
            $regulars[] = (int)$player['id'];
        }
        foreach ($division['substitutes'] as $player) {
            $subs[] = (int)$player['id'];
        }
        $plannerDivisions[] = [
            'division_id' => (int)$division['division_id'],
            'regulars' => $regulars,
            'substitutes' => $subs,
        ];
    }
    }
    $patched = patchAbsentPlayers($plannerGames, $plannerDivisions, $willPlay);
    $afterPatch = [];
    foreach ($plannerGames as $game) {
        $game['slots'] = $patched['slots'][$game['id']] ?? $game['slots'];
        $afterPatch[] = $game;
    }
    $balanced = rebalanceFairNight($afterPatch, $plannerDivisions, $willPlay);
    $patched['replacements'] = array_merge($patched['replacements'], $balanced['replacements']);
    $patched['slots'] = $balanced['slots'] + $patched['slots'];
    $changed = [];
    foreach ($patched['slots'] as $gameId => $slots) {
        if ($slots !== $gamesById[$gameId]['slots']) {
            $changed[$gameId] = $slots;
        }
    }
    $presenceSlots = $patched['slots'];
    foreach ($rows as $row) {
        if ($row['status'] !== 'scheduled') {
            $presenceSlots['done-' . $row['id']] = courtSideIds($row);
        }
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    try {
        if ($changed) {
            writeGameSlots($conn, (int)$current_season['id'], $changed);
        }
        saveNightPresence($conn, (int)$current_season['id'], $play_date, nightPresenceMap($players, $week_statuses, $presenceSlots));
        $conn->commit();
        $_SESSION['court_flash'] = absenceUpdateMessage($patched, $players);
        header('Location: assign_pickleball_courts.php?date=' . urlencode($play_date));
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">Could not update the schedule. ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

$slotOverrides = [];
$courtOverrides = [];
$timeOverrides = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slots']) && $current_season && $play_date) {
    $rows = loadPlayDateGames($conn, (int)$current_season['id'], $play_date);
    $scheduled = [];
    foreach ($rows as $row) {
        if ($row['status'] === 'scheduled') {
            $scheduled[(int)$row['id']] = $row;
        }
    }
    $posted = $_POST['slot'] ?? [];
    $postedCourts = $_POST['court_location'] ?? [];
    $postedTimes = $_POST['court_time'] ?? [];
    $allowedTimes = [];
    foreach ($scheduled as $row) {
        $allowedTimes[date('H:i', strtotime($row['date']))] = true;
    }
    $allowedByDivision = [];
    $divisionNames = [];
    foreach ($divisions as $division) {
        $divisionId = (int)$division['division_id'];
        $divisionNames[$divisionId] = $division['name'];
        $allowedByDivision[$divisionId] = [];
        foreach ($division['players'] as $player) {
            $allowedByDivision[$divisionId][(int)$player['id']] = true;
        }
    }
    $errors = [];
    $newSlots = [];
    $locationsByGame = [];
    $timesByGame = [];
    if (!$scheduled || !is_array($posted)) {
        $errors[] = 'There are no scheduled courts to change.';
    }
    foreach ($scheduled as $gameId => $row) {
        $current = courtSideIds($row);
        $currentTime = date('H:i', strtotime($row['date']));
        $location = normalizeCourtLocation(is_array($postedCourts) ? ($postedCourts[$gameId] ?? $row['location']) : $row['location']);
        $time = is_array($postedTimes) ? trim((string)($postedTimes[$gameId] ?? '')) : '';
        if ($location === '') {
            $location = (string)$row['location'];
            $errors[] = 'Enter a court for ' . date('g:i A', strtotime($row['date'])) . '.';
        }
        if ($time === '' || empty($allowedTimes[$time])) {
            $time = $currentTime;
            $errors[] = 'Choose a time already on this night for ' . $location . '.';
        }
        $locationsByGame[$gameId] = $location;
        $timesByGame[$gameId] = $time;
        $incoming = (is_array($posted) && isset($posted[$gameId]) && is_array($posted[$gameId])) ? $posted[$gameId] : null;
        if ($incoming === null) {
            $newSlots[$gameId] = $current;
            continue;
        }
        $slots = [];
        for ($i = 0; $i < 4; $i++) {
            $slots[] = (int)($incoming[$i] ?? 0);
        }
        $newSlots[$gameId] = $slots;
        $divisionId = (int)$row['division_id'];
        $allowed = $allowedByDivision[$divisionId] ?? [];
        foreach ($current as $playerId) {
            if ($playerId > 0) {
                $allowed[$playerId] = true;
            }
        }
        $where = trim(date('g:i A', strtotime($play_date . ' ' . $time)) . ' ' . $location);
        $divisionName = $divisionNames[$divisionId] ?? 'that division';
        $seen = [];
        foreach ($slots as $playerId) {
            if ($playerId <= 0 || empty($allowed[$playerId])) {
                $errors[] = 'Choose four players from ' . $divisionName . ' for ' . $where . '.';
                $seen = null;
                break;
            }
            if (isset($seen[$playerId])) {
                $errors[] = ($names[(int)$playerId] ?? 'A player') . ' is listed twice for ' . $where . '.';
                $seen = null;
                break;
            }
            $seen[$playerId] = true;
        }
    }
    if (!$errors) {
        $busy = [];
        $taken = [];
        foreach ($newSlots as $gameId => $slots) {
            $when = $play_date . ' ' . $timesByGame[$gameId];
            $placeKey = $when . '|' . strtolower($locationsByGame[$gameId]);
            if (isset($taken[$placeKey])) {
                $errors[] = $locationsByGame[$gameId] . ' is used twice at ' . date('g:i A', strtotime($when)) . '.';
            }
            $taken[$placeKey] = $gameId;
            foreach ($slots as $playerId) {
                if ($playerId <= 0) {
                    continue;
                }
                if (isset($busy[$when][$playerId])) {
                    $errors[] = ($names[$playerId] ?? 'A player') . ' is on two courts at ' . date('g:i A', strtotime($when)) . '.';
                }
                $busy[$when][$playerId] = $gameId;
            }
        }
        foreach ($rows as $row) {
            if ($row['status'] === 'scheduled') {
                continue;
            }
            $when = date('Y-m-d H:i', strtotime($row['date']));
            $placeKey = $when . '|' . strtolower((string)$row['location']);
            if (isset($taken[$placeKey])) {
                $errors[] = $row['location'] . ' already has a completed game at ' . date('g:i A', strtotime($row['date'])) . '.';
            }
            foreach (courtSideIds($row) as $playerId) {
                if ($playerId > 0 && isset($busy[$when][$playerId])) {
                    $errors[] = ($names[$playerId] ?? 'A player') . ' is already in a completed game at ' . date('g:i A', strtotime($row['date'])) . '.';
                }
            }
        }
    }
    if ($errors) {
        $shown = array_slice(array_unique($errors), 0, 4);
        $message = '<div class="alert alert-danger">' . implode('<br>', array_map('htmlspecialchars', $shown)) . '</div>';
        $slotOverrides = $newSlots;
        $courtOverrides = $locationsByGame;
        $timeOverrides = $timesByGame;
    } else {
        $changed = [];
        $placeChanges = [];
        foreach ($newSlots as $gameId => $slots) {
            $currentWhen = date('Y-m-d H:i:s', strtotime($scheduled[$gameId]['date']));
            $nextWhen = $play_date . ' ' . $timesByGame[$gameId] . ':00';
            $playersChanged = $slots !== courtSideIds($scheduled[$gameId]);
            $placeChanged = $locationsByGame[$gameId] !== (string)$scheduled[$gameId]['location']
                || $nextWhen !== $currentWhen;
            if ($playersChanged) {
                $changed[$gameId] = $slots;
            }
            if ($placeChanged) {
                $placeChanges[$gameId] = [
                    'location' => $locationsByGame[$gameId],
                    'date' => $nextWhen,
                ];
            }
        }
        if (!$changed && !$placeChanges) {
            $_SESSION['court_flash'] = '<div class="alert alert-info">No court changes to save. The schedule was left as it is.</div>';
            header('Location: assign_pickleball_courts.php?date=' . urlencode($play_date));
            exit();
        }
        $presenceSlots = $newSlots;
        foreach ($rows as $row) {
            if ($row['status'] !== 'scheduled') {
                $presenceSlots['done-' . $row['id']] = courtSideIds($row);
            }
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();
        try {
            writeGameSlots($conn, (int)$current_season['id'], $changed, $placeChanges);
            saveNightPresence($conn, (int)$current_season['id'], $play_date, nightPresenceMap($players, $week_statuses, $presenceSlots));
            $conn->commit();
            $_SESSION['court_flash'] = '<div class="alert alert-success">Saved the court changes. The other courts were left as they are.</div>';
            header('Location: assign_pickleball_courts.php?date=' . urlencode($play_date));
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $slotOverrides = $newSlots;
            $courtOverrides = $locationsByGame;
            $timeOverrides = $timesByGame;
            $message = '<div class="alert alert-danger">Could not save the court changes. ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

$saved_rounds = [];
if ($current_season && $play_date) {
    foreach (loadPlayDateGames($conn, (int)$current_season['id'], $play_date) as $row) {
        $saved_rounds[$row['date']][] = $row;
    }
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

function priorPlayDates(array $playDates, $playDate, $limit) {
    $prior = [];
    foreach ($playDates as $date) {
        if ($date >= $playDate) {
            break;
        }
        $prior[] = $date;
    }
    return array_slice($prior, -1 * (int)$limit);
}

function buildLadderCourtPlan($conn, $seasonId, $playDate, array $playDates, array $players, array $weekStatuses, array $allWeekStatuses, array $courtNumbers) {
    $stored = loadLatestCourtRungs($conn, $seasonId, $playDate);
    if ($stored === null) {
        throw new RuntimeException('Could not read the saved court groups.');
    }
    $names = [];
    $regularIds = [];
    $regularSet = [];
    $subNames = [];
    $willPlay = [];
    foreach ($players as $player) {
        $playerId = (int)$player['id'];
        $names[$playerId] = $player['name'];
        if (!empty($player['is_substitute'])) {
            $subNames[$playerId] = $player['name'];
        } else {
            $regularIds[] = $playerId;
            $regularSet[$playerId] = true;
        }
        if (playerWillPlay($player, $weekStatuses)) {
            $willPlay[$playerId] = true;
        }
    }
    $previousRungs = $stored['rungs'];
    $resultDate = $stored['play_date'] ?: previousPlayDate($playDates, $playDate);
    $loadedAppearances = $resultDate ? loadSingleCourtAppearances($conn, $seasonId, $resultDate) : ['single' => [], 'multiple' => [], 'division_night' => false];
    if (!$previousRungs && $resultDate && empty($loadedAppearances['division_night']) && empty($loadedAppearances['multiple']) && $loadedAppearances['single']) {
        $byCourt = [];
        foreach ($loadedAppearances['single'] as $playerId => $court) {
            if (!empty($regularSet[$playerId])) {
                $byCourt[(int)$court][] = (int)$playerId;
            }
        }
        ksort($byCourt, SORT_NUMERIC);
        foreach ($byCourt as $court => $playerIds) {
            $previousRungs[] = ['court' => $court, 'player_ids' => $playerIds];
        }
    }
    $results = $resultDate ? seasonResultRecords($conn, $seasonId, $resultDate) : [];
    $seasonResults = seasonResultRecords($conn, $seasonId);
    $onRung = [];
    foreach ($previousRungs as $rung) {
        foreach ($rung['player_ids'] as $playerId) {
            $onRung[(int)$playerId] = true;
        }
    }
    $offRungCourts = [];
    foreach ($loadedAppearances['single'] as $playerId => $court) {
        if (!empty($onRung[$playerId])) {
            continue;
        }
        $offRungCourts[(int)$court][] = (int)$playerId;
    }
    $played = [];
    $idle = [];
    foreach ($regularIds as $playerId) {
        if (isset($results[$playerId])) {
            $row = $results[$playerId];
            $row['id'] = $playerId;
            $row['name'] = $names[$playerId];
            $played[] = $row;
        } else {
            $row = $seasonResults[$playerId] ?? ['wins' => 0, 'losses' => 0, 'point_differential' => 0];
            $row['id'] = $playerId;
            $row['name'] = $names[$playerId];
            $idle[] = $row;
        }
    }
    usort($played, 'compareCourtNightResult');
    usort($idle, 'pickleballRankCompare');
    $playedOrdered = [];
    foreach ($played as $row) {
        $playedOrdered[] = (int)$row['id'];
    }
    $idleOrdered = [];
    foreach ($idle as $row) {
        $idleOrdered[] = (int)$row['id'];
    }
    $prior = priorPlayDates($playDates, $playDate, 2);
    $twoMiss = [];
    if (count($prior) === 2) {
        foreach ($regularIds as $playerId) {
            $first = pickleballNormalizeWeekStatus($allWeekStatuses[$prior[0]][$playerId] ?? 'not confirmed');
            $second = pickleballNormalizeWeekStatus($allWeekStatuses[$prior[1]][$playerId] ?? 'not confirmed');
            if ($first === 'not playing' && $second === 'not playing') {
                $twoMiss[$playerId] = true;
            }
        }
    }
    return planLadderCourts([
        'names' => $names,
        'results' => $results,
        'willPlay' => $willPlay,
        'courtNumbers' => $courtNumbers,
        'regularIds' => $regularIds,
        'twoMiss' => $twoMiss,
        'subNames' => $subNames,
        'offRungCourts' => $offRungCourts,
        'previousRungs' => $previousRungs,
        'playedOrdered' => $playedOrdered,
        'idleOrdered' => $idleOrdered,
    ]);
}

function ladderAssignmentText(array $summary, array $names, $gameCount, $playDate, $courtSummary, $sessionMinutes) {
    $parts = ['Assigned ' . (int)$gameCount . ' games for ' . date('l, F j, Y', strtotime($playDate)) . '.'];
    if (!empty($summary['seed'])) {
        $parts[] = 'Courts were ordered from last Friday\'s results.';
    } elseif ((int)($summary['up'] ?? 0) === 0) {
        $parts[] = 'Players stayed on last week\'s courts.';
    } else {
        $moved = (int)$summary['up'];
        $parts[] = $moved === 1
            ? '1 player moved up and 1 moved down.'
            : $moved . ' players moved up and ' . $moved . ' moved down.';
    }
    $subCount = (int)($summary['subs'] ?? 0);
    if ($subCount === 1) {
        $parts[] = '1 substitute filled in.';
    } elseif ($subCount > 1) {
        $parts[] = $subCount . ' substitutes filled in.';
    }
    $listNames = function (array $ids) use ($names) {
        $who = [];
        foreach ($ids as $playerId) {
            $who[] = $names[(int)$playerId] ?? 'A player';
        }
        return implode(', ', $who);
    };
    if (!empty($summary['bumped'])) {
        $who = $listNames($summary['bumped']);
        $parts[] = $who . (count($summary['bumped']) === 1 ? ' came off a full court after missing two Fridays.' : ' came off full courts after missing two Fridays.');
    }
    if (!empty($summary['waiting'])) {
        $who = $listNames($summary['waiting']);
        $parts[] = $who . (count($summary['waiting']) === 1 ? ' stayed off because every court already has 8 regulars.' : ' stayed off because every court already has 8 regulars.');
    }
    if (!empty($summary['short'])) {
        $courts = [];
        foreach ($summary['short'] as $number) {
            $courts[] = 'Court ' . (int)$number;
        }
        $parts[] = implode(', ', $courts) . (count($courts) === 1 ? ' did not have 4 players, so that court was left off.' : ' did not have 4 players, so those courts were left off.');
    }
    if (!empty($summary['extended'])) {
        $parts[] = 'An extra court was added so no court has more than 8 regulars.';
    }
    $parts[] = (int)$sessionMinutes . '-minute games on courts ' . $courtSummary . '.';
    return implode(' ', $parts);
}

function loadPlayDateGames($conn, $seasonId, $playDate) {
    $stmt = $conn->prepare("
        SELECT pg.id, pg.date, pg.location, pg.division_id, pg.notes, pg.status,
               pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id,
               d.name AS division_name,
               a.team1_player1_id, a.team1_player2_id, a.team2_player1_id, a.team2_player2_id
        FROM pickleball_games pg
        LEFT JOIN pickleball_divisions d ON d.id = pg.division_id
        LEFT JOIN pickleball_match_assignments a ON a.game_id = pg.id AND a.match_number = 1
        WHERE pg.season_id = ? AND DATE(pg.date) = ? AND pg.player1_id IS NOT NULL
        ORDER BY pg.date, pg.location, pg.id
    ");
    $stmt->bind_param("is", $seasonId, $playDate);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function writeGameSlots($conn, $seasonId, array $slotsByGame, array $placeByGame = []) {
    $updateGame = $conn->prepare("UPDATE pickleball_games SET player1_id = ?, player2_id = ?, player3_id = ?, player4_id = ? WHERE id = ? AND season_id = ? AND status = 'scheduled'");
    $updatePlace = $conn->prepare("UPDATE pickleball_games SET location = ?, date = ? WHERE id = ? AND season_id = ? AND status = 'scheduled'");
    $findMatch = $conn->prepare("SELECT id FROM pickleball_match_assignments WHERE game_id = ? AND match_number = 1 LIMIT 1");
    $updateMatch = $conn->prepare("UPDATE pickleball_match_assignments SET team1_player1_id = ?, team1_player2_id = ?, team2_player1_id = ?, team2_player2_id = ? WHERE game_id = ? AND match_number = 1");
    $insertMatch = $conn->prepare("INSERT INTO pickleball_match_assignments (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id) VALUES (?, 1, ?, ?, ?, ?)");
    foreach ($placeByGame as $gameId => $place) {
        $gameId = (int)$gameId;
        $location = (string)$place['location'];
        $when = (string)$place['date'];
        $updatePlace->bind_param("ssii", $location, $when, $gameId, $seasonId);
        $updatePlace->execute();
    }
    foreach ($slotsByGame as $gameId => $slots) {
        $gameId = (int)$gameId;
        $p1 = (int)$slots[0];
        $p2 = (int)$slots[1];
        $p3 = (int)$slots[2];
        $p4 = (int)$slots[3];
        $updateGame->bind_param("iiiiii", $p1, $p2, $p3, $p4, $gameId, $seasonId);
        $updateGame->execute();
        $findMatch->bind_param("i", $gameId);
        $findMatch->execute();
        $matchResult = $findMatch->get_result();
        $existing = $matchResult->fetch_assoc();
        $matchResult->free();
        if ($existing) {
            $updateMatch->bind_param("iiiii", $p1, $p2, $p3, $p4, $gameId);
            $updateMatch->execute();
        } else {
            $insertMatch->bind_param("iiiii", $gameId, $p1, $p2, $p3, $p4);
            $insertMatch->execute();
        }
    }
    $updateGame->close();
    $updatePlace->close();
    $findMatch->close();
    $updateMatch->close();
    $insertMatch->close();
}

function normalizeCourtLocation($value) {
    $value = trim((string)$value);
    if (preg_match('/^\d+$/', $value)) {
        return 'Court ' . (int)$value;
    }
    return $value;
}

function nightPresenceMap(array $players, array $weekStatuses, array $slotsByGame) {
    $onCourt = [];
    foreach ($slotsByGame as $slots) {
        foreach ($slots as $playerId) {
            $playerId = (int)$playerId;
            if ($playerId > 0) {
                $onCourt[$playerId] = true;
            }
        }
    }
    $present = [];
    foreach ($players as $player) {
        $playerId = (int)$player['id'];
        if (!empty($player['is_substitute'])) {
            $present[$playerId] = empty($onCourt[$playerId]) ? 0 : 1;
        } else {
            $present[$playerId] = (playerWillPlay($player, $weekStatuses) || !empty($onCourt[$playerId])) ? 1 : 0;
        }
    }
    return $present;
}

function saveNightPresence($conn, $seasonId, $playDate, array $present) {
    $delete = $conn->prepare("DELETE FROM pickleball_session_players WHERE season_id = ? AND play_date = ?");
    $delete->bind_param("is", $seasonId, $playDate);
    $delete->execute();
    $delete->close();
    $insert = $conn->prepare("INSERT INTO pickleball_session_players (season_id, play_date, player_id, is_present) VALUES (?, ?, ?, ?)");
    foreach ($present as $playerId => $isPresent) {
        $playerId = (int)$playerId;
        $isPresent = (int)$isPresent ? 1 : 0;
        $insert->bind_param("isii", $seasonId, $playDate, $playerId, $isPresent);
        $insert->execute();
    }
    $insert->close();
}

function absenceUpdateMessage(array $patched, array $players) {
    $names = [];
    foreach ($players as $player) {
        $names[(int)$player['id']] = $player['name'];
    }
    $parts = [];
    foreach ($patched['replacements'] as $row) {
        $in = $names[(int)$row['in']] ?? ('Player ' . (int)$row['in']);
        $out = $names[(int)$row['out']] ?? ('Player ' . (int)$row['out']);
        $count = (int)$row['games'];
        $parts[] = htmlspecialchars($in) . ' took ' . htmlspecialchars($out) . "'s " . $count . ' ' . ($count === 1 ? 'game' : 'games');
    }
    $openByReason = ['ahead' => [], 'sub' => [], 'busy' => []];
    foreach ($patched['unfilled'] as $row) {
        $who = $names[(int)$row['player_id']] ?? ('Player ' . (int)$row['player_id']);
        $when = trim(date('g:i A', strtotime($row['time'])) . ' ' . ($row['location'] ?? ''));
        $reason = $row['reason'] ?? 'busy';
        if (!isset($openByReason[$reason])) {
            $reason = 'busy';
        }
        $openByReason[$reason][$who][] = $when;
    }
    $html = '';
    $stillOpen = $openByReason['ahead'] || $openByReason['sub'] || $openByReason['busy'];
    if ($parts) {
        $html .= '<div class="alert alert-' . ($stillOpen ? 'warning' : 'success') . '">Kept the other courts the same. ' . implode('. ', $parts) . '.</div>';
    }
    foreach ([
        'ahead' => 'These spots were left unchanged so the fill-in stays within one game of the rest of the division: ',
        'sub' => 'These spots were left unchanged so a substitute keeps the fewest games in the division: ',
        'busy' => 'These spots still need a player because nobody in that division was free: ',
    ] as $reason => $lead) {
        if (!$openByReason[$reason]) {
            continue;
        }
        $open = [];
        foreach ($openByReason[$reason] as $who => $times) {
            $open[] = htmlspecialchars($who) . ' at ' . htmlspecialchars(implode(', ', $times));
        }
        $html .= '<div class="alert alert-warning">' . $lead . implode('; ', $open) . '. Change that court under Update courts, or add a substitute and update again.</div>';
    }
    if ($html === '') {
        $html = '<div class="alert alert-info">Everyone on the courts is still playing. The schedule was left as it is.</div>';
    }
    return $html;
}

function courtSlotSelect($gameId, $index, $selectedId, array $optionPlayers, array $weekStatuses) {
    $selectedId = (int)$selectedId;
    $class = 'form-select form-select-sm slot-select';
    foreach ($optionPlayers as $player) {
        if ((int)$player['id'] === $selectedId && !playerWillPlay($player, $weekStatuses)) {
            $class .= ' slot-out';
        }
    }
    $html = '<select class="' . $class . '" name="slot[' . (int)$gameId . '][' . (int)$index . ']">';
    if ($selectedId <= 0) {
        $html .= '<option value="" selected>Choose</option>';
    }
    $seen = [];
    foreach ($optionPlayers as $player) {
        $id = (int)$player['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $label = $player['name'];
        if (!empty($player['is_substitute'])) {
            $label .= ' (sub)';
        }
        if (!playerWillPlay($player, $weekStatuses)) {
            $label .= ' (not playing)';
        }
        $html .= '<option value="' . $id . '"' . ($id === $selectedId ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
    }
    if ($selectedId > 0 && empty($seen[$selectedId])) {
        $html .= '<option value="' . $selectedId . '" selected>Player ' . $selectedId . '</option>';
    }
    $html .= '</select>';
    return $html;
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

function playerWillPlay(array $player, array $weekStatuses) {
    $status = pickleballNormalizeWeekStatus($weekStatuses[(int)$player['id']] ?? 'not confirmed');
    return $status !== 'not playing';
}

function weekStatusSelect($playerId, array $weekStatuses, $playerName = '') {
    $current = pickleballNormalizeWeekStatus($weekStatuses[(int)$playerId] ?? 'not confirmed');
    $options = [
        'not confirmed' => 'Not confirmed',
        'confirmed' => 'Confirmed',
        'not playing' => 'Not playing',
    ];
    $label = $playerName !== '' ? ' aria-label="Weekly status for ' . htmlspecialchars($playerName) . '"' : '';
    $html = '<select class="form-select form-select-sm week-status-select" name="week_status[' . (int)$playerId . ']"' . $label . '>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . htmlspecialchars($value) . '"' . ($current === $value ? ' selected' : '') . '>' . $label . '</option>';
    }
    $html .= '</select>';
    return $html;
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
        .player-row { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
        .player-row.player-out { opacity: 0.55; }
        .player-row.player-out .player-name { text-decoration: line-through; }
        .week-status-select { width: 9.5rem; }
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
        .slot-select { width: auto; min-width: 9rem; max-width: 14rem; }
        .slot-select.slot-out { border-color: #b45309; background: #fff7e6; }
        .slot-select.slot-conflict, .court-location.slot-conflict { border-color: #b91c1c; background: #fef2f2; }
        .court-time { width: 7.2rem; }
        .court-location { width: 7.5rem; }
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
                        <p class="text-muted small"><?php echo $rankByResults ? 'A substitute fills a court that would otherwise have fewer than 4 of its own regulars. They play that Friday only and are not part of the court ladder. Not confirmed still counts as playing.' : 'A substitute takes the spot of a regular in the same division who is marked not playing. Not confirmed still counts as playing.'; ?></p>
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
                    <p class="text-muted">Not confirmed still plays. Mark someone not playing to leave them off the courts. Confirmed and not playing show on the public schedule. When the courts are already set, Update for absences swaps those players out and leaves everyone else on the same court.</p>
                    <div class="row">
                        <?php foreach ($divisions as $division): ?>
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
                                        <p class="text-muted small division-summary mb-2" data-division="<?php echo (int)$division['division_id']; ?>"></p>
                                        <?php foreach ($division['regulars'] as $player): ?>
                                            <div class="player-row mb-1" data-role="regular" data-division="<?php echo (int)$division['division_id']; ?>">
                                                <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                                <span class="d-flex align-items-center gap-2">
                                                    <?php echo weekStatusSelect((int)$player['id'], $week_statuses, $player['name']); ?>
                                                    <span class="text-muted small text-nowrap"><?php echo (int)$player['games_played']; ?> played</span>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($division['substitutes']): ?>
                                            <p class="text-muted small mt-3 mb-1"><?php echo $rankByResults ? 'A substitute plays only when a court would have fewer than 4 of its regulars. Mark a substitute not playing to keep them off the courts.' : 'Substitutes fill open spots in the order shown when courts are reassigned. Update for absences uses a substitute only when no regular is free, and the substitute with fewer games tonight goes in. Mark extras not playing if someone else should go in.'; ?></p>
                                            <p class="text-muted small sub-status" data-division="<?php echo (int)$division['division_id']; ?>"></p>
                                            <?php foreach ($division['substitutes'] as $player): ?>
                                                <div class="player-row mb-1" data-role="substitute" data-division="<?php echo (int)$division['division_id']; ?>">
                                                    <span class="player-name">
                                                        <?php echo htmlspecialchars($player['name']); ?>
                                                        <span class="badge bg-secondary">Sub</span>
                                                    </span>
                                                    <span class="d-flex align-items-center gap-2">
                                                        <?php echo weekStatusSelect((int)$player['id'], $week_statuses, $player['name']); ?>
                                                        <span class="text-muted small text-nowrap"><?php echo (int)$player['games_played']; ?> played</span>
                                                    </span>
                                                </div>
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
                            <p class="text-muted small mb-2"><?php echo $rankByResults ? 'The top court uses the lowest court number. After the first Friday, players stay on that court and last week\'s results swap neighbors. A range such as 1-5 is five courts.' : 'These numbers are used when you reassign. A range such as 3-7 fills every court from 3 through 7.'; ?></p>
                        </div>
                    </div>
                    <?php $hasSchedule = !empty($saved_rounds); ?>
                    <button type="submit" name="patch" class="btn <?php echo $hasSchedule ? 'btn-success' : 'btn-outline-success'; ?>">Update for absences</button>
                    <button type="submit" name="assign" class="btn <?php echo $hasSchedule ? 'btn-outline-success' : 'btn-success'; ?> ms-2"<?php echo $scoreGate ? ' disabled' : ''; ?>>Reassign courts</button>
                    <button type="submit" name="save_status" class="btn btn-outline-secondary ms-2">Save weekly status</button>
                    <?php if ($scoreGate): ?>
                        <div class="alert alert-warning mt-3 mb-0"><?php echo htmlspecialchars($scoreGate); ?></div>
                    <?php endif; ?>
                    <p class="text-muted small mt-2 mb-0"><?php if ($rankByResults): ?>Reassign courts keeps each regular on last week's court. Last week's wins, then point differential, then points scored, move players between neighboring courts: 1 player when 4 to 6 regulars played, 2 when 7 or 8 played. A tie on that cut stays put. Fewer than 4 regulars played means nobody moves. Someone who missed keeps the court. A substitute is added only to reach 4 and does not move. A new regular joins a court that has fewer than 8, and someone who missed two Fridays in a row is the one who comes off a full court. The first Friday is still grouped by division. This Friday cannot be assigned until the previous Friday's scores are entered. Update for absences only changes the court someone was already on. Update courts is for choosing the players, court, and time yourself.<?php else: ?>Update for absences keeps the current times and courts. It fills spots for players marked not playing, and it moves extra games to someone who is sitting when a player already has more than the rest of the division. A substitute keeps the fewest games. Reassign courts builds a new schedule for everyone who is playing. Update courts is for choosing the players, court, and time yourself.<?php endif; ?></p>
                </form>

                <?php if ($saved_rounds): ?>
                    <?php
                    $scheduled_rows = [];
                    foreach ($saved_rounds as $courts) {
                        foreach ($courts as $court) {
                            if (($court['status'] ?? '') === 'scheduled') {
                                $scheduled_rows[] = $court;
                            }
                        }
                    }
                    $editorTimes = [];
                    foreach ($scheduled_rows as $court) {
                        $editorTimes[date('H:i', strtotime($court['date']))] = date('g:i A', strtotime($court['date']));
                    }
                    ksort($editorTimes);
                    ?>
                    <?php if ($scheduled_rows): ?>
                        <form method="post" id="court-editor" class="card mt-4 no-print">
                            <div class="card-header bg-success text-white">
                                <strong>Update courts</strong>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Change any player, move a game to another court, or move it to another time already on this night. Saving writes only the courts you change. Weekly status stays as it is, and a player marked not playing can still be placed on a court. A player can be on one court at a time.</p>
                                <input type="hidden" name="play_date" value="<?php echo htmlspecialchars($play_date); ?>">
                                <?php foreach ($scheduled_rows as $court): ?>
                                    <?php
                                    $gameId = (int)$court['id'];
                                    $editSlots = $slotOverrides[$gameId] ?? courtSideIds($court);
                                    $editLocation = $courtOverrides[$gameId] ?? $court['location'];
                                    $editTime = $timeOverrides[$gameId] ?? date('H:i', strtotime($court['date']));
                                    $slotOptions = $divisions[(int)$court['division_id']]['players'] ?? [];
                                    ?>
                                    <div class="court-edit mb-3">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <select class="form-select form-select-sm court-time" name="court_time[<?php echo $gameId; ?>]" aria-label="Time">
                                                <?php foreach ($editorTimes as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>"<?php echo $value === $editTime ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                <?php endforeach; ?>
                                                <?php if (!isset($editorTimes[$editTime])): ?>
                                                    <option value="<?php echo htmlspecialchars($editTime); ?>" selected><?php echo htmlspecialchars(date('g:i A', strtotime($play_date . ' ' . $editTime))); ?></option>
                                                <?php endif; ?>
                                            </select>
                                            <input class="form-control form-control-sm court-location" name="court_location[<?php echo $gameId; ?>]" value="<?php echo htmlspecialchars($editLocation); ?>" aria-label="Court" required>
                                            <?php if (!empty($court['division_name'])): ?>
                                                <span class="small text-muted"><?php echo htmlspecialchars($court['division_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <?php echo courtSlotSelect($gameId, 0, $editSlots[0], $slotOptions, $week_statuses); ?>
                                            <span>&amp;</span>
                                            <?php echo courtSlotSelect($gameId, 1, $editSlots[1], $slotOptions, $week_statuses); ?>
                                            <span class="fw-bold">vs</span>
                                            <?php echo courtSlotSelect($gameId, 2, $editSlots[2], $slotOptions, $week_statuses); ?>
                                            <span>&amp;</span>
                                            <?php echo courtSlotSelect($gameId, 3, $editSlots[3], $slotOptions, $week_statuses); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" name="save_slots" class="btn btn-success">Save court changes</button>
                            </div>
                        </form>
                    <?php endif; ?>
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
                                        <?php
                                        [$leftA, $leftB, $rightA, $rightB] = courtSideIds($court);
                                        $outNames = [];
                                        foreach ([$leftA, $leftB, $rightA, $rightB] as $outId) {
                                            if ($outId > 0 && !playerWillPlay(['id' => $outId], $week_statuses) && isset($names[$outId])) {
                                                $outNames[] = $names[$outId];
                                            }
                                        }
                                        ?>
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
                                                <?php if ($outNames): ?>
                                                    <div class="small text-danger no-print"><?php echo htmlspecialchars(implode(', ', $outNames)); ?> marked not playing</div>
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
        const ladderMovement = <?php echo $rankByResults ? 'true' : 'false'; ?>;
        function refreshDivision(division) {
            const rows = document.querySelectorAll('.player-row[data-division="' + division + '"]');
            let regulars = 0;
            let playing = 0;
            let open = 0;
            rows.forEach(function (row) {
                if (row.dataset.role !== 'regular') return;
                regulars++;
                const out = row.querySelector('.week-status-select').value === 'not playing';
                row.classList.toggle('player-out', out);
                if (out) open++; else playing++;
            });
            const summary = document.querySelector('.division-summary[data-division="' + division + '"]');
            if (summary) {
                summary.textContent = playing + ' of ' + regulars + (regulars === 1 ? ' regular playing.' : ' regulars playing.');
            }
            let filled = 0;
            rows.forEach(function (row) {
                if (row.dataset.role !== 'substitute') return;
                const willing = row.querySelector('.week-status-select').value !== 'not playing';
                const takesSpot = ladderMovement ? willing : (willing && filled < open);
                row.classList.toggle('player-out', !takesSpot);
                if (!ladderMovement && takesSpot) filled++;
            });
            const status = document.querySelector('.sub-status[data-division="' + division + '"]');
            if (!status) return;
            if (ladderMovement) {
                status.textContent = 'A substitute plays only when a court would have fewer than 4 of its regulars.';
                return;
            }
            if (open === 0) {
                status.textContent = 'No open spots until a regular is marked not playing.';
            } else if (filled === 0) {
                status.textContent = open + (open === 1 ? ' open spot. No substitute is set to play.' : ' open spots. No substitutes are set to play.');
            } else {
                status.textContent = filled + ' of ' + open + (open === 1 ? ' open spot filled.' : ' open spots filled.');
            }
        }
        document.querySelectorAll('.week-status-select').forEach(function (select) {
            select.addEventListener('change', function () {
                const row = select.closest('.player-row');
                if (row) refreshDivision(row.dataset.division);
            });
        });
        document.querySelectorAll('.division-summary').forEach(function (summary) {
            refreshDivision(summary.dataset.division);
        });
        function markCourtConflicts() {
            const editor = document.getElementById('court-editor');
            if (!editor) return;
            editor.querySelectorAll('.slot-select, .court-location').forEach(function (field) {
                field.classList.remove('slot-conflict');
            });
            const players = {};
            const courts = {};
            editor.querySelectorAll('.court-edit').forEach(function (row) {
                const time = row.querySelector('.court-time').value;
                const court = row.querySelector('.court-location').value.trim().toLowerCase();
                if (court) {
                    const courtKey = time + '|' + court;
                    (courts[courtKey] = courts[courtKey] || []).push(row.querySelector('.court-location'));
                }
                row.querySelectorAll('.slot-select').forEach(function (select) {
                    if (!select.value) return;
                    const key = time + '|' + select.value;
                    (players[key] = players[key] || []).push(select);
                });
            });
            Object.keys(players).forEach(function (key) {
                if (players[key].length > 1) {
                    players[key].forEach(function (select) { select.classList.add('slot-conflict'); });
                }
            });
            Object.keys(courts).forEach(function (key) {
                if (courts[key].length > 1) {
                    courts[key].forEach(function (field) { field.classList.add('slot-conflict'); });
                }
            });
        }
        const courtEditor = document.getElementById('court-editor');
        if (courtEditor) {
            courtEditor.addEventListener('change', markCourtConflicts);
            courtEditor.addEventListener('input', markCourtConflicts);
            markCourtConflicts();
        }
    </script>
</body>
</html>
