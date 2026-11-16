<?php
/**
 * Per-player view of one night: games, court times, time spent waiting, and repeat partners.
 * A round is 20 minutes when that night's games are 20 minutes apart, and 15 minutes otherwise.
 * Waiting is a round where that player is not on a court.
 * Partners are the two players on a side (player 1 with 2, player 3 with 4).
 */

function summarizePickleballNights(array $games) {
    $nights = [];
    foreach ($games as $game) {
        if (($game['status'] ?? '') === 'cancelled') {
            continue;
        }
        $names = [];
        foreach ([1, 2, 3, 4, 5, 6, 7] as $slot) {
            $id = (int)($game['player' . $slot . '_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $names[$id] = $game['player' . $slot . '_name'] ?? ('Player ' . $id);
        }
        if (!$names) {
            continue;
        }

        $when = strtotime($game['date']);
        $day = date('Y-m-d', $when);
        $clock = date('H:i:s', $when);
        if (!isset($nights[$day])) {
            $nights[$day] = [
                'date' => $day,
                'slots' => [],
                'players' => [],
                'repeats' => [],
                'notes' => [],
            ];
        }
        if (!empty($game['notes'])) {
            $nights[$day]['notes'][] = $game['notes'];
        }
        $nights[$day]['slots'][$clock] = true;
        $timeLabel = date('g:i A', $when);
        $court = trim((string)($game['location'] ?? ''));
        $playingLabel = $timeLabel . ($court !== '' ? ' ' . $court : '');

        foreach ($names as $id => $name) {
            if (!isset($nights[$day]['players'][$id])) {
                $nights[$day]['players'][$id] = [
                    'name' => $name,
                    'division' => '',
                    'games' => 0,
                    'playing' => [],
                    'slots' => [],
                    'partners' => [],
                ];
            }
            $player = &$nights[$day]['players'][$id];
            $player['games']++;
            if ($player['division'] === '' && !empty($game['division_name'])) {
                $player['division'] = $game['division_name'];
            }
            if (!isset($player['slots'][$clock])) {
                $player['slots'][$clock] = [];
            }
            $player['slots'][$clock][] = $playingLabel;
            unset($player);
        }

        $pairs = [];
        if (!empty($game['player1_id']) && !empty($game['player2_id'])) {
            $pairs[] = [(int)$game['player1_id'], (int)$game['player2_id']];
        }
        if (!empty($game['player3_id']) && !empty($game['player4_id'])) {
            $pairs[] = [(int)$game['player3_id'], (int)$game['player4_id']];
        }
        foreach ($pairs as $pair) {
            [$left, $right] = $pair;
            if (!isset($names[$left], $names[$right])) {
                continue;
            }
            $nights[$day]['players'][$left]['partners'][$right]['name'] = $names[$right];
            $nights[$day]['players'][$left]['partners'][$right]['times'][] = $when;
            $nights[$day]['players'][$right]['partners'][$left]['name'] = $names[$left];
            $nights[$day]['players'][$right]['partners'][$left]['times'][] = $when;
        }
    }

    ksort($nights);
    $summaries = [];
    foreach ($nights as $night) {
        $slotTimes = array_keys($night['slots']);
        sort($slotTimes);
        $roundMinutes = nightRoundMinutes($slotTimes, $night['notes']);
        $rows = [];
        $repeatLines = [];
        $seenPairs = [];
        foreach ($night['players'] as $id => $player) {
            $playing = [];
            foreach ($slotTimes as $slot) {
                if (!isset($player['slots'][$slot])) {
                    continue;
                }
                foreach ($player['slots'][$slot] as $label) {
                    $playing[] = $label;
                }
            }
            $waitTimes = [];
            foreach ($slotTimes as $slot) {
                if (!isset($player['slots'][$slot])) {
                    $waitTimes[] = date('g:i A', strtotime($night['date'] . ' ' . $slot));
                }
            }
            $repeats = [];
            foreach ($player['partners'] as $partnerId => $partner) {
                if (count($partner['times']) < 2) {
                    continue;
                }
                $times = $partner['times'];
                sort($times);
                $timeLabels = array_map(function ($time) {
                    return date('g:i A', $time);
                }, $times);
                $repeats[] = $partner['name'] . ' (' . implode(', ', $timeLabels) . ')';
                $pairKey = min($id, $partnerId) . '-' . max($id, $partnerId);
                if (!isset($seenPairs[$pairKey])) {
                    $seenPairs[$pairKey] = true;
                    $namesInOrder = [$player['name'], $partner['name']];
                    sort($namesInOrder, SORT_NATURAL | SORT_FLAG_CASE);
                    $repeatLines[] = $namesInOrder[0] . ' & ' . $namesInOrder[1] . ' (' . implode(', ', $timeLabels) . ')';
                }
            }
            $rows[] = [
                'name' => $player['name'],
                'division' => $player['division'],
                'games' => $player['games'],
                'playing' => $playing,
                'wait_minutes' => count($waitTimes) * $roundMinutes,
                'wait_times' => $waitTimes,
                'repeats' => $repeats,
            ];
        }
        usort($rows, function ($a, $b) {
            $division = strcasecmp($a['division'], $b['division']);
            if ($division !== 0) {
                return $division;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        sort($repeatLines, SORT_NATURAL | SORT_FLAG_CASE);
        $summaries[] = [
            'date' => $night['date'],
            'round_minutes' => $roundMinutes,
            'rows' => $rows,
            'repeats' => $repeatLines,
        ];
    }
    return $summaries;
}

function nightRoundMinutes(array $slotTimes, array $notes) {
    foreach ($notes as $note) {
        if (strpos((string)$note, '20-minute') !== false) {
            return 20;
        }
    }
    if (count($slotTimes) >= 2) {
        $allTwenty = true;
        for ($i = 1; $i < count($slotTimes); $i++) {
            $gap = (int)round((strtotime('2000-01-01 ' . $slotTimes[$i]) - strtotime('2000-01-01 ' . $slotTimes[$i - 1])) / 60);
            if ($gap !== 20) {
                $allTwenty = false;
                break;
            }
        }
        if ($allTwenty) {
            return 20;
        }
    }
    return 15;
}
