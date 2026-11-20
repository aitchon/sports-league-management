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
