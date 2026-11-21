<?php
/**
 * Build a Friday night of doubles, 7:00–9:00.
 * Court numbers come from that night's list. Divisions stay on their own courts.
 * 24 or fewer players use 20-minute games (six rounds). More than 24 use 15-minute games (eight rounds).
 * Courts are split so players in each division get as close to the same number of games as possible, using every court those players can fill.
 * Inside a division, game counts stay within one of each other. A player with fewer games is not left sitting while someone with more games goes on.
 * Among players with the same number of games, courts are filled with foursomes that have shared a court as few times as possible, so the same group is not sent out again while another grouping is available.
 * Whoever has been sitting longest comes back on next when that does not rebuild a group, and a spare game goes to whoever has played fewer games this season.
 * Repeat partners are used only when every other pairing would repeat one too.
 */

function defaultCourtNumbers($playDate) {
    if ($playDate === '2026-11-20') {
        return [3, 4, 5, 6, 7];
    }
    return [1, 2, 3, 4, 5];
}

function parseCourtNumbers($text) {
    $text = str_replace(['–', '—'], '-', trim((string)$text));
    if ($text === '') {
        return [];
    }
    if (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $text, $match)) {
        $start = (int)$match[1];
        $end = (int)$match[2];
        if ($start < 1 || $end < $start || $end - $start > 11) {
            return [];
        }
        $numbers = [];
        for ($number = $start; $number <= $end; $number++) {
            $numbers[] = $number;
        }
        return $numbers;
    }
    if (!preg_match_all('/\d+/', $text, $matches)) {
        return [];
    }
    $numbers = [];
    foreach ($matches[0] as $raw) {
        $number = (int)$raw;
        if ($number < 1 || $number > 99 || in_array($number, $numbers, true)) {
            continue;
        }
        $numbers[] = $number;
    }
    if (!$numbers || count($numbers) > 12) {
        return [];
    }
    return $numbers;
}

function formatCourtNumbers(array $numbers) {
    $numbers = array_values(array_map('intval', $numbers));
    if (!$numbers) {
        return '';
    }
    $contiguous = count($numbers) > 1;
    for ($i = 1; $i < count($numbers); $i++) {
        if ($numbers[$i] !== $numbers[$i - 1] + 1) {
            $contiguous = false;
            break;
        }
    }
    if ($contiguous) {
        return $numbers[0] . '-' . $numbers[count($numbers) - 1];
    }
    return implode(', ', $numbers);
}

function courtsFromLocations(array $locations) {
    $numbers = [];
    foreach ($locations as $location) {
        if (preg_match('/(\d+)/', (string)$location, $match)) {
            $number = (int)$match[1];
            if ($number > 0) {
                $numbers[$number] = $number;
            }
        }
    }
    $numbers = array_values($numbers);
    sort($numbers, SORT_NUMERIC);
    return $numbers;
}

function courtNumbersForDate($playDate, array $savedNumbers) {
    $savedNumbers = array_values(array_unique(array_map('intval', $savedNumbers)));
    sort($savedNumbers, SORT_NUMERIC);
    if ($playDate === '2026-11-20' && (!$savedNumbers || $savedNumbers === [1, 2, 3, 4, 5])) {
        return [3, 4, 5, 6, 7];
    }
    if ($savedNumbers) {
        return $savedNumbers;
    }
    return defaultCourtNumbers($playDate);
}

function courtSessionLength($playerCount) {
    if ((int)$playerCount <= 24) {
        return ['minutes' => 20, 'rounds' => 6];
    }
    return ['minutes' => 15, 'rounds' => 8];
}

function previousPlayDate(array $playDates, $playDate) {
    $previous = null;
    foreach ($playDates as $date) {
        if ($date >= $playDate) {
            break;
        }
        $previous = $date;
    }
    return $previous;
}

function playDateAwaitingScores($conn, $seasonId, $playDate) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS games, SUM(status = 'completed') AS done FROM pickleball_games WHERE season_id = ? AND DATE(date) = ? AND player1_id IS NOT NULL");
    $stmt->bind_param("is", $seasonId, $playDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $games = (int)($row['games'] ?? 0);
    $done = (int)($row['done'] ?? 0);
    return $games === 0 || $done < $games;
}

function seasonResultRecords($conn, $seasonId, $playDate = null) {
    $games = [];
    if ($playDate) {
        $stmt = $conn->prepare("SELECT * FROM pickleball_games WHERE season_id = ? AND status = 'completed' AND DATE(date) = ?");
        $stmt->bind_param("is", $seasonId, $playDate);
    } else {
        $stmt = $conn->prepare("SELECT * FROM pickleball_games WHERE season_id = ? AND status = 'completed'");
        $stmt->bind_param("i", $seasonId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $games[(int)$row['id']] = $row;
    }
    $stmt->close();
    if (!$games) {
        return [];
    }
    $idList = implode(',', array_map('intval', array_keys($games)));
    $assignments = [];
    $assignmentResult = $conn->query("SELECT game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id FROM pickleball_match_assignments WHERE game_id IN ($idList)");
    if ($assignmentResult) {
        while ($row = $assignmentResult->fetch_assoc()) {
            $assignments[(int)$row['game_id']][(int)$row['match_number']] = $row;
        }
    }
    $records = [];
    $add = function ($playerId, $scored, $allowed, $won) use (&$records) {
        $playerId = (int)$playerId;
        if ($playerId <= 0) {
            return;
        }
        if (!isset($records[$playerId])) {
            $records[$playerId] = ['wins' => 0, 'losses' => 0, 'point_differential' => 0, 'points_scored' => 0];
        }
        $records[$playerId]['points_scored'] += $scored;
        $records[$playerId]['point_differential'] += $scored - $allowed;
        if ($won === 1) {
            $records[$playerId]['wins']++;
        } elseif ($won === -1) {
            $records[$playerId]['losses']++;
        }
    };
    foreach ($games as $gameId => $game) {
        $matches = $assignments[$gameId] ?? [];
        foreach ($matches as $matchNumber => $assignment) {
            $team1Key = 'match' . $matchNumber . '_team1_score';
            $team2Key = 'match' . $matchNumber . '_team2_score';
            if (!array_key_exists($team1Key, $game) || $game[$team1Key] === null || $game[$team2Key] === null) {
                continue;
            }
            $team1 = (int)$game[$team1Key];
            $team2 = (int)$game[$team2Key];
            $team1Result = $team1 === $team2 ? 0 : ($team1 > $team2 ? 1 : -1);
            $team2Result = $team1Result === 0 ? 0 : -$team1Result;
            foreach ([$assignment['team1_player1_id'], $assignment['team1_player2_id']] as $playerId) {
                $add($playerId, $team1, $team2, $team1Result);
            }
            foreach ([$assignment['team2_player1_id'], $assignment['team2_player2_id']] as $playerId) {
                $add($playerId, $team2, $team1, $team2Result);
            }
        }
    }
    return $records;
}

function rankPlayerIds(array $playerIds, array $records, array $names) {
    $rows = [];
    foreach ($playerIds as $playerId) {
        $playerId = (int)$playerId;
        $record = $records[$playerId] ?? ['wins' => 0, 'losses' => 0, 'point_differential' => 0];
        $record['name'] = $names[$playerId] ?? '';
        $record['id'] = $playerId;
        $rows[] = $record;
    }
    usort($rows, 'pickleballRankCompare');
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['id'];
    }
    return $ids;
}

