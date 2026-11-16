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
