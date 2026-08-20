<?php
// db_pdo.php - PDO-based database connection
// This is an alternative to db.php for systems without MySQLi

// Include the function to load .env file
require_once 'loadEnv.php';

// Check the value of APP_ENV after loading the .env file
$envFile = (__DIR__ . '/.env'); // Default to dev
if (getenv('APP_ENV') == 'production') {
    $envFile = (__DIR__ . '/.env.production');
}

// Load the correct .env file based on APP_ENV
loadEnv($envFile);

// Database configuration from the environment
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get current active season
function getCurrentSeason($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM seasons WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ?: null;
}

// Function to get all seasons
function getAllSeasons($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM seasons ORDER BY start_date DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Function to get all volleyball seasons
function getAllVolleyballSeasons($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM volleyball_seasons ORDER BY start_date DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Function to get volleyball season by ID
function getVolleyballSeasonById($pdo, $season_id) {
    $stmt = $pdo->prepare("SELECT * FROM volleyball_seasons WHERE id = ?");
    $stmt->execute([$season_id]);
    return $stmt->fetch();
}
?>
