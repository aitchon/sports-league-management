<?php

function pickleballPublicBaseUrl() {
    $base = getenv('PUBLIC_BASE_URL');
    if ($base) {
        return rtrim($base, '/');
    }
    return 'https://itchonfamily.com/fastdetroit';
}

function pickleballEnsureColumn($conn, $table, $column, $alterSql) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }
    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === $column) {
                return true;
            }
        }
    }
    return $conn->query($alterSql) === true;
}

function pickleballEnsureSmsTables($conn) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $settings = $conn->query("
        CREATE TABLE IF NOT EXISTS pickleball_sms_settings (
          id int NOT NULL AUTO_INCREMENT,
          provider enum('twilio','nexmo','textbelt','custom') NOT NULL DEFAULT 'twilio',
          api_key varchar(255) DEFAULT NULL,
          api_secret varchar(255) DEFAULT NULL,
          from_number varchar(20) DEFAULT NULL,
          webhook_url varchar(500) DEFAULT NULL,
          cron_key varchar(64) DEFAULT NULL,
          is_active tinyint NOT NULL DEFAULT 0,
          created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $notifications = $conn->query("
        CREATE TABLE IF NOT EXISTS pickleball_sms_notifications (
          id int NOT NULL AUTO_INCREMENT,
          template_id int DEFAULT NULL,
          player_id int DEFAULT NULL,
          phone_number varchar(20) NOT NULL,
          message text NOT NULL,
          status enum('pending','sent','failed','delivered') NOT NULL DEFAULT 'pending',
          error_message text DEFAULT NULL,
          message_id varchar(64) DEFAULT NULL,
          kind varchar(32) DEFAULT NULL,
          play_date date DEFAULT NULL,
          sent_at timestamp NULL DEFAULT NULL,
          delivered_at timestamp NULL DEFAULT NULL,
          created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          season_id int DEFAULT NULL,
          game_id int DEFAULT NULL,
          PRIMARY KEY (id),
          KEY player_id (player_id),
          KEY message_id (message_id),
          KEY weekly_lookup (player_id, play_date, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if ($settings !== true || $notifications !== true) {
        $ready = false;
        return false;
    }
    pickleballEnsureColumn($conn, 'pickleball_sms_settings', 'cron_key', "ALTER TABLE pickleball_sms_settings ADD COLUMN cron_key varchar(64) DEFAULT NULL");
    pickleballEnsureColumn($conn, 'pickleball_sms_notifications', 'message_id', "ALTER TABLE pickleball_sms_notifications ADD COLUMN message_id varchar(64) DEFAULT NULL");
    pickleballEnsureColumn($conn, 'pickleball_sms_notifications', 'kind', "ALTER TABLE pickleball_sms_notifications ADD COLUMN kind varchar(32) DEFAULT NULL");
    pickleballEnsureColumn($conn, 'pickleball_sms_notifications', 'play_date', "ALTER TABLE pickleball_sms_notifications ADD COLUMN play_date date DEFAULT NULL");
    $ready = true;
    return true;
}

function pickleballSmsSettings($conn) {
    if (!pickleballEnsureSmsTables($conn)) {
        return null;
    }
    $result = $conn->query("SELECT * FROM pickleball_sms_settings ORDER BY id ASC LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row) {
        $key = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO pickleball_sms_settings (provider, is_active, cron_key) VALUES ('twilio', 0, ?)");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
        $result = $conn->query("SELECT * FROM pickleball_sms_settings ORDER BY id ASC LIMIT 1");
        $row = $result ? $result->fetch_assoc() : null;
    }
    if ($row && empty($row['cron_key'])) {
        $key = bin2hex(random_bytes(16));
        $id = (int)$row['id'];
        $stmt = $conn->prepare("UPDATE pickleball_sms_settings SET cron_key = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $key, $id);
            $stmt->execute();
            $stmt->close();
            $row['cron_key'] = $key;
        }
    }
    return $row;
}

function pickleballSaveTwilioSettings($conn, $accountSid, $authToken, $fromNumber) {
    $row = pickleballSmsSettings($conn);
    if (!$row) {
        return false;
    }
    $id = (int)$row['id'];
    $stmt = $conn->prepare("UPDATE pickleball_sms_settings SET provider = 'twilio', api_key = ?, api_secret = ?, from_number = ?, is_active = 1 WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sssi', $accountSid, $authToken, $fromNumber, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function pickleballSeasonFridays(array $season) {
    $fridays = [];
    if (empty($season['start_date']) || empty($season['end_date'])) {
        return $fridays;
    }
    $cursor = new DateTime($season['start_date']);
    $end = new DateTime($season['end_date']);
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
    return $fridays;
}

function pickleballCurrentPlayDate(array $season, $today = null) {
    $fridays = pickleballSeasonFridays($season);
    $today = $today ?: (new DateTime('today'))->format('Y-m-d');
    foreach ($fridays as $friday) {
        if ($friday >= $today) {
            return $friday;
        }
    }
    return $fridays ? $fridays[count($fridays) - 1] : '';
}

function pickleballWeeklyWindowOpen($playDate, $today = null) {
    if ($playDate === '') {
        return false;
    }
    $today = $today ?: (new DateTime('today'))->format('Y-m-d');
    $play = new DateTime($playDate);
    $monday = clone $play;
    $monday->modify('monday this week');
    $todayDt = new DateTime($today);
    return $todayDt >= $monday && $todayDt->format('Y-m-d') <= $play->format('Y-m-d');
}

function pickleballNormalizePhone($phone) {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) {
        return '';
    }
    return '+1' . $digits;
}

function pickleballFirstName($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return 'there';
    }
    $parts = preg_split('/\s+/', $name);
    return $parts[0] !== '' ? $parts[0] : 'there';
}

function pickleballSmsPlayers($conn, $seasonId) {
    $substitute = pickleballHasSubstituteColumn($conn) ? 'p.is_substitute' : '0 AS is_substitute';
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.phone, p.is_active, {$substitute}, d.name AS division_name
        FROM pickleball_players p
        LEFT JOIN pickleball_divisions d ON p.division_id = d.id
        WHERE p.season_id = ? AND p.is_active = 1
        ORDER BY p.name
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $seasonId);
    $stmt->execute();
    $result = $stmt->get_result();
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_substitute'] = (int)($row['is_substitute'] ?? 0);
        $row['sms_phone'] = pickleballNormalizePhone($row['phone'] ?? '');
        $players[] = $row;
    }
    $stmt->close();
    return $players;
}

function pickleballConfirmToken($playerId, $playDate, $secret) {
    $payload = (int)$playerId . '.' . $playDate;
    $sig = substr(hash_hmac('sha256', $payload, $secret), 0, 24);
    return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
}

function pickleballParseConfirmToken($token, $secret) {
    $token = trim((string)$token);
    $padded = strtr($token, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $raw = base64_decode($padded, true);
    if (!$raw || !preg_match('/^(\d+)\.(\d{4}-\d{2}-\d{2})\.([a-f0-9]{24})$/', $raw, $match)) {
        return null;
    }
    $payload = $match[1] . '.' . $match[2];
    $expected = substr(hash_hmac('sha256', $payload, $secret), 0, 24);
    if (!hash_equals($expected, $match[3])) {
        return null;
    }
    return ['player_id' => (int)$match[1], 'play_date' => $match[2]];
}

function pickleballConfirmUrl($playerId, $playDate, $secret) {
    return pickleballPublicBaseUrl() . '/pickleball_confirm.php?t=' . pickleballConfirmToken($playerId, $playDate, $secret);
}

function pickleballWeeklyMessage($name, $playDate, $confirmUrl) {
    $when = date('l, F j', strtotime($playDate));
    return 'Hi ' . pickleballFirstName($name) . ', can you play pickleball ' . $when . ', 7:00-9:00 PM? Reply YES if you can play or NO if you can\'t. ' . $confirmUrl;
}

function pickleballParseAvailabilityReply($body) {
    $text = strtolower(trim((string)$body));
    $text = str_replace(['’', '‘'], "'", $text);
    if (preg_match("/\b(can't|cannot|cant|not playing|won't be there|count me out)\b/", $text)) {
        return 'not playing';
    }
    if (!preg_match('/[a-z]/', $text)) {
        return '';
    }
    $word = preg_split('/[^a-z\']+/', $text, 2)[0] ?? '';
    if (in_array($word, ['yes', 'y', 'yeah', 'yep', 'in', 'confirm', 'confirmed', 'playing'], true)) {
        return 'confirmed';
    }
    if (in_array($word, ['no', 'n', 'nope', 'out'], true)) {
        return 'not playing';
    }
    return '';
}

function pickleballWeeklyAlreadySent($conn, $playerId, $playDate) {
    $stmt = $conn->prepare("
        SELECT status, created_at
        FROM pickleball_sms_notifications
        WHERE player_id = ? AND play_date = ? AND kind = 'weekly_availability'
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $playerId, $playDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    if (in_array($row['status'], ['sent', 'delivered'], true)) {
        return true;
    }
    return $row['status'] === 'pending' && strtotime($row['created_at']) > time() - 1800;
}

function pickleballWeeklySentSummary($conn, $seasonId, $playDate) {
    $summary = ['sent_at' => null, 'count' => 0];
    $stmt = $conn->prepare("
        SELECT MAX(sent_at) AS sent_at, COUNT(*) AS recipient_count
        FROM pickleball_sms_notifications
        WHERE season_id = ? AND play_date = ? AND kind = 'weekly_availability' AND status IN ('sent', 'delivered')
    ");
    if (!$stmt) {
        return $summary;
    }
    $stmt->bind_param('is', $seasonId, $playDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && (int)$row['recipient_count'] > 0) {
        $summary['sent_at'] = $row['sent_at'];
        $summary['count'] = (int)$row['recipient_count'];
    }
    return $summary;
}

function pickleballSendWeeklyAvailability($conn, $sms, array $season, $force = false, $ignoreWindow = false) {
    $playDate = pickleballCurrentPlayDate($season);
    if ($playDate === '') {
        return ['ok' => false, 'summary' => 'This season has no Friday play date.'];
    }
    $when = date('l, F j', strtotime($playDate));
    if (!$ignoreWindow && !pickleballWeeklyWindowOpen($playDate)) {
        return ['ok' => true, 'summary' => 'No text sent. The weekly ask goes out Monday through Friday for ' . $when . '.'];
    }
    if (!$sms->isConfigured()) {
        return ['ok' => false, 'summary' => 'Add the Twilio account before sending texts.'];
    }
    $settings = pickleballSmsSettings($conn);
    if (!$settings || empty($settings['cron_key'])) {
        return ['ok' => false, 'summary' => 'The texting settings are not ready yet.'];
    }
    $players = pickleballSmsPlayers($conn, (int)$season['id']);
    $eligible = 0;
    foreach ($players as $player) {
        if (empty($player['is_substitute']) && $player['sms_phone'] !== '') {
            $eligible++;
        }
    }
    if ($eligible === 0) {
        return ['ok' => false, 'summary' => 'No regular players have a phone number, so the availability text was not sent.'];
    }
    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $errors = [];
    foreach ($players as $player) {
        if (!empty($player['is_substitute']) || $player['sms_phone'] === '') {
            continue;
        }
        if (!$force && pickleballWeeklyAlreadySent($conn, $player['id'], $playDate)) {
            $skipped++;
            continue;
        }
        $url = pickleballConfirmUrl($player['id'], $playDate, $settings['cron_key']);
        $body = pickleballWeeklyMessage($player['name'], $playDate, $url);
        $result = $sms->sendSMS($player['sms_phone'], $body, $player['id'], null, (int)$season['id'], null, 'weekly_availability', $playDate);
        if (!empty($result['success'])) {
            $sent++;
        } else {
            $failed++;
            if (count($errors) < 3) {
                $errors[] = $player['name'] . ': ' . ($result['error'] ?? 'could not send');
            }
        }
    }
    if ($sent === 0 && $failed === 0 && $skipped > 0) {
        return ['ok' => true, 'summary' => 'The availability text for ' . $when . ' was already sent to ' . $skipped . ' regulars.'];
    }
    $summary = 'Sent the ' . $when . ' availability text to ' . $sent . ' regular' . ($sent === 1 ? '' : 's') . '.';
    if ($skipped > 0) {
        $summary .= ' ' . $skipped . ' already had it.';
    }
    if ($failed > 0) {
        $summary .= ' ' . $failed . ' failed.';
        if ($errors) {
            $summary .= ' ' . implode(' ', $errors);
        }
    }
    return ['ok' => $failed === 0, 'summary' => $summary, 'sent' => $sent, 'failed' => $failed];
}

function pickleballPlayerByPhone($conn, $seasonId, $phone) {
    $target = pickleballNormalizePhone($phone);
    if ($target === '') {
        return null;
    }
    $match = null;
    foreach (pickleballSmsPlayers($conn, $seasonId) as $player) {
        if ($player['sms_phone'] !== $target) {
            continue;
        }
        if ($match === null || (!empty($match['is_substitute']) && empty($player['is_substitute']))) {
            $match = $player;
        }
    }
    return $match;
}

function pickleballReplyPlayDate($conn, $playerId, $fallback) {
    $stmt = $conn->prepare("
        SELECT play_date
        FROM pickleball_sms_notifications
        WHERE player_id = ? AND kind = 'weekly_availability' AND play_date IS NOT NULL AND status IN ('sent', 'delivered')
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['play_date'])) {
        return $fallback;
    }
    $cutoff = (new DateTime($row['play_date']))->modify('+2 days')->format('Y-m-d');
    $today = (new DateTime('today'))->format('Y-m-d');
    if ($today <= $cutoff) {
        return $row['play_date'];
    }
    return $fallback;
}

function pickleballTwilioSignatureValid($authToken, $url, array $params, $header) {
    if ($authToken === '' || $header === '') {
        return false;
    }
    ksort($params);
    $data = $url;
    foreach ($params as $key => $value) {
        $data .= $key . $value;
    }
    $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
    return hash_equals($expected, (string)$header);
}
