-- Create table to track individual player statistics
CREATE TABLE `pickleball_player_stats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT NOT NULL,
  `game_id` INT NOT NULL,
  `match_number` TINYINT NOT NULL, -- 1, 2, or 3 for the three matches
  `points_scored` INT NOT NULL DEFAULT 0,
  `points_against` INT NOT NULL DEFAULT 0,
  `won_match` TINYINT(1) NOT NULL DEFAULT 0, -- 1 if won, 0 if lost
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `player_game_match_unique` (`player_id`, `game_id`, `match_number`),
  CONSTRAINT `fk_player_stats_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_player_stats_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

