<?php
// db.php

// Include the function to load .env file
require_once 'loadEnv.php'; // Adjust the path as needed

// Check the value of APP_ENV after loading the .env file
$envFile = (__DIR__ . '/.env'); // Default to dev
if (getenv('APP_ENV') == 'production') {
    $envFile = (__DIR__ . '/.env.production');
}

// Load the correct .env file based on APP_ENV
loadEnv($envFile);

// Database configuration from the environment
$db_host = getenv('DB_HOST'); // Or $_ENV['DB_HOST']
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get current active season
function getCurrentSeason($conn) {
    $query = "SELECT * FROM seasons WHERE is_active = 1 LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Function to get all seasons
function getAllSeasons($conn) {
    $query = "SELECT * FROM seasons ORDER BY start_date DESC";
    $result = $conn->query($query);
    $seasons = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $seasons[] = $row;
        }
    }
    return $seasons;
}

// Function to get all volleyball seasons
function getAllVolleyballSeasons($conn) {
    $query = "SELECT * FROM volleyball_seasons ORDER BY start_date DESC";
    $result = $conn->query($query);
    $seasons = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $seasons[] = $row;
        }
    }
    return $seasons;
}

// Function to get volleyball season by ID
function getVolleyballSeasonById($conn, $season_id) {
    $stmt = $conn->prepare("SELECT * FROM volleyball_seasons WHERE id = ?");
    $stmt->bind_param("i", $season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Function to get season by ID
function getSeasonById($conn, $season_id) {
    $stmt = $conn->prepare("SELECT * FROM seasons WHERE id = ?");
    $stmt->bind_param("i", $season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Function to get teams by division (with option to include/exclude playoff placeholders)
function getTeamsByDivision($conn, $division_id, $season_id = null, $include_placeholders = true) {
    if ($season_id) {
        if ($include_placeholders) {
            $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM teams WHERE division_id = ? AND season_id = ? ORDER BY name");
            $stmt->bind_param("ii", $division_id, $season_id);
        } else {
            $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM teams WHERE division_id = ? AND season_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
            $stmt->bind_param("ii", $division_id, $season_id);
        }
    } else {
        if ($include_placeholders) {
            $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM teams WHERE division_id = ? ORDER BY name");
            $stmt->bind_param("i", $division_id);
        } else {
            $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM teams WHERE division_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
            $stmt->bind_param("i", $division_id);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    $stmt->close();
    return $teams;
}

// Public pickleball pages use ?season= to open an archived season.
// With no parameter, or an unknown id, the active season is used.
function getPickleballSeasonForRequest($conn) {
    $requested_id = isset($_GET['season']) ? (int)$_GET['season'] : 0;
    if ($requested_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE id = ?");
        $stmt->bind_param("i", $requested_id);
        $stmt->execute();
        $season = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($season) {
            return $season;
        }
    }

    $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Keep archive pages linked to the season being viewed. Active-season links stay clean.
function pickleballPublicHref($path, $season) {
    if ($season && empty($season['is_active'])) {
        $separator = (strpos($path, '?') === false) ? '?' : '&';
        return $path . $separator . 'season=' . (int)$season['id'];
    }
    return $path;
}

function pickleballHasSubstituteColumn($conn) {
    static $has = null;
    if ($has === null) {
        $result = $conn->query("SHOW COLUMNS FROM pickleball_players LIKE 'is_substitute'");
        $has = $result && $result->num_rows > 0;
    }
    return $has;
}

// Roster queries omit substitutes once the column exists. Before that migration, they stay unchanged.
function pickleballRosterSql($conn, $alias = 'p') {
    if (!pickleballHasSubstituteColumn($conn)) {
        return '';
    }
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
        $alias = 'p';
    }
    return " AND {$alias}.is_substitute = 0";
}

function pickleballNormalizeWeekStatus($status) {
    $status = strtolower(trim((string)$status));
    if ($status === 'confirmed' || $status === 'not playing') {
        return $status;
    }
    return 'not confirmed';
}

function pickleballWeekStatusLabel($status) {
    $status = pickleballNormalizeWeekStatus($status);
    if ($status === 'confirmed') {
        return 'Confirmed';
    }
    if ($status === 'not playing') {
        return 'Not playing';
    }
    return 'Not confirmed';
}

function pickleballWeekStatusClass($status) {
    $status = pickleballNormalizeWeekStatus($status);
    if ($status === 'confirmed') {
        return 'success';
    }
    if ($status === 'not playing') {
        return 'danger';
    }
    return 'secondary';
}

function pickleballEnsureWeekStatusTable($conn) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $result = $conn->query("SHOW TABLES LIKE 'pickleball_week_status'");
    if ($result && $result->num_rows > 0) {
        pickleballEnsurePlayerCascade($conn, 'pickleball_week_status', 'fk_pickleball_week_status_player');
        pickleballEnsurePlayerCascade($conn, 'pickleball_session_players', 'fk_pickleball_session_players_player');
        $ready = true;
        return true;
    }
    $ready = $conn->query("
        CREATE TABLE IF NOT EXISTS `pickleball_week_status` (
          `id` int NOT NULL AUTO_INCREMENT,
          `season_id` int NOT NULL,
          `play_date` date NOT NULL,
          `player_id` int NOT NULL,
          `status` enum('not confirmed','confirmed','not playing') NOT NULL DEFAULT 'not confirmed',
          PRIMARY KEY (`id`),
          UNIQUE KEY `season_date_player` (`season_id`, `play_date`, `player_id`),
          KEY `player_id` (`player_id`),
          CONSTRAINT `fk_pickleball_week_status_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
          CONSTRAINT `fk_pickleball_week_status_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ") === true;
    if ($ready) {
        pickleballEnsurePlayerCascade($conn, 'pickleball_week_status', 'fk_pickleball_week_status_player');
        pickleballEnsurePlayerCascade($conn, 'pickleball_session_players', 'fk_pickleball_session_players_player');
    }
    return $ready;
}

function pickleballEnsurePlayerCascade($conn, $table, $constraint) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $constraint)) {
        return;
    }
    $stmt = $conn->prepare("
        SELECT DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ss', $table, $constraint);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['DELETE_RULE'] === 'CASCADE') {
        return;
    }
    $conn->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    $conn->query("
        ALTER TABLE `{$table}`
        ADD CONSTRAINT `{$constraint}`
        FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
    ");
}

function pickleballClearPlayerOwnedRows($conn, $playerId) {
    $playerId = (int)$playerId;
    $tables = [
        'pickleball_week_status' => true,
        'pickleball_session_players' => true,
    ];
    $result = $conn->query("
        SELECT TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME = 'pickleball_players'
          AND REFERENCED_COLUMN_NAME = 'id'
          AND COLUMN_NAME = 'player_id'
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tables[$row['TABLE_NAME']] = true;
        }
    }
    foreach (array_keys($tables) as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            continue;
        }
        $quoted = $conn->real_escape_string($table);
        $exists = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$quoted}'");
        if (!$exists || $exists->num_rows === 0) {
            continue;
        }
        $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE player_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $playerId);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }
    return true;
}

function pickleballLoadWeekStatuses($conn, $seasonId) {
    $statuses = [];
    if (!pickleballEnsureWeekStatusTable($conn)) {
        return $statuses;
    }
    $stmt = $conn->prepare("SELECT play_date, player_id, status FROM pickleball_week_status WHERE season_id = ?");
    $stmt->bind_param("i", $seasonId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['play_date'];
        $statuses[$date][(int)$row['player_id']] = pickleballNormalizeWeekStatus($row['status']);
    }
    $stmt->close();
    return $statuses;
}

function pickleballSaveWeekStatuses($conn, $seasonId, $playDate, array $playerIds, array $posted) {
    if (!pickleballEnsureWeekStatusTable($conn)) {
        return false;
    }
    $stmt = $conn->prepare("
        INSERT INTO pickleball_week_status (season_id, play_date, player_id, status)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = ?
    ");
    if (!$stmt) {
        return false;
    }
    foreach ($playerIds as $playerId) {
        $playerId = (int)$playerId;
        $status = pickleballNormalizeWeekStatus($posted[$playerId] ?? ($posted[(string)$playerId] ?? 'not confirmed'));
        $stmt->bind_param("isiss", $seasonId, $playDate, $playerId, $status, $status);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }
    $stmt->close();
    return true;
}

function pickleballRankCompare(array $a, array $b) {
    $aWins = (int)($a['wins'] ?? 0);
    $bWins = (int)($b['wins'] ?? 0);
    $aGames = $aWins + (int)($a['losses'] ?? 0);
    $bGames = $bWins + (int)($b['losses'] ?? 0);
    if (($aGames > 0) !== ($bGames > 0)) {
        return $aGames > 0 ? -1 : 1;
    }
    if ($aGames > 0 && $bGames > 0) {
        $byWin = ($bWins * $aGames) <=> ($aWins * $bGames);
        if ($byWin !== 0) {
            return $byWin;
        }
        $aDiff = (int)($a['point_differential'] ?? 0);
        $bDiff = (int)($b['point_differential'] ?? 0);
        $byDiff = ($bDiff * $aGames) <=> ($aDiff * $bGames);
        if ($byDiff !== 0) {
            return $byDiff;
        }
    }
    if ($aWins !== $bWins) {
        return $bWins <=> $aWins;
    }
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
}

function pickleballArchiveBanner($season) {
    if (!$season || !empty($season['is_active'])) {
        return '';
    }
    $name = htmlspecialchars($season['name']);
    $history = htmlspecialchars(pickleballPublicHref('pickleball_history.php', $season));
    return '<div class="alert alert-secondary">Archive: ' . $name . ' is closed. These results stay available for reference. '
        . '<a href="' . $history . '">Season history</a> · <a href="pickleball_standings.php">Current season</a></div>';
}

// Get current season for use in other scripts
$current_season = getCurrentSeason($conn);
?>