function rankingCourtGroups(array $rankedIds, array $courtNumbers) {
    $rankedIds = array_values(array_map('intval', $rankedIds));
    $courtNumbers = array_values(array_map('intval', $courtNumbers));
    sort($courtNumbers, SORT_NUMERIC);
    $people = count($rankedIds);
    $usable = min(count($courtNumbers), intdiv($people, 4));
    if ($usable < 1) {
        return [];
    }
    $courtNumbers = array_slice($courtNumbers, 0, $usable);
    $base = intdiv($people, $usable);
    $extra = $people % $usable;
    $groups = [];
    $offset = 0;
    foreach ($courtNumbers as $index => $number) {
        $size = $base + ($index < $extra ? 1 : 0);
        $groups[] = [
            'court' => $number,
            'player_ids' => array_slice($rankedIds, $offset, $size),
        ];
        $offset += $size;
    }
    return $groups;
}

function buildRankedNightPlan(array $rankedIds, array $courtNumbers) {
    $groups = rankingCourtGroups($rankedIds, $courtNumbers);
    $session = courtSessionLength(count($rankedIds));
    $minutes = $session['minutes'];
    $roundCount = $session['rounds'];
    $rounds = [];
    for ($round = 0; $round < $roundCount; $round++) {
        $rounds[$round] = [
            'time' => sprintf('%02d:%02d:00', 19 + intdiv($round * $minutes, 60), ($round * $minutes) % 60),
            'minutes' => $minutes,
            'courts' => [],
            'sitting' => [],
        ];
    }
    foreach ($groups as $group) {
        $plan = planDivision($group['player_ids'], 1, [], $roundCount);
        foreach ($plan as $round => $roundPlan) {
            foreach ($roundPlan['courts'] as $game) {
                $game['court'] = 'Court ' . $group['court'];
                $game['division_id'] = null;
                $rounds[$round]['courts'][] = $game;
            }
        }
    }
    foreach ($rounds as $round => $roundPlan) {
        usort($rounds[$round]['courts'], function ($a, $b) {
            return strnatcmp($a['court'], $b['court']);
        });
    }
    return $rounds;
}

function buildGroupedNightPlan(array $groups) {
    $seen = [];
    foreach ($groups as $group) {
        foreach ($group['player_ids'] as $playerId) {
            $seen[(int)$playerId] = true;
        }
    }
    $session = courtSessionLength(count($seen));
    $minutes = $session['minutes'];
    $roundCount = $session['rounds'];
    $rounds = [];
    for ($round = 0; $round < $roundCount; $round++) {
        $rounds[$round] = [
            'time' => sprintf('%02d:%02d:00', 19 + intdiv($round * $minutes, 60), ($round * $minutes) % 60),
            'minutes' => $minutes,
            'courts' => [],
            'sitting' => [],
        ];
    }
    foreach ($groups as $group) {
        $plan = planDivision($group['player_ids'], 1, [], $roundCount);
        foreach ($plan as $round => $roundPlan) {
            foreach ($roundPlan['courts'] as $game) {
                $game['court'] = 'Court ' . $group['court'];
                $game['division_id'] = null;
                $rounds[$round]['courts'][] = $game;
            }
        }
    }
    foreach ($rounds as $round => $roundPlan) {
        usort($rounds[$round]['courts'], function ($a, $b) {
            return strnatcmp($a['court'], $b['court']);
        });
    }
    return $rounds;
}

function movementAllowance($played) {
    $played = (int)$played;
    if ($played < 4) {
        return 0;
    }
    if ($played <= 6) {
        return 1;
    }
    return 2;
}

function compareCourtNightResult(array $a, array $b) {
    $byWins = ((int)($b['wins'] ?? 0)) <=> ((int)($a['wins'] ?? 0));
    if ($byWins !== 0) {
        return $byWins;
    }
    $byDiff = ((int)($b['point_differential'] ?? 0)) <=> ((int)($a['point_differential'] ?? 0));
    if ($byDiff !== 0) {
        return $byDiff;
    }
    $byScored = ((int)($b['points_scored'] ?? 0)) <=> ((int)($a['points_scored'] ?? 0));
    if ($byScored !== 0) {
        return $byScored;
    }
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
}

function courtNightCutTied(array $a, array $b) {
    return (int)($a['wins'] ?? 0) === (int)($b['wins'] ?? 0)
        && (int)($a['point_differential'] ?? 0) === (int)($b['point_differential'] ?? 0);
}

function rankCourtNightRows(array $playerIds, array $results, array $names) {
    $rows = [];
    foreach ($playerIds as $playerId) {
        $playerId = (int)$playerId;
        if (!isset($results[$playerId])) {
            continue;
        }
        $row = $results[$playerId];
        $row['id'] = $playerId;
        $row['name'] = $names[$playerId] ?? '';
        $rows[] = $row;
    }
    usort($rows, 'compareCourtNightResult');
    return $rows;
}

/**
 * Players clearly on the move side of the cut. A tie on wins and point differential stays put.
 * From the bottom, the worst player is first.
 */
function clearCourtMovers(array $rankedBestFirst, $want, $fromBottom) {
    $count = count($rankedBestFirst);
    $want = min(max(0, (int)$want), $count);
    if ($want === 0) {
        return [];
    }
    if (!$fromBottom) {
        if ($want < $count && courtNightCutTied($rankedBestFirst[$want - 1], $rankedBestFirst[$want])) {
            $bound = $rankedBestFirst[$want - 1];
            $ids = [];
            foreach ($rankedBestFirst as $row) {
                if (courtNightCutTied($row, $bound)) {
                    break;
                }
                $ids[] = (int)$row['id'];
            }
            return $ids;
        }
        $ids = [];
        for ($i = 0; $i < $want; $i++) {
            $ids[] = (int)$rankedBestFirst[$i]['id'];
        }
        return $ids;
    }
    $start = $count - $want;
    if ($start > 0 && courtNightCutTied($rankedBestFirst[$start - 1], $rankedBestFirst[$start])) {
        $bound = $rankedBestFirst[$start];
        $ids = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            if (courtNightCutTied($rankedBestFirst[$i], $bound)) {
                break;
            }
            $ids[] = (int)$rankedBestFirst[$i]['id'];
        }
        return $ids;
    }
    $ids = [];
    for ($i = $count - 1; $i >= $start; $i--) {
        $ids[] = (int)$rankedBestFirst[$i]['id'];
    }
    return $ids;
}

