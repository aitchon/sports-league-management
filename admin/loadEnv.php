<?php

// Function to load .env file
function loadEnv($file) {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignore comments (lines starting with # or ;)
            if (strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            // Split key-value pairs (assuming no '=' in values)
            list($key, $value) = explode('=', $line, 2);

            // Trim whitespace and assign to $_ENV
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    } else {
        throw new Exception("The .env file does not exist.");
    }
}

// Load environment variables from .env file
loadEnv(__DIR__ . '/.env'); // Change path as necessary
