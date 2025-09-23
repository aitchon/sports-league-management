-- Volleyball League Database Tables
-- Create these tables to add volleyball support alongside basketball

-- Volleyball Seasons
CREATE TABLE `volleyball_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volleyball Divisions
CREATE TABLE `volleyball_divisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_playoff` tinyint(4) NOT NULL DEFAULT 0,
  `season_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_volleyball_divisions_season` FOREIGN KEY (`season_id`) REFERENCES `volleyball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volleyball Teams
CREATE TABLE `volleyball_teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `coach` varchar(100) NOT NULL,
  `team_pic_url` varchar(255) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  `season_id` int(11) DEFAULT NULL,
  `is_playoff_placeholder` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `division_id` (`division_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_volleyball_teams_division` FOREIGN KEY (`division_id`) REFERENCES `volleyball_divisions` (`id`),
  CONSTRAINT `fk_volleyball_teams_season` FOREIGN KEY (`season_id`) REFERENCES `volleyball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volleyball Players
CREATE TABLE `volleyball_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `season_id` int(11) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL, -- Setter, Hitter, Libero, etc.
  `jersey_number` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_volleyball_players_team` FOREIGN KEY (`team_id`) REFERENCES `volleyball_teams` (`id`),
  CONSTRAINT `fk_volleyball_players_season` FOREIGN KEY (`season_id`) REFERENCES `volleyball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volleyball Games
CREATE TABLE `volleyball_games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `home_team_id` int(11) DEFAULT NULL,
  `away_team_id` int(11) DEFAULT NULL,
  `date` datetime NOT NULL,
  `location` varchar(255) NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `is_playoff` tinyint(4) NOT NULL DEFAULT 0,
  `season_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `home_team_id` (`home_team_id`),
  KEY `away_team_id` (`away_team_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_volleyball_games_home_team` FOREIGN KEY (`home_team_id`) REFERENCES `volleyball_teams` (`id`),
  CONSTRAINT `fk_volleyball_games_away_team` FOREIGN KEY (`away_team_id`) REFERENCES `volleyball_teams` (`id`),
  CONSTRAINT `fk_volleyball_games_season` FOREIGN KEY (`season_id`) REFERENCES `volleyball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volleyball Scores (Best of 3 or 5 sets)
CREATE TABLE `volleyball_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `set_number` int(11) NOT NULL, -- 1, 2, 3, 4, or 5
  `home_team_score` int(11) DEFAULT NULL,
  `away_team_score` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `fk_volleyball_scores_game` FOREIGN KEY (`game_id`) REFERENCES `volleyball_games` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Volleyball Player Stats
CREATE TABLE `volleyball_player_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `kills` int(11) DEFAULT 0,
  `assists` int(11) DEFAULT 0,
  `digs` int(11) DEFAULT 0,
  `blocks` int(11) DEFAULT 0,
  `aces` int(11) DEFAULT 0,
  `service_errors` int(11) DEFAULT 0,
  `attack_errors` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `fk_volleyball_player_stats_player` FOREIGN KEY (`player_id`) REFERENCES `volleyball_players` (`id`),
  CONSTRAINT `fk_volleyball_player_stats_game` FOREIGN KEY (`game_id`) REFERENCES `volleyball_games` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample volleyball season
INSERT INTO `volleyball_seasons` (`name`, `start_date`, `end_date`, `is_active`) VALUES
('2025 Spring Volleyball League', '2025-03-01', '2025-06-30', 1);
