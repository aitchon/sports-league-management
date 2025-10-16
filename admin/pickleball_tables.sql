use `aitchon_basketball_league`;
-- Pickleball Ladder League Database Tables
-- Create these tables to add pickleball ladder support alongside basketball and volleyball

-- Pickleball Seasons
CREATE TABLE `pickleball_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 0,
  `ladder_type` enum('singles','doubles','mixed') NOT NULL DEFAULT 'singles',
  `max_players` int(11) DEFAULT 50,
  `challenge_window_days` int(11) DEFAULT 7,
  `min_games_per_week` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Divisions (Skill Levels)
CREATE TABLE `pickleball_divisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `skill_level` varchar(50) NOT NULL, -- Beginner, Intermediate, Advanced, Pro
  `min_rating` decimal(4,2) DEFAULT 0.00,
  `max_rating` decimal(4,2) DEFAULT 5.00,
  `season_id` int(11) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_pickleball_divisions_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Players
CREATE TABLE `pickleball_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `dupr_rating` decimal(4,2) DEFAULT NULL,
  `current_rating` decimal(4,2) DEFAULT 2.50,
  `ladder_position` int(11) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  `season_id` int(11) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `join_date` date NOT NULL DEFAULT (CURRENT_DATE),
  `profile_pic_url` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `division_id` (`division_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_pickleball_players_division` FOREIGN KEY (`division_id`) REFERENCES `pickleball_divisions` (`id`),
  CONSTRAINT `fk_pickleball_players_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Games/Matches
CREATE TABLE `pickleball_games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player1_id` int(11) DEFAULT NULL,
  `player2_id` int(11) DEFAULT NULL,
  `player3_id` int(11) DEFAULT NULL, -- For doubles
  `player4_id` int(11) DEFAULT NULL, -- For doubles
  `challenger_id` int(11) DEFAULT NULL, -- Who initiated the challenge
  `challenged_id` int(11) DEFAULT NULL, -- Who was challenged
  `date` datetime NOT NULL,
  `location` varchar(255) NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled','forfeit') NOT NULL DEFAULT 'scheduled',
  `game_type` enum('singles','doubles','mixed') NOT NULL DEFAULT 'singles',
  `is_ladder_match` tinyint(4) NOT NULL DEFAULT 1,
  `is_challenge` tinyint(4) NOT NULL DEFAULT 0,
  `season_id` int(11) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player1_id` (`player1_id`),
  KEY `player2_id` (`player2_id`),
  KEY `player3_id` (`player3_id`),
  KEY `player4_id` (`player4_id`),
  KEY `challenger_id` (`challenger_id`),
  KEY `challenged_id` (`challenged_id`),
  KEY `season_id` (`season_id`),
  KEY `division_id` (`division_id`),
  CONSTRAINT `fk_pickleball_games_player1` FOREIGN KEY (`player1_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_games_player2` FOREIGN KEY (`player2_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_games_player3` FOREIGN KEY (`player3_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_games_player4` FOREIGN KEY (`player4_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_games_challenger` FOREIGN KEY (`challenger_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_games_challenged` FOREIGN KEY (`challenged_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_games_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_games_division` FOREIGN KEY (`division_id`) REFERENCES `pickleball_divisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Scores (Best of 3 games)
CREATE TABLE `pickleball_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `game_number` int(11) NOT NULL, -- 1, 2, or 3
  `player1_score` int(11) DEFAULT NULL,
  `player2_score` int(11) DEFAULT NULL,
  `player3_score` int(11) DEFAULT NULL, -- For doubles
  `player4_score` int(11) DEFAULT NULL, -- For doubles
  `winner_id` int(11) DEFAULT NULL, -- Who won this game
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `winner_id` (`winner_id`),
  CONSTRAINT `fk_pickleball_scores_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`),
  CONSTRAINT `fk_pickleball_scores_winner` FOREIGN KEY (`winner_id`) REFERENCES `pickleball_players` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Player Stats
CREATE TABLE `pickleball_player_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `wins` int(11) DEFAULT 0,
  `losses` int(11) DEFAULT 0,
  `total_games` int(11) DEFAULT 0,
  `win_percentage` decimal(5,2) DEFAULT 0.00,
  `points_for` int(11) DEFAULT 0,
  `points_against` int(11) DEFAULT 0,
  `point_differential` int(11) DEFAULT 0,
  `ladder_position` int(11) DEFAULT NULL,
  `rating_change` decimal(4,2) DEFAULT 0.00,
  `season_id` int(11) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `game_id` (`game_id`),
  KEY `season_id` (`season_id`),
  KEY `division_id` (`division_id`),
  CONSTRAINT `fk_pickleball_player_stats_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_player_stats_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`),
  CONSTRAINT `fk_pickleball_player_stats_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_player_stats_division` FOREIGN KEY (`division_id`) REFERENCES `pickleball_divisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Challenges
CREATE TABLE `pickleball_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenger_id` int(11) DEFAULT NULL,
  `challenged_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','declined','expired','completed') NOT NULL DEFAULT 'pending',
  `challenge_date` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `season_id` int(11) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `challenger_id` (`challenger_id`),
  KEY `challenged_id` (`challenged_id`),
  KEY `game_id` (`game_id`),
  KEY `season_id` (`season_id`),
  KEY `division_id` (`division_id`),
  CONSTRAINT `fk_pickleball_challenges_challenger` FOREIGN KEY (`challenger_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_challenges_challenged` FOREIGN KEY (`challenged_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_challenges_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`),
  CONSTRAINT `fk_pickleball_challenges_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_challenges_division` FOREIGN KEY (`division_id`) REFERENCES `pickleball_divisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pickleball Ladder History
CREATE TABLE `pickleball_ladder_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) DEFAULT NULL,
  `old_position` int(11) DEFAULT NULL,
  `new_position` int(11) DEFAULT NULL,
  `change_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` varchar(255) DEFAULT NULL, -- 'challenge_won', 'challenge_lost', 'admin_adjustment', etc.
  `game_id` int(11) DEFAULT NULL,
  `season_id` int(11) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `game_id` (`game_id`),
  KEY `season_id` (`season_id`),
  KEY `division_id` (`division_id`),
  CONSTRAINT `fk_pickleball_ladder_history_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_ladder_history_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`),
  CONSTRAINT `fk_pickleball_ladder_history_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_ladder_history_division` FOREIGN KEY (`division_id`) REFERENCES `pickleball_divisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample pickleball season
INSERT INTO `pickleball_seasons` (`name`, `start_date`, `end_date`, `is_active`, `ladder_type`, `max_players`, `challenge_window_days`, `min_games_per_week`) VALUES
('2025 Spring Pickleball Ladder', '2025-03-01', '2025-06-30', 1, 'singles', 50, 7, 1);

-- Insert sample pickleball divisions
INSERT INTO `pickleball_divisions` (`name`, `skill_level`, `min_rating`, `max_rating`, `season_id`, `is_active`) VALUES
('Beginner', 'Beginner', 0.00, 2.50, 1, 1),
('Intermediate', 'Intermediate', 2.51, 3.50, 1, 1),
('Advanced', 'Advanced', 3.51, 4.50, 1, 1),
('Pro', 'Pro', 4.51, 5.00, 1, 1);