function applyRungSwaps(array $rungLists, array $results, array $names) {
    $dest = [];
    $up = 0;
    $pairs = count($rungLists) - 1;
    for ($i = 0; $i < $pairs; $i++) {
        $upper = rankCourtNightRows($rungLists[$i], $results, $names);
        $lower = rankCourtNightRows($rungLists[$i + 1], $results, $names);
        $want = min(movementAllowance(count($upper)), movementAllowance(count($lower)));
        if ($want < 1) {
            continue;
        }
        $down = clearCourtMovers($upper, $want, true);
        $promote = clearCourtMovers($lower, $want, false);
        $count = min(count($down), count($promote));
        for ($k = 0; $k < $count; $k++) {
            $dest[$down[$k]] = $i + 1;
            $dest[$promote[$k]] = $i;
            $up++;
        }
    }
    $next = array_fill(0, count($rungLists), []);
    foreach ($rungLists as $index => $ids) {
        foreach ($ids as $playerId) {
            $playerId = (int)$playerId;
            $next[$dest[$playerId] ?? $index][] = $playerId;
        }
    }
    return [$next, $up];
}

function packPlayerGroups(array $orderedIds, array $courtNumbers) {
    $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
    $courtNumbers = array_values(array_unique(array_map('intval', $courtNumbers)));
    sort($courtNumbers, SORT_NUMERIC);
    $people = count($orderedIds);
    if ($people < 4 || !$courtNumbers) {
        return [];
    }
    $usable = max((int)ceil($people / 8), min(intdiv($people, 4), count($courtNumbers)));
    $nextNumber = $courtNumbers[count($courtNumbers) - 1];
    while (count($courtNumbers) < $usable) {
        $nextNumber++;
        $courtNumbers[] = $nextNumber;
    }
    $courtNumbers = array_slice($courtNumbers, 0, $usable);
    $base = intdiv($people, $usable);
    $extra = $people % $usable;
    $groups = [];
    $offset = 0;
    foreach ($courtNumbers as $index => $number) {
        $size = $base + ($index < $extra ? 1 : 0);
        $groups[] = [
            'court' => $number,
            'player_ids' => array_slice($orderedIds, $offset, $size),
        ];
        $offset += $size;
    }
    return $groups;
}

function addPlayersToOpenRungs(array $groups, array $playerIds) {
    $waiting = [];
    foreach ($playerIds as $playerId) {
        $playerId = (int)$playerId;
        $placed = false;
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if (count($groups[$i]['player_ids']) < 8) {
                $groups[$i]['player_ids'][] = $playerId;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $waiting[] = $playerId;
        }
    }
    return [$groups, $waiting];
}

function seedCourtGroups(array $playedOrdered, array $idleOrdered, array $courtNumbers) {
    $playedOrdered = array_values(array_map('intval', $playedOrdered));
    $idleOrdered = array_values(array_map('intval', $idleOrdered));
    if (count($playedOrdered) < 4) {
        return [packPlayerGroups(array_merge($playedOrdered, $idleOrdered), $courtNumbers), []];
    }
    return addPlayersToOpenRungs(packPlayerGroups($playedOrdered, $courtNumbers), $idleOrdered);
}

function placeNewCourtRegulars(array $rungLists, array $newcomerIds, array $preferredIndex, array $twoMiss, array $names) {
    $bumped = [];
    $waiting = [];
    $usedBump = [];
    foreach ($newcomerIds as $playerId) {
        $playerId = (int)$playerId;
        $index = $preferredIndex[$playerId] ?? null;
        if ($index === null || !isset($rungLists[$index])) {
            $index = count($rungLists) - 1;
        }
        if ($index < 0) {
            $waiting[] = $playerId;
            continue;
        }
        if (count($rungLists[$index]) < 8) {
            $rungLists[$index][] = $playerId;
            continue;
        }
        $bumpId = null;
        $bumpName = null;
        foreach ($rungLists[$index] as $existing) {
            $existing = (int)$existing;
            if (empty($twoMiss[$existing]) || !empty($usedBump[$existing])) {
                continue;
            }
            $name = $names[$existing] ?? '';
            if ($bumpId === null || strcasecmp($name, (string)$bumpName) < 0) {
                $bumpId = $existing;
                $bumpName = $name;
            }
        }
        if ($bumpId === null) {
            $waiting[] = $playerId;
            continue;
        }
        $usedBump[$bumpId] = true;
        $rungLists[$index] = array_values(array_filter($rungLists[$index], function ($existing) use ($bumpId) {
            return (int)$existing !== $bumpId;
        }));
        $rungLists[$index][] = $playerId;
        $bumped[] = $bumpId;
    }
    return [$rungLists, $bumped, $waiting];
}

function numberCourtRungs(array $rungLists, array $courtNumbers) {
    $courtNumbers = array_values(array_unique(array_map('intval', $courtNumbers)));
    sort($courtNumbers, SORT_NUMERIC);
    if (!$courtNumbers) {
        $courtNumbers = [1];
    }
    $extended = false;
    $nextNumber = $courtNumbers[count($courtNumbers) - 1];
    while (count($courtNumbers) < count($rungLists)) {
        $nextNumber++;
        $courtNumbers[] = $nextNumber;
        $extended = true;
    }
    $groups = [];
    foreach ($rungLists as $index => $ids) {
        $groups[] = [
            'court' => $courtNumbers[$index],
            'player_ids' => array_values(array_unique(array_map('intval', $ids))),
        ];
    }
    return [$groups, $extended];
}

function fillCourtPlayingGroups(array $rungLists, array $willPlay, array $subsByIndex, array $subNames) {
    $usedSubs = [];
    $rosters = [];
    $subCount = 0;
    foreach ($rungLists as $index => $ids) {
        $playing = [];
        foreach ($ids as $playerId) {
            $playerId = (int)$playerId;
            if (!empty($willPlay[$playerId])) {
                $playing[] = $playerId;
            }
        }
        if (count($playing) < 4) {
            $preferred = [];
            foreach ($subsByIndex[$index] ?? [] as $subId) {
                $subId = (int)$subId;
                if (isset($subNames[$subId])) {
                    $preferred[] = $subId;
                }
            }
            $rest = [];
            foreach ($subNames as $subId => $name) {
                $subId = (int)$subId;
                if (!in_array($subId, $preferred, true)) {
                    $rest[] = $subId;
                }
            }
            $byName = function ($a, $b) use ($subNames) {
                return strcasecmp((string)($subNames[$a] ?? ''), (string)($subNames[$b] ?? ''));
            };
            usort($preferred, $byName);
            usort($rest, $byName);
            foreach (array_merge($preferred, $rest) as $subId) {
                if (count($playing) >= 4) {
                    break;
                }
                if (!empty($usedSubs[$subId]) || empty($willPlay[$subId])) {
                    continue;
                }
                $playing[] = $subId;
                $usedSubs[$subId] = true;
                $subCount++;
            }
        }
        $rosters[] = count($playing) >= 4 ? $playing : [];
    }
    return [$rosters, $subCount];
}

