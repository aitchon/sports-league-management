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
?>
