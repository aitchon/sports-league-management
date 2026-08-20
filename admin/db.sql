-- Create the database
CREATE DATABASE IF NOT EXISTS basketball_league;
USE basketball_league;

-- Divisions Table
CREATE TABLE divisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Teams Table
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    division_id INT,
    FOREIGN KEY (division_id) REFERENCES divisions(id)
);

-- Players Table
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    team_id INT,
    FOREIGN KEY (team_id) REFERENCES teams(id)
);

-- Games Table
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    location VARCHAR(100) NOT NULL,
    home_team_id INT,
    away_team_id INT,
    status ENUM('scheduled', 'completed') DEFAULT 'scheduled',
    FOREIGN KEY (home_team_id) REFERENCES teams(id),
    FOREIGN KEY (away_team_id) REFERENCES teams(id)
);

-- Scores Table
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT,
    home_team_score INT,
    away_team_score INT,
    FOREIGN KEY (game_id) REFERENCES games(id)
);

-- Player Statistics Table
CREATE TABLE player_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT,
    game_id INT,
    points INT,
    rebounds INT,
    assists INT,
    fouls INT,
    three_pointers_made INT,
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (game_id) REFERENCES games(id)
);

-- Users Table (for authentication)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL -- Store hashed passwords
);