/**
 * Keep regulars on a court and swap neighbors from one night's results.
 * The first results night seeds the courts. Later nights swap 1 or 2.
 * A substitute fills a court only up to 4 and does not join it.
 */
function planLadderCourts(array $spec) {
    $names = $spec['names'] ?? [];
    $results = $spec['results'] ?? [];
    $willPlay = $spec['willPlay'] ?? [];
    $courtNumbers = $spec['courtNumbers'] ?? [];
    $regularIds = array_values(array_map('intval', $spec['regularIds'] ?? []));
    $regularSet = array_fill_keys($regularIds, true);
    $twoMiss = $spec['twoMiss'] ?? [];
    $subNames = $spec['subNames'] ?? [];
    $offRungCourts = $spec['offRungCourts'] ?? [];
    $summary = [
        'seed' => false,
        'up' => 0,
        'bumped' => [],
        'waiting' => [],
        'subs' => 0,
        'short' => [],
        'extended' => false,
    ];

    $rungLists = [];
    $previousNumbers = [];
    foreach ($spec['previousRungs'] ?? [] as $rung) {
        $ids = [];
        foreach ($rung['player_ids'] as $playerId) {
            $playerId = (int)$playerId;
            if (!empty($regularSet[$playerId])) {
                $ids[] = $playerId;
            }
        }
        if ($ids) {
            $rungLists[] = $ids;
            $previousNumbers[] = (int)$rung['court'];
        }
    }

    if (!$rungLists) {
        $summary['seed'] = true;
        [$groups, $waiting] = seedCourtGroups($spec['playedOrdered'] ?? [], $spec['idleOrdered'] ?? [], $courtNumbers);
        $summary['waiting'] = $waiting;
        $givenCourts = array_values(array_unique(array_map('intval', $courtNumbers)));
        $summary['extended'] = count($groups) > count($givenCourts);
        $numbered = $groups;
    } else {
        [$rungLists, $up] = applyRungSwaps($rungLists, $results, $names);
        $summary['up'] = $up;
        $onRung = [];
        foreach ($rungLists as $ids) {
            foreach ($ids as $playerId) {
                $onRung[$playerId] = true;
            }
        }
        $newcomers = [];
        $preferred = [];
        foreach ($regularIds as $playerId) {
            if (!empty($onRung[$playerId])) {
                continue;
            }
            $newcomers[] = $playerId;
            $preferred[$playerId] = null;
            foreach ($previousNumbers as $index => $number) {
                if (in_array($playerId, $offRungCourts[$number] ?? [], true)) {
                    $preferred[$playerId] = $index;
                    break;
                }
            }
        }
        usort($newcomers, function ($a, $b) use ($preferred, $names) {
            $aHas = $preferred[$a] !== null;
            $bHas = $preferred[$b] !== null;
            if ($aHas !== $bHas) {
                return $aHas ? -1 : 1;
            }
            return strcasecmp((string)($names[$a] ?? ''), (string)($names[$b] ?? ''));
        });
        [$rungLists, $bumped, $waiting] = placeNewCourtRegulars($rungLists, $newcomers, $preferred, $twoMiss, $names);
        $summary['bumped'] = $bumped;
        $summary['waiting'] = $waiting;
        [$numbered, $extended] = numberCourtRungs($rungLists, $courtNumbers);
        $summary['extended'] = $extended;
    }

    $subsByIndex = [];
    if ($summary['seed']) {
        foreach ($numbered as $index => $group) {
            $subsByIndex[$index] = $offRungCourts[$group['court']] ?? [];
        }
    } else {
        foreach ($previousNumbers as $index => $number) {
            $subsByIndex[$index] = $offRungCourts[$number] ?? [];
        }
    }
    $listsForFill = [];
    foreach ($numbered as $group) {
        $listsForFill[] = $group['player_ids'];
    }
    [$rosters, $subCount] = fillCourtPlayingGroups($listsForFill, $willPlay, $subsByIndex, $subNames);
    $summary['subs'] = $subCount;
    $playing = [];
    $saved = [];
    foreach ($numbered as $index => $group) {
        if ($group['player_ids']) {
            $saved[] = $group;
        }
        if (empty($rosters[$index])) {
            if ($group['player_ids']) {
                $summary['short'][] = $group['court'];
            }
            continue;
        }
        $playing[] = [
            'court' => $group['court'],
            'player_ids' => $rosters[$index],
        ];
    }
    return [
        'playing' => $playing,
        'rungs' => $saved,
        'summary' => $summary,
    ];
}

