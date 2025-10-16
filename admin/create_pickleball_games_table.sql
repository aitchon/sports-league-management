-- Create pickleball_games table if it doesn't exist
USE `aitchon_basketball_league`;

-- Check if table exists, if not create it
CREATE TABLE IF NOT EXISTS `pickleball_games` (
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player1_id` (`player1_id`),
  KEY `player2_id` (`player2_id`),
  KEY `player3_id` (`player3_id`),
  KEY `player4_id` (`player4_id`),
  KEY `challenger_id` (`challenger_id`),
  KEY `challenged_id` (`challenged_id`),
  KEY `season_id` (`season_id`),
  KEY `division_id` (`division_id`),
  KEY `date` (`date`),
  KEY `status` (`status`),
  KEY `game_type` (`game_type`),
  CONSTRAINT `fk_pickleball_games_player1` FOREIGN KEY (`player1_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_player2` FOREIGN KEY (`player2_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_player3` FOREIGN KEY (`player3_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_player4` FOREIGN KEY (`player4_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_challenger` FOREIGN KEY (`challenger_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_challenged` FOREIGN KEY (`challenged_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_games_division` FOREIGN KEY (`division_id`) REFERENCES `pickleball_divisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



