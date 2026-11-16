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