function ensurePickleballCourtRungs($conn) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $result = $conn->query("SHOW TABLES LIKE 'pickleball_court_rungs'");
    if ($result && $result->num_rows > 0) {
        $ready = true;
        return true;
    }
    $ready = $conn->query("
        CREATE TABLE IF NOT EXISTS `pickleball_court_rungs` (
          `id` int NOT NULL AUTO_INCREMENT,
          `season_id` int NOT NULL,
          `play_date` date NOT NULL,
          `player_id` int NOT NULL,
          `court_number` int NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `season_date_player` (`season_id`, `play_date`, `player_id`),
          KEY `player_id` (`player_id`),
          CONSTRAINT `fk_pickleball_court_rungs_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
          CONSTRAINT `fk_pickleball_court_rungs_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ") === true;
    return $ready;
}

function loadLatestCourtRungs($conn, $seasonId, $beforeDate) {
    if (!ensurePickleballCourtRungs($conn)) {
        return null;
    }
    $stmt = $conn->prepare("SELECT MAX(play_date) AS play_date FROM pickleball_court_rungs WHERE season_id = ? AND play_date < ?");
    $stmt->bind_param("is", $seasonId, $beforeDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $playDate = $row['play_date'] ?? null;
    if (!$playDate) {
        return ['play_date' => null, 'rungs' => []];
    }
    $stmt = $conn->prepare("SELECT player_id, court_number FROM pickleball_court_rungs WHERE season_id = ? AND play_date = ? ORDER BY court_number, player_id");
    $stmt->bind_param("is", $seasonId, $playDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byCourt = [];
    while ($player = $result->fetch_assoc()) {
        $byCourt[(int)$player['court_number']][] = (int)$player['player_id'];
    }
    $stmt->close();
    ksort($byCourt, SORT_NUMERIC);
    $rungs = [];
    foreach ($byCourt as $court => $playerIds) {
        $rungs[] = ['court' => $court, 'player_ids' => $playerIds];
    }
    return ['play_date' => $playDate, 'rungs' => $rungs];
}

function saveCourtRungs($conn, $seasonId, $playDate, array $rungs) {
    if (!ensurePickleballCourtRungs($conn)) {
        throw new RuntimeException('Could not store the court groups.');
    }
    $delete = $conn->prepare("DELETE FROM pickleball_court_rungs WHERE season_id = ? AND play_date = ?");
    $delete->bind_param("is", $seasonId, $playDate);
    $delete->execute();
    $delete->close();
    $insert = $conn->prepare("INSERT INTO pickleball_court_rungs (season_id, play_date, player_id, court_number) VALUES (?, ?, ?, ?)");
    foreach ($rungs as $rung) {
        $court = (int)$rung['court'];
        foreach ($rung['player_ids'] as $playerId) {
            $playerId = (int)$playerId;
            $insert->bind_param("isii", $seasonId, $playDate, $playerId, $court);
            $insert->execute();
        }
    }
    $insert->close();
}

function loadCourtRungsForDate($conn, $seasonId, $playDate) {
    if (!ensurePickleballCourtRungs($conn)) {
        return [];
    }
    $stmt = $conn->prepare("SELECT player_id, court_number FROM pickleball_court_rungs WHERE season_id = ? AND play_date = ?");
    $stmt->bind_param("is", $seasonId, $playDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $rungs = [];
    while ($row = $result->fetch_assoc()) {
        $rungs[(int)$row['player_id']] = (int)$row['court_number'];
    }
    $stmt->close();
    return $rungs;
}

function loadSingleCourtAppearances($conn, $seasonId, $playDate) {
    $stmt = $conn->prepare("
        SELECT location, division_id, player1_id, player2_id, player3_id, player4_id
        FROM pickleball_games
        WHERE season_id = ? AND DATE(date) = ? AND status <> 'cancelled' AND player1_id IS NOT NULL
    ");
    $stmt->bind_param("is", $seasonId, $playDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $courts = [];
    $divisionNight = false;
    while ($row = $result->fetch_assoc()) {
        if ((int)$row['division_id'] > 0) {
            $divisionNight = true;
        }
        if (!preg_match('/(\d+)/', (string)$row['location'], $match)) {
            continue;
        }
        $number = (int)$match[1];
        foreach (['player1_id', 'player2_id', 'player3_id', 'player4_id'] as $column) {
            $playerId = (int)$row[$column];
            if ($playerId > 0) {
                $courts[$playerId][$number] = $number;
            }
        }
    }
    $stmt->close();
    $single = [];
    $multiple = [];
    foreach ($courts as $playerId => $numbers) {
        if (count($numbers) === 1) {
            $single[$playerId] = (int)array_key_first($numbers);
        } else {
            $multiple[$playerId] = true;
        }
    }
    return ['single' => $single, 'multiple' => $multiple, 'division_night' => $divisionNight];
}

function courtGroupPlanner(array $games, array $players) {
    $isSub = [];
    foreach ($players as $player) {
        $isSub[(int)$player['id']] = !empty($player['is_substitute']);
    }
    $members = [];
    foreach ($games as $game) {
        $location = (string)$game['location'];
        foreach ($game['slots'] as $playerId) {
            $playerId = (int)$playerId;
            if ($playerId > 0) {
                $members[$location][$playerId] = true;
            }
        }
    }
    $locationIds = [];
    $nextId = 1;
    foreach (array_keys($members) as $location) {
        $locationIds[$location] = $nextId;
        $nextId++;
    }
    $divisions = [];
    foreach ($members as $location => $ids) {
        $regulars = [];
        $subs = [];
        foreach (array_keys($ids) as $playerId) {
            if (!empty($isSub[$playerId])) {
                $subs[] = $playerId;
            } else {
                $regulars[] = $playerId;
            }
        }
        $divisions[] = [
            'division_id' => $locationIds[$location],
            'regulars' => $regulars,
            'substitutes' => $subs,
        ];
    }
    foreach ($games as $index => $game) {
        $games[$index]['division_id'] = $locationIds[(string)$game['location']] ?? 0;
    }
    return [$games, $divisions];
}

function allocateDivisionCourts(array $counts, $totalCourts = 5, $rounds = 8) {
    $eligible = [];
    foreach ($counts as $divisionId => $playerCount) {
        $playerCount = (int)$playerCount;
        if ($playerCount >= 4) {
            $eligible[$divisionId] = [
                'players' => $playerCount,
                'max' => min($totalCourts, intdiv($playerCount, 4)),
            ];
        }
    }
    if (!$eligible) {
        return [];
    }

    $best = null;
    $ids = array_keys($eligible);
    $search = function ($index, $used, array $assign) use (&$search, &$best, $ids, $eligible, $totalCourts, $rounds) {
        if ($index === count($ids)) {
            $rates = [];
            foreach ($assign as $id => $courts) {
                $rates[] = ($courts * $rounds * 4) / $eligible[$id]['players'];
            }
            $spread = max($rates) - min($rates);
            $moreCourts = $best === null || $used > $best['used'];
            $sameCourtsCloser = $best !== null
                && $used === $best['used']
                && $spread < $best['spread'] - 0.0001;
            if ($moreCourts || $sameCourtsCloser) {
                $best = ['spread' => $spread, 'used' => $used, 'assign' => $assign];
            }
            return;
        }
        $id = $ids[$index];
        $maxHere = min($eligible[$id]['max'], $totalCourts - $used);
        for ($courts = 0; $courts <= $maxHere; $courts++) {
            $next = $assign;
            $next[$id] = $courts;
            $search($index + 1, $used + $courts, $next);
        }
    };
    $search(0, 0, []);
    return $best['assign'];
}

function buildNightPlan(array $divisions, array $courtNumbers = []) {
    $counts = [];
    $playerCount = 0;
    foreach ($divisions as $division) {
        $count = count($division['player_ids']);
        $counts[$division['division_id']] = $count;
        $playerCount += $count;
    }
    $courtNumbers = array_values(array_map('intval', $courtNumbers));
    if (!$courtNumbers) {
        $courtNumbers = [1, 2, 3, 4, 5];
    }
    $session = courtSessionLength($playerCount);
    $minutes = $session['minutes'];
    $roundCount = $session['rounds'];
    $courtCounts = allocateDivisionCourts($counts, count($courtNumbers), $roundCount);

    $nextCourt = 0;
    $divisionCourts = [];
    foreach ($divisions as $division) {
        $divisionId = $division['division_id'];
        $owned = [];
        $howMany = $courtCounts[$divisionId] ?? 0;
        for ($i = 0; $i < $howMany && $nextCourt < count($courtNumbers); $i++) {
            $owned[] = $courtNumbers[$nextCourt];
            $nextCourt++;
        }
        $divisionCourts[$divisionId] = $owned;
    }

    $rounds = [];
    for ($round = 0; $round < $roundCount; $round++) {
        $rounds[$round] = [
            'time' => sprintf('%02d:%02d:00', 19 + intdiv($round * $minutes, 60), ($round * $minutes) % 60),
            'minutes' => $minutes,
            'courts' => [],
            'sitting' => [],
        ];
    }

    foreach ($divisions as $division) {
        $divisionId = $division['division_id'];
        $courts = $divisionCourts[$divisionId] ?? [];
        if (!$courts) {
            for ($round = 0; $round < $roundCount; $round++) {
                $rounds[$round]['sitting'][$divisionId] = $division['player_ids'];
            }
            continue;
        }
        $plan = planDivision($division['player_ids'], count($courts), $division['season_games'] ?? [], $roundCount);
        foreach ($plan as $round => $roundPlan) {
            foreach ($roundPlan['courts'] as $index => $game) {
                $game['court'] = 'Court ' . $courts[$index];
                $game['division_id'] = $divisionId;
                $rounds[$round]['courts'][] = $game;
            }
            $rounds[$round]['sitting'][$divisionId] = $roundPlan['sitting'];
        }
    }

    foreach ($rounds as $round => $roundPlan) {
        usort($rounds[$round]['courts'], function ($a, $b) {
            return strnatcmp($a['court'], $b['court']);
        });
    }
    return $rounds;
}

function planDivision(array $playerIds, $courtCount, array $seasonGames = [], $roundCount = 8) {
    $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
    $state = [
        'games' => [],
        'wait' => [],
        'partners' => [],
        'opponents' => [],
        'together' => [],
        'groups' => [],
    ];
    foreach ($playerIds as $playerId) {
        $state['games'][$playerId] = 0;
        $state['wait'][$playerId] = 0;
    }

    $rounds = [];
    $roundCount = max(1, (int)$roundCount);
    for ($round = 0; $round < $roundCount; $round++) {
        $courtsThisRound = min($courtCount, intdiv(count($playerIds), 4));
        [$active, $sitting] = selectActive($playerIds, $courtsThisRound * 4, $state, $seasonGames);
        $courts = assignCourts($active, $courtsThisRound, $state);
        foreach ($courts as $game) {
            $players = $game['players'];
            foreach ($game['pairs'] as $pair) {
                rememberPair($state['partners'], $pair[0], $pair[1]);
            }
            foreach ($game['pairs'][0] as $playerA) {
                foreach ($game['pairs'][1] as $playerB) {
                    rememberPair($state['opponents'], $playerA, $playerB);
                }
            }
            foreach ($players as $index => $playerId) {
                for ($other = $index + 1; $other < count($players); $other++) {
                    rememberPair($state['together'], $playerId, $players[$other]);
                }
            }
            $groupKey = groupKey($players);
            $state['groups'][$groupKey] = ($state['groups'][$groupKey] ?? 0) + 1;
            foreach ($players as $playerId) {
                $state['games'][$playerId]++;
                $state['wait'][$playerId] = 0;
            }
        }
        foreach ($sitting as $playerId) {
            $state['wait'][$playerId]++;
        }
        $rounds[] = ['courts' => $courts, 'sitting' => array_values($sitting)];
    }
    return $rounds;
}

function selectActive(array $playerIds, $needed, array $state, array $seasonGames) {
    $playerIds = array_values($playerIds);
    if ($needed <= 0) {
        return [[], $playerIds];
    }
    if ($needed >= count($playerIds)) {
        return [$playerIds, []];
    }
    usort($playerIds, function ($a, $b) use ($state) {
        if ($state['games'][$a] !== $state['games'][$b]) {
            return $state['games'][$a] <=> $state['games'][$b];
        }
        return $a <=> $b;
    });
    $cutoff = $state['games'][$playerIds[$needed - 1]];
    $must = [];
    $band = [];
    $later = [];
    foreach ($playerIds as $playerId) {
        $games = $state['games'][$playerId];
        if ($games < $cutoff) {
            $must[] = $playerId;
        } elseif ($games === $cutoff) {
            $band[] = $playerId;
        } else {
            $later[] = $playerId;
        }
    }
    $chosen = $must;
    $needMore = $needed - count($chosen);
    while ($needMore > 0 && $band) {
        $bestId = null;
        $bestRank = null;
        foreach ($band as $playerId) {
            $together = 0;
            foreach ($chosen as $otherId) {
                $together += $state['together'][$playerId][$otherId] ?? 0;
            }
            $rank = [$together, -($state['wait'][$playerId] ?? 0), $seasonGames[$playerId] ?? 0, $playerId];
            if ($bestRank === null || $rank < $bestRank) {
                $bestRank = $rank;
                $bestId = $playerId;
            }
        }
        $chosen[] = $bestId;
        $band = array_values(array_diff($band, [$bestId]));
        $needMore--;
    }
    return [$chosen, array_merge($band, $later)];
}

function assignCourts(array $playerIds, $courtCount, array $state) {
    $remaining = array_values($playerIds);
    $courts = [];
    for ($court = 0; $court < $courtCount; $court++) {
        $game = bestGroup($remaining, $state);
        if (!$game) {
            break;
        }
        $courts[] = $game;
        $remaining = array_values(array_diff($remaining, $game['players']));
    }
    $passes = 0;
    $improved = true;
    while ($improved && $passes < 40) {
        $improved = false;
        $passes++;
        $courtTotal = count($courts);
        for ($i = 0; $i < $courtTotal; $i++) {
            for ($j = $i + 1; $j < $courtTotal; $j++) {
                for ($leftIndex = 0; $leftIndex < 4; $leftIndex++) {
                    for ($rightIndex = 0; $rightIndex < 4; $rightIndex++) {
                        $left = $courts[$i]['players'];
                        $right = $courts[$j]['players'];
                        $hold = $left[$leftIndex];
                        $left[$leftIndex] = $right[$rightIndex];
                        $right[$rightIndex] = $hold;
                        $newLeft = scoreGroup($left, $state);
                        $newRight = scoreGroup($right, $state);
                        if ($newLeft['score'] + $newRight['score'] < $courts[$i]['score'] + $courts[$j]['score']) {
                            $courts[$i] = $newLeft;
                            $courts[$j] = $newRight;
                            $improved = true;
                        }
                    }
                }
            }
        }
    }
    return $courts;
}

function bestGroup(array $remaining, array $state) {
    $remaining = array_values($remaining);
    $count = count($remaining);
    if ($count < 4) {
        return null;
    }
    $best = null;
    for ($a = 0; $a < $count - 3; $a++) {
        for ($b = $a + 1; $b < $count - 2; $b++) {
            for ($c = $b + 1; $c < $count - 1; $c++) {
                for ($d = $c + 1; $d < $count; $d++) {
                    $candidate = scoreGroup([
                        $remaining[$a], $remaining[$b], $remaining[$c], $remaining[$d]
                    ], $state);
                    if ($best === null || $candidate['score'] < $best['score']) {
                        $best = $candidate;
                    }
                }
            }
        }
    }
    return $best;
}

function scoreGroup(array $ids, array $state) {
    $ids = array_values($ids);
    $pairings = [
        [[$ids[0], $ids[1]], [$ids[2], $ids[3]]],
        [[$ids[0], $ids[2]], [$ids[1], $ids[3]]],
        [[$ids[0], $ids[3]], [$ids[1], $ids[2]]],
    ];
    $groupRepeats = $state['groups'][groupKey($ids)] ?? 0;
    $shared = 0;
    for ($i = 0; $i < 4; $i++) {
        for ($j = $i + 1; $j < 4; $j++) {
            $shared += $state['together'][$ids[$i]][$ids[$j]] ?? 0;
        }
    }
    $best = null;
    foreach ($pairings as $pairs) {
        $partnerRepeats = 0;
        foreach ($pairs as $pair) {
            $partnerRepeats += $state['partners'][$pair[0]][$pair[1]] ?? 0;
        }
        $opponentRepeats = 0;
        foreach ($pairs[0] as $playerA) {
            foreach ($pairs[1] as $playerB) {
                $opponentRepeats += $state['opponents'][$playerA][$playerB] ?? 0;
            }
        }
        $gameLoad = 0;
        foreach ($ids as $playerId) {
            $gameLoad += $state['games'][$playerId] ?? 0;
        }
        $score = ($groupRepeats * 100000) + ($partnerRepeats * 1000) + ($shared * 100) + ($opponentRepeats * 10) + $gameLoad;
        if ($best === null || $score < $best['score']) {
            $best = [
                'score' => $score,
                'pairs' => $pairs,
                'repeat_partner' => $partnerRepeats > 0,
                'players' => [$pairs[0][0], $pairs[0][1], $pairs[1][0], $pairs[1][1]],
            ];
        }
    }
    return $best;
}

function groupKey(array $ids) {
    $ids = array_map('intval', $ids);
    sort($ids);
    return implode('-', $ids);
}

function rememberPair(array &$map, $a, $b) {
    $map[$a][$b] = ($map[$a][$b] ?? 0) + 1;
    $map[$b][$a] = ($map[$b][$a] ?? 0) + 1;
}

/**
 * Swap players who cannot play out of an existing night.
 * Everyone else keeps the same time, court, and partner.
 * Each open spot goes to a regular who is sitting that round and still within one game of the division.
 * A designated substitute plays only when no regular can, and only while that substitute still has the fewest games in the division.
 */
function patchAbsentPlayers(array $games, array $divisions, array $willPlay) {
    $pools = [];
    foreach ($divisions as $division) {
        $divisionId = (int)$division['division_id'];
        $subs = [];
        foreach ($division['substitutes'] as $playerId) {
            $playerId = (int)$playerId;
            if (!empty($willPlay[$playerId])) {
                $subs[] = $playerId;
            }
        }
        $regulars = [];
        foreach ($division['regulars'] as $playerId) {
            $playerId = (int)$playerId;
            if (!empty($willPlay[$playerId])) {
                $regulars[] = $playerId;
            }
        }
        $pools[$divisionId] = ['substitutes' => $subs, 'regulars' => $regulars];
    }

    $working = [];
    $meta = [];
    foreach ($games as $game) {
        if (($game['status'] ?? 'scheduled') !== 'scheduled') {
            continue;
        }
        $slots = [];
        foreach (array_values($game['slots']) as $playerId) {
            $slots[] = (int)$playerId;
        }
        if (count($slots) !== 4) {
            continue;
        }
        $id = (int)$game['id'];
        $working[$id] = $slots;
        $meta[$id] = [
            'time' => (string)($game['time'] ?? ''),
            'location' => (string)($game['location'] ?? ''),
            'division_id' => (int)($game['division_id'] ?? 0),
        ];
    }

    $busy = [];
    foreach ($working as $id => $slots) {
        foreach ($slots as $playerId) {
            if ($playerId > 0) {
                $busy[$meta[$id]['time']][$playerId] = true;
            }
        }
    }

    $replacements = [];
    $record = function ($out, $in) use (&$replacements) {
        $key = $out . ':' . $in;
        if (!isset($replacements[$key])) {
            $replacements[$key] = ['out' => $out, 'in' => $in, 'games' => 0];
        }
        $replacements[$key]['games']++;
    };
    $countGames = function ($playerId) use (&$working) {
        $count = 0;
        foreach ($working as $slots) {
            foreach ($slots as $slotId) {
                if ($slotId === $playerId) {
                    $count++;
                }
            }
        }
        return $count;
    };
    $assignSlot = function ($gameId, $index, $out, $in) use (&$working, &$busy, $meta, $record) {
        $time = $meta[$gameId]['time'];
        $working[$gameId][$index] = $in;
        unset($busy[$time][$out]);
        if ($in > 0) {
            $busy[$time][$in] = true;
        }
        $record($out, $in);
    };
    $regularFloor = function ($divisionId) use (&$pools, $countGames) {
        $ids = $pools[$divisionId]['regulars'] ?? [];
        if (!$ids) {
            $ids = $pools[$divisionId]['substitutes'] ?? [];
        }
        $floor = null;
        foreach ($ids as $playerId) {
            $games = $countGames($playerId);
            if ($floor === null || $games < $floor) {
                $floor = $games;
            }
        }
        return $floor === null ? 0 : $floor;
    };
    $rankOf = [];
    foreach ($pools as $divisionId => $pool) {
        foreach ($pool['substitutes'] as $index => $playerId) {
            $rankOf[$divisionId][$playerId] = [0, $index];
        }
        foreach ($pool['regulars'] as $index => $playerId) {
            if (!isset($rankOf[$divisionId][$playerId])) {
                $rankOf[$divisionId][$playerId] = [1, $index];
            }
        }
    }

    $gameIds = array_keys($working);
    usort($gameIds, function ($a, $b) use ($meta) {
        if ($meta[$a]['time'] === $meta[$b]['time']) {
            return $a <=> $b;
        }
        return strcmp($meta[$a]['time'], $meta[$b]['time']);
    });

    $unfilled = [];
    foreach ($gameIds as $gameId) {
        $divisionId = $meta[$gameId]['division_id'];
        $regulars = $pools[$divisionId]['regulars'] ?? [];
        $subs = $pools[$divisionId]['substitutes'] ?? [];
        $hasRegulars = (bool)$regulars;
        foreach ($working[$gameId] as $index => $playerId) {
            if ($playerId <= 0 || !empty($willPlay[$playerId])) {
                continue;
            }
            $time = $meta[$gameId]['time'];
            $floor = $regularFloor($divisionId);
            $chosen = null;
            $bestRank = null;
            $sitting = 0;
            $regularBlocked = false;
            $subBlocked = false;
            foreach (array_merge($regulars, $subs) as $candidate) {
                if (in_array($candidate, $working[$gameId], true) || !empty($busy[$time][$candidate])) {
                    continue;
                }
                $sitting++;
                $games = $countGames($candidate);
                $isSub = $hasRegulars && in_array($candidate, $subs, true);
                if ($isSub && count($regulars) >= 4) {
                    $subBlocked = true;
                    continue;
                }
                if ($isSub && $games >= $floor) {
                    $subBlocked = true;
                    continue;
                }
                if (!$isSub && $games > $floor) {
                    $regularBlocked = true;
                    continue;
                }
                $rank = [$isSub ? 1 : 0, $games, $rankOf[$divisionId][$candidate][1], $candidate];
                if ($bestRank === null || $rank < $bestRank) {
                    $bestRank = $rank;
                    $chosen = $candidate;
                }
            }
            if ($chosen === null) {
                $reason = 'busy';
                if ($regularBlocked) {
                    $reason = 'ahead';
                } elseif ($subBlocked) {
                    $reason = 'sub';
                }
                $unfilled[] = [
                    'game_id' => $gameId,
                    'player_id' => $playerId,
                    'time' => $time,
                    'location' => $meta[$gameId]['location'],
                    'reason' => $sitting > 0 ? $reason : 'busy',
                ];
                continue;
            }
            $assignSlot($gameId, $index, $playerId, $chosen);
        }
    }

    return [
        'slots' => $working,
        'replacements' => array_values($replacements),
        'unfilled' => $unfilled,
    ];
}

/**
 * Move extra games to players who are sitting.
 * Regulars stay within one game of each other.
 * A substitute keeps the fewest games in the division.
 * Only the open slot changes.
 */
function rebalanceFairNight(array $games, array $divisions, array $willPlay) {
    $pools = [];
    $isSub = [];
    foreach ($divisions as $division) {
        $divisionId = (int)$division['division_id'];
        $subs = [];
        foreach ($division['substitutes'] as $playerId) {
            $playerId = (int)$playerId;
            if (!empty($willPlay[$playerId])) {
                $subs[] = $playerId;
                $isSub[$playerId] = true;
            }
        }
        $regulars = [];
        foreach ($division['regulars'] as $playerId) {
            $playerId = (int)$playerId;
            if (!empty($willPlay[$playerId])) {
                $regulars[] = $playerId;
                $isSub[$playerId] = false;
            }
        }
        $pools[$divisionId] = ['substitutes' => $subs, 'regulars' => $regulars];
    }

    $working = [];
    $meta = [];
    foreach ($games as $game) {
        if (($game['status'] ?? 'scheduled') !== 'scheduled') {
            continue;
        }
        $slots = [];
        foreach (array_values($game['slots']) as $playerId) {
            $slots[] = (int)$playerId;
        }
        if (count($slots) !== 4) {
            continue;
        }
        $id = (int)$game['id'];
        $working[$id] = $slots;
        $meta[$id] = [
            'time' => (string)($game['time'] ?? ''),
            'division_id' => (int)($game['division_id'] ?? 0),
        ];
    }

    $busy = [];
    foreach ($working as $id => $slots) {
        foreach ($slots as $playerId) {
            if ($playerId > 0) {
                $busy[$meta[$id]['time']][$playerId] = true;
            }
        }
    }

    $countGames = function ($playerId) use (&$working) {
        $count = 0;
        foreach ($working as $slots) {
            foreach ($slots as $slotId) {
                if ($slotId === $playerId) {
                    $count++;
                }
            }
        }
        return $count;
    };

    $replacements = [];
    $record = function ($out, $in) use (&$replacements) {
        $key = $out . ':' . $in;
        if (!isset($replacements[$key])) {
            $replacements[$key] = ['out' => $out, 'in' => $in, 'games' => 0];
        }
        $replacements[$key]['games']++;
    };

    $gameIds = array_keys($working);
    usort($gameIds, function ($a, $b) use ($meta) {
        if ($meta[$a]['time'] === $meta[$b]['time']) {
            return $a <=> $b;
        }
        return strcmp($meta[$a]['time'], $meta[$b]['time']);
    });

    for ($guard = 0; $guard < 200; $guard++) {
        $swap = null;
        foreach ($pools as $divisionId => $pool) {
            $regulars = $pool['regulars'];
            if (!$regulars) {
                continue;
            }
            $floor = null;
            foreach ($regulars as $playerId) {
                $gamesPlayed = $countGames($playerId);
                if ($floor === null || $gamesPlayed < $floor) {
                    $floor = $gamesPlayed;
                }
            }
            $donors = [];
            foreach ($regulars as $playerId) {
                $gamesPlayed = $countGames($playerId);
                if ($gamesPlayed > $floor + 1) {
                    $donors[] = [$gamesPlayed - ($floor + 1), $gamesPlayed, $playerId];
                }
            }
            foreach ($pool['substitutes'] as $playerId) {
                $gamesPlayed = $countGames($playerId);
                if ($gamesPlayed > $floor) {
                    $donors[] = [$gamesPlayed - $floor, $gamesPlayed, $playerId];
                }
            }
            usort($donors, function ($a, $b) {
                if ($a[0] !== $b[0]) {
                    return $b[0] <=> $a[0];
                }
                if ($a[1] !== $b[1]) {
                    return $b[1] <=> $a[1];
                }
                return $a[2] <=> $b[2];
            });
            foreach ($donors as $donorInfo) {
                $donor = $donorInfo[2];
                $best = null;
                $bestRank = null;
                foreach ($gameIds as $gameId) {
                    if ($meta[$gameId]['division_id'] !== $divisionId) {
                        continue;
                    }
                    $index = array_search($donor, $working[$gameId], true);
                    if ($index === false) {
                        continue;
                    }
                    $time = $meta[$gameId]['time'];
                    foreach ($regulars as $listIndex => $recipient) {
                        if ($recipient === $donor) {
                            continue;
                        }
                        if (in_array($recipient, $working[$gameId], true) || !empty($busy[$time][$recipient])) {
                            continue;
                        }
                        $gamesPlayed = $countGames($recipient);
                        if ($gamesPlayed > $floor) {
                            continue;
                        }
                        $rank = [$gamesPlayed, $listIndex, $recipient, $meta[$gameId]['time'], $gameId];
                        if ($bestRank === null || $rank < $bestRank) {
                            $bestRank = $rank;
                            $best = [$gameId, $index, $recipient];
                        }
                    }
                }
                if ($best === null) {
                    continue;
                }
                $swap = [$best[0], $best[1], $donor, $best[2], $meta[$best[0]]['time']];
                break 2;
            }
        }
        if ($swap === null) {
            break;
        }
        [$gameId, $index, $donor, $recipient, $time] = $swap;
        $working[$gameId][$index] = $recipient;
        unset($busy[$time][$donor]);
        $busy[$time][$recipient] = true;
        $record($donor, $recipient);
    }

    return [
        'slots' => $working,
        'replacements' => array_values($replacements),
    ];
}
